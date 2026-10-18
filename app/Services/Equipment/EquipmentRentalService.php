<?php

namespace App\Services\Equipment;

use App\Enums\EquipmentRentalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BusinessException;
use App\Models\Equipment;
use App\Models\EquipmentRental;
use App\Repositories\Contracts\EquipmentRentalRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Content\AppSettingsService;
use App\Services\Notification\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class EquipmentRentalService
{
    public function __construct(
        protected EquipmentRentalRepositoryInterface $rentalRepository,
        protected PaymentRepositoryInterface $paymentRepository,
        protected EquipmentRentalInventoryService $inventory,
        protected EquipmentRentalPricingService $pricing,
        protected AppSettingsService $appSettings,
        protected AdminNotificationService $adminNotifications,
    ) {
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    public function quote(string $startDate, string $endDate, array $items, ?int $excludeRentalId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new BusinessException('end_date must be on or after start_date.', 'invalid_date_range');
        }

        [$periodStart, $periodEnd] = $this->inventory->rentalPeriod($start->toDateString(), $end->toDateString());
        $this->inventory->validateAvailability($items, $periodStart, $periodEnd, $excludeRentalId);

        $days = (int) $start->diffInDays($end) + 1;
        $priced = $this->pricing->buildLines($items, $days);

        $taxRate = (float) $this->appSettings->get('tax_rate', 0);
        $taxAmount = round($priced['subtotal'] * ($taxRate / 100), 2);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'line_items' => $priced['lines'],
            'subtotal' => $priced['subtotal'],
            'tax_amount' => $taxAmount,
            'total_amount' => round($priced['subtotal'] + $taxAmount, 2),
        ];
    }

    /**
     * @param  array{start_date: string, end_date: string, items: array<int, array{id: int, quantity: int}>, customer_notes?: string|null}  $data
     */
    public function create(int $userId, array $data, ?int $createdBy = null): EquipmentRental
    {
        $rental = DB::transaction(function () use ($userId, $data, $createdBy) {
            Equipment::query()
                ->whereIn('id', array_keys($this->inventory->groupItems($data['items'])))
                ->lockForUpdate()
                ->get();

            $quote = $this->quote($data['start_date'], $data['end_date'], $data['items']);

            /** @var EquipmentRental $rental */
            $rental = $this->rentalRepository->create([
                'rental_number' => $this->generateRentalNumber(),
                'user_id' => $userId,
                'start_date' => $quote['start_date'],
                'end_date' => $quote['end_date'],
                'days' => $quote['days'],
                'status' => EquipmentRentalStatus::Confirmed->value,
                'payment_method' => PaymentMethod::Cash->value,
                'subtotal' => $quote['subtotal'],
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total_amount'],
                'paid_amount' => 0,
                'remaining_amount' => $quote['total_amount'],
                'customer_notes' => $data['customer_notes'] ?? null,
                'created_by' => $createdBy ?? $userId,
            ]);

            foreach ($quote['line_items'] as $line) {
                $rental->items()->create([
                    'equipment_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ]);
            }

            return $rental->fresh(['items.equipment.images']);
        });

        $this->notifyAdminsOfNewOrder($rental);

        return $rental;
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->rentalRepository->paginateForUser($userId, $perPage);
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->rentalRepository->paginateFiltered($filters, $perPage);
    }

    public function findForUser(int $rentalId, int $userId): EquipmentRental
    {
        return EquipmentRental::query()
            ->where('user_id', $userId)
            ->with(['items.equipment.images', 'payments'])
            ->findOrFail($rentalId);
    }

    public function findForAdmin(int $rentalId): EquipmentRental
    {
        return EquipmentRental::query()
            ->with(['user', 'creator', 'items.equipment.images', 'payments'])
            ->findOrFail($rentalId);
    }

    public function cancelByCustomer(int $rentalId, int $userId): EquipmentRental
    {
        return DB::transaction(function () use ($rentalId, $userId) {
            $rental = EquipmentRental::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($rentalId);

            if (! in_array($rental->status, [EquipmentRentalStatus::Pending, EquipmentRentalStatus::Confirmed], true)) {
                throw new BusinessException('Rental cannot be cancelled in its current state.', 'rental_not_cancellable');
            }

            if (! Carbon::parse($rental->start_date)->startOfDay()->isFuture()) {
                throw new BusinessException('Rental can only be cancelled before its start date.', 'rental_already_started');
            }

            $rental->update(['status' => EquipmentRentalStatus::Cancelled->value]);

            return $rental->fresh(['items.equipment.images', 'payments']);
        });
    }

    public function updateStatus(int $rentalId, EquipmentRentalStatus $status): EquipmentRental
    {
        return DB::transaction(function () use ($rentalId, $status) {
            $rental = EquipmentRental::query()->lockForUpdate()->findOrFail($rentalId);
            $this->assertValidTransition($rental->status, $status);

            if ($status === EquipmentRentalStatus::Confirmed) {
                $this->revalidateStock($rental);
            }

            $rental->update(['status' => $status->value]);

            return $this->findForAdmin($rentalId);
        });
    }

    public function markCashPaid(int $rentalId, ?float $amount = null): EquipmentRental
    {
        return DB::transaction(function () use ($rentalId, $amount) {
            $rental = EquipmentRental::query()->lockForUpdate()->findOrFail($rentalId);

            if ($rental->status === EquipmentRentalStatus::Cancelled) {
                throw new BusinessException('Cannot record a payment for a cancelled rental.', 'rental_cancelled');
            }

            $payAmount = round($amount ?? (float) $rental->remaining_amount, 2);

            if ($payAmount <= 0 || (float) $rental->remaining_amount <= 0) {
                throw new BusinessException('No amount due for this rental.', 'nothing_to_pay');
            }

            if ($payAmount > (float) $rental->remaining_amount) {
                throw new BusinessException('Amount exceeds the remaining balance.', 'amount_exceeds_remaining');
            }

            $this->paymentRepository->create([
                'equipment_rental_id' => $rental->id,
                'type' => PaymentType::Full->value,
                'amount' => $payAmount,
                'status' => PaymentStatus::Paid->value,
                'provider' => 'cash',
                'paid_at' => now(),
            ]);

            $this->applyPayment($rental, $payAmount);

            return $this->findForAdmin($rentalId);
        });
    }

    public function applyPayment(EquipmentRental $rental, float $amount): void
    {
        $paidAmount = round((float) $rental->paid_amount + $amount, 2);

        $rental->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, round((float) $rental->total_amount - $paidAmount, 2)),
        ]);
    }

    protected function notifyAdminsOfNewOrder(EquipmentRental $rental): void
    {
        try {
            $customer = $rental->user()->first(['id', 'name', 'phone']);
            $itemsCount = (int) $rental->items->sum('quantity');

            $this->adminNotifications->notifyAdmins(
                'equipment_rental.created',
                'New equipment rental',
                sprintf(
                    '%s rented equipment %s (%d item%s, %s EGP) from %s to %s.',
                    $customer?->name ?? 'A customer',
                    $rental->rental_number,
                    $itemsCount,
                    $itemsCount === 1 ? '' : 's',
                    number_format((float) $rental->total_amount, 2),
                    $rental->start_date->toDateString(),
                    $rental->end_date->toDateString(),
                ),
                [
                    'rental_id' => $rental->id,
                    'rental_number' => $rental->rental_number,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'customer_phone' => $customer?->phone,
                    'items_count' => $itemsCount,
                    'total_amount' => (float) $rental->total_amount,
                    'start_date' => $rental->start_date->toDateString(),
                    'end_date' => $rental->end_date->toDateString(),
                ],
                'equipment.manage',
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function revalidateStock(EquipmentRental $rental): void
    {
        [$periodStart, $periodEnd] = $this->inventory->rentalPeriod(
            Carbon::parse($rental->start_date)->toDateString(),
            Carbon::parse($rental->end_date)->toDateString(),
        );

        $items = $rental->items()
            ->get(['equipment_id', 'quantity'])
            ->map(fn ($item) => ['id' => (int) $item->equipment_id, 'quantity' => (int) $item->quantity])
            ->all();

        $this->inventory->validateAvailability($items, $periodStart, $periodEnd, $rental->id);
    }

    protected function assertValidTransition(EquipmentRentalStatus $from, EquipmentRentalStatus $to): void
    {
        $allowed = [
            EquipmentRentalStatus::Pending->value => [EquipmentRentalStatus::Confirmed, EquipmentRentalStatus::Cancelled],
            EquipmentRentalStatus::Confirmed->value => [EquipmentRentalStatus::InProgress, EquipmentRentalStatus::Cancelled],
            EquipmentRentalStatus::InProgress->value => [EquipmentRentalStatus::Completed],
        ];

        if (! in_array($to, $allowed[$from->value] ?? [], true)) {
            throw new BusinessException(
                "Cannot change rental status from {$from->value} to {$to->value}.",
                'invalid_status_transition',
            );
        }
    }

    protected function generateRentalNumber(): string
    {
        do {
            $number = 'ER-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->rentalRepository->existsByRentalNumber($number));

        return $number;
    }
}
