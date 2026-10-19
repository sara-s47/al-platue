<?php

namespace App\Services\Rental;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RentalOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\CartItem;
use App\Models\Equipment;
use App\Models\RentalOrder;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RentalOrderRepositoryInterface;
use App\Services\Accessory\AccessoryInventoryService;
use App\Services\Accessory\AccessoryPricingService;
use App\Services\Content\AppSettingsService;
use App\Services\Engagement\CartService;
use App\Services\Equipment\EquipmentRentalInventoryService;
use App\Services\Equipment\EquipmentRentalPricingService;
use App\Services\Notification\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class RentalOrderService
{
    public function __construct(
        protected RentalOrderRepositoryInterface $orderRepository,
        protected PaymentRepositoryInterface $paymentRepository,
        protected AccessoryInventoryService $accessoryInventory,
        protected EquipmentRentalInventoryService $equipmentInventory,
        protected AccessoryPricingService $accessoryPricing,
        protected EquipmentRentalPricingService $equipmentPricing,
        protected AppSettingsService $appSettings,
        protected CartService $cartService,
        protected AdminNotificationService $adminNotifications,
    ) {
    }

    /**
     * @param  array<int, array{type: string, id: int, quantity: int}>|null  $items
     * @return array<string, mixed>
     */
    public function quote(
        string $startDate,
        string $endDate,
        ?array $items = null,
        ?int $userId = null,
        ?int $excludeOrderId = null,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new BusinessException('end_date must be on or after start_date.', 'invalid_date_range');
        }

        $normalized = $items !== null
            ? $this->normalizeItems($items)
            : $this->itemsFromCart($userId);

        if ($normalized['accessories'] === [] && $normalized['equipment'] === []) {
            throw new BusinessException('Cart is empty. Add equipment or accessories first.', 'cart_empty');
        }

        [$periodStart, $periodEnd] = $this->accessoryInventory->rentalPeriod(
            $start->toDateString(),
            $end->toDateString(),
        );

        if ($normalized['accessories'] !== []) {
            $this->accessoryInventory->validateAvailability(
                $normalized['accessories'],
                $periodStart,
                $periodEnd,
                null,
                $excludeOrderId,
            );
        }

        if ($normalized['equipment'] !== []) {
            $this->equipmentInventory->validateAvailability(
                $normalized['equipment'],
                $periodStart,
                $periodEnd,
                $excludeOrderId,
            );
        }

        $days = (int) $start->diffInDays($end) + 1;
        $lines = [];
        $subtotal = 0.0;

        if ($normalized['accessories'] !== []) {
            $priced = $this->accessoryPricing->buildLines($normalized['accessories'], $days);
            $lines = array_merge($lines, $priced['lines']);
            $subtotal += $priced['subtotal'];
        }

        if ($normalized['equipment'] !== []) {
            $priced = $this->equipmentPricing->buildLines($normalized['equipment'], $days);
            $lines = array_merge($lines, $priced['lines']);
            $subtotal += $priced['subtotal'];
        }

        $subtotal = round($subtotal, 2);
        $taxRate = (float) $this->appSettings->get('tax_rate', 0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'line_items' => $lines,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
        ];
    }

    /**
     * Checkout the user's cart into one rental order.
     *
     * @param  array{start_date: string, end_date: string, customer_notes?: string|null}  $data
     */
    public function checkoutFromCart(int $userId, array $data, ?int $createdBy = null): RentalOrder
    {
        $items = $this->itemsFromCart($userId);

        return $this->create($userId, [
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'customer_notes' => $data['customer_notes'] ?? null,
            'accessories' => $items['accessories'],
            'equipment' => $items['equipment'],
        ], $createdBy, clearCart: true);
    }

    /**
     * Create a rental order from explicit typed item lists (used by wrappers / checkout).
     *
     * @param  array{
     *   start_date: string,
     *   end_date: string,
     *   customer_notes?: string|null,
     *   accessories?: array<int, array{id: int, quantity: int}>,
     *   equipment?: array<int, array{id: int, quantity: int}>,
     *   items?: array<int, array{type: string, id: int, quantity: int}>
     * }  $data
     */
    public function create(int $userId, array $data, ?int $createdBy = null, bool $clearCart = false): RentalOrder
    {
        if (isset($data['items'])) {
            $normalized = $this->normalizeItems($data['items']);
        } else {
            $normalized = [
                'accessories' => $data['accessories'] ?? [],
                'equipment' => $data['equipment'] ?? [],
            ];
        }

        if ($normalized['accessories'] === [] && $normalized['equipment'] === []) {
            throw new BusinessException('At least one equipment or accessory item is required.', 'items_required');
        }

        $order = DB::transaction(function () use ($userId, $data, $createdBy, $normalized, $clearCart) {
            $accessoryIds = array_keys($this->accessoryInventory->groupItems($normalized['accessories']));
            $equipmentIds = array_keys($this->equipmentInventory->groupItems($normalized['equipment']));

            if ($accessoryIds !== []) {
                Accessory::query()->whereIn('id', $accessoryIds)->lockForUpdate()->get();
            }
            if ($equipmentIds !== []) {
                Equipment::query()->whereIn('id', $equipmentIds)->lockForUpdate()->get();
            }

            $quote = $this->quote(
                $data['start_date'],
                $data['end_date'],
                array_merge(
                    array_map(fn ($i) => ['type' => 'accessory', 'id' => $i['id'], 'quantity' => $i['quantity']], $normalized['accessories']),
                    array_map(fn ($i) => ['type' => 'equipment', 'id' => $i['id'], 'quantity' => $i['quantity']], $normalized['equipment']),
                ),
            );

            /** @var RentalOrder $order */
            $order = $this->orderRepository->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $userId,
                'start_date' => $quote['start_date'],
                'end_date' => $quote['end_date'],
                'days' => $quote['days'],
                'status' => RentalOrderStatus::Confirmed->value,
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
                if (($line['item_type'] ?? '') === 'accessory') {
                    $order->accessoryItems()->create([
                        'accessory_id' => $line['item_id'],
                        'quantity' => $line['quantity'],
                        'days' => $line['days'],
                        'unit_price' => $line['unit_price'],
                        'total_price' => $line['total_price'],
                    ]);
                } elseif (($line['item_type'] ?? '') === 'equipment') {
                    $order->equipmentItems()->create([
                        'equipment_id' => $line['item_id'],
                        'quantity' => $line['quantity'],
                        'days' => $line['days'],
                        'unit_price' => $line['unit_price'],
                        'total_price' => $line['total_price'],
                    ]);
                }
            }

            if ($clearCart) {
                $this->cartService->clear($userId);
            }

            return $order->fresh([
                'accessoryItems.accessory.images',
                'equipmentItems.equipment.images',
            ]);
        });

        $this->notifyAdminsOfNewOrder($order);

        return $order;
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForUser($userId, $perPage);
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->paginateFiltered($filters, $perPage);
    }

    public function findForUser(int $orderId, int $userId): RentalOrder
    {
        return RentalOrder::query()
            ->where('user_id', $userId)
            ->with([
                'accessoryItems.accessory.images',
                'equipmentItems.equipment.images',
                'payments',
            ])
            ->findOrFail($orderId);
    }

    public function findForAdmin(int $orderId): RentalOrder
    {
        return RentalOrder::query()
            ->with([
                'user',
                'creator',
                'accessoryItems.accessory.images',
                'equipmentItems.equipment.images',
                'payments',
            ])
            ->findOrFail($orderId);
    }

    public function cancelByCustomer(int $orderId, int $userId): RentalOrder
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = RentalOrder::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [RentalOrderStatus::Pending, RentalOrderStatus::Confirmed], true)) {
                throw new BusinessException('Order cannot be cancelled in its current state.', 'order_not_cancellable');
            }

            if (! Carbon::parse($order->start_date)->startOfDay()->isFuture()) {
                throw new BusinessException('Order can only be cancelled before its start date.', 'order_already_started');
            }

            $order->update(['status' => RentalOrderStatus::Cancelled->value]);

            return $this->findForUser($orderId, $userId);
        });
    }

    public function updateStatus(int $orderId, RentalOrderStatus $status): RentalOrder
    {
        return DB::transaction(function () use ($orderId, $status) {
            $order = RentalOrder::query()->lockForUpdate()->findOrFail($orderId);
            $this->assertValidTransition($order->status, $status);

            if ($status === RentalOrderStatus::Confirmed) {
                $this->revalidateStock($order);
            }

            $order->update(['status' => $status->value]);

            return $this->findForAdmin($orderId);
        });
    }

    public function markCashPaid(int $orderId, ?float $amount = null): RentalOrder
    {
        return DB::transaction(function () use ($orderId, $amount) {
            $order = RentalOrder::query()->lockForUpdate()->findOrFail($orderId);

            if ($order->status === RentalOrderStatus::Cancelled) {
                throw new BusinessException('Cannot record a payment for a cancelled order.', 'order_cancelled');
            }

            $payAmount = round($amount ?? (float) $order->remaining_amount, 2);

            if ($payAmount <= 0 || (float) $order->remaining_amount <= 0) {
                throw new BusinessException('No amount due for this order.', 'nothing_to_pay');
            }

            if ($payAmount > (float) $order->remaining_amount) {
                throw new BusinessException('Amount exceeds the remaining balance.', 'amount_exceeds_remaining');
            }

            $this->paymentRepository->create([
                'rental_order_id' => $order->id,
                'type' => PaymentType::Full->value,
                'amount' => $payAmount,
                'status' => PaymentStatus::Paid->value,
                'provider' => 'cash',
                'paid_at' => now(),
            ]);

            $this->applyPayment($order, $payAmount);

            return $this->findForAdmin($orderId);
        });
    }

    public function applyPayment(RentalOrder $order, float $amount): void
    {
        $paidAmount = round((float) $order->paid_amount + $amount, 2);

        $order->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, round((float) $order->total_amount - $paidAmount, 2)),
        ]);
    }

    /**
     * @return array{accessories: array<int, array{id: int, quantity: int}>, equipment: array<int, array{id: int, quantity: int}>}
     */
    protected function itemsFromCart(?int $userId): array
    {
        if (! $userId) {
            throw new BusinessException('Cart checkout requires an authenticated user.', 'unauthenticated', 401);
        }

        $cart = $this->cartService->listForUser($userId);
        $items = $cart->map(fn (CartItem $item) => [
            'type' => $item->item_type,
            'id' => (int) $item->item_id,
            'quantity' => (int) $item->quantity,
        ])->all();

        return $this->normalizeItems($items);
    }

    /**
     * @param  array<int, array{type?: string, id: int, quantity: int}>  $items
     * @return array{accessories: array<int, array{id: int, quantity: int}>, equipment: array<int, array{id: int, quantity: int}>}
     */
    protected function normalizeItems(array $items): array
    {
        $accessories = [];
        $equipment = [];

        foreach ($items as $item) {
            $type = $item['type'] ?? null;
            $row = ['id' => (int) $item['id'], 'quantity' => (int) $item['quantity']];

            if ($type === CartItem::TYPE_ACCESSORY || $type === 'accessory') {
                $accessories[] = $row;
            } elseif ($type === CartItem::TYPE_EQUIPMENT || $type === 'equipment') {
                $equipment[] = $row;
            } else {
                throw new BusinessException('Invalid item type. Use equipment or accessory.', 'invalid_item_type');
            }
        }

        return [
            'accessories' => $accessories,
            'equipment' => $equipment,
        ];
    }

    protected function revalidateStock(RentalOrder $order): void
    {
        [$periodStart, $periodEnd] = $this->accessoryInventory->rentalPeriod(
            Carbon::parse($order->start_date)->toDateString(),
            Carbon::parse($order->end_date)->toDateString(),
        );

        $accessories = $order->accessoryItems()
            ->get(['accessory_id', 'quantity'])
            ->map(fn ($item) => ['id' => (int) $item->accessory_id, 'quantity' => (int) $item->quantity])
            ->all();

        $equipment = $order->equipmentItems()
            ->get(['equipment_id', 'quantity'])
            ->map(fn ($item) => ['id' => (int) $item->equipment_id, 'quantity' => (int) $item->quantity])
            ->all();

        if ($accessories !== []) {
            $this->accessoryInventory->validateAvailability($accessories, $periodStart, $periodEnd, null, $order->id);
        }
        if ($equipment !== []) {
            $this->equipmentInventory->validateAvailability($equipment, $periodStart, $periodEnd, $order->id);
        }
    }

    protected function assertValidTransition(RentalOrderStatus $from, RentalOrderStatus $to): void
    {
        $allowed = [
            RentalOrderStatus::Pending->value => [RentalOrderStatus::Confirmed, RentalOrderStatus::Cancelled],
            RentalOrderStatus::Confirmed->value => [RentalOrderStatus::InProgress, RentalOrderStatus::Cancelled],
            RentalOrderStatus::InProgress->value => [RentalOrderStatus::Completed],
        ];

        if (! in_array($to, $allowed[$from->value] ?? [], true)) {
            throw new BusinessException(
                "Cannot change order status from {$from->value} to {$to->value}.",
                'invalid_status_transition',
            );
        }
    }

    protected function notifyAdminsOfNewOrder(RentalOrder $order): void
    {
        try {
            $customer = $order->user()->first(['id', 'name', 'phone']);
            $itemsCount = (int) $order->accessoryItems->sum('quantity')
                + (int) $order->equipmentItems->sum('quantity');

            $this->adminNotifications->notifyAdmins(
                'rental_order.created',
                'New rental order',
                sprintf(
                    '%s placed order %s (%d item%s, %s EGP) from %s to %s.',
                    $customer?->name ?? 'A customer',
                    $order->order_number,
                    $itemsCount,
                    $itemsCount === 1 ? '' : 's',
                    number_format((float) $order->total_amount, 2),
                    $order->start_date->toDateString(),
                    $order->end_date->toDateString(),
                ),
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'customer_phone' => $customer?->phone,
                    'items_count' => $itemsCount,
                    'total_amount' => (float) $order->total_amount,
                    'start_date' => $order->start_date->toDateString(),
                    'end_date' => $order->end_date->toDateString(),
                ],
                null,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'RO-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->orderRepository->existsByOrderNumber($number));

        return $number;
    }
}
