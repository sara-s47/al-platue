<?php

namespace App\Services\Order;

use App\Enums\BookingMode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RentalOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Caravan;
use App\Models\CartItem;
use App\Models\Equipment;
use App\Models\Generator;
use App\Models\Order;
use App\Models\PortableBathroom;
use App\Models\Studio;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RentalOrderRepositoryInterface;
use App\Services\Accessory\AccessoryInventoryService;
use App\Services\Accessory\AccessoryPricingService;
use App\Services\Booking\BookingService;
use App\Services\Content\AppSettingsService;
use App\Services\DayCatalog\DayCatalogInventoryService;
use App\Services\DayCatalog\DayCatalogPricingService;
use App\Services\Engagement\CartService;
use App\Services\Equipment\EquipmentRentalInventoryService;
use App\Services\Equipment\EquipmentRentalPricingService;
use App\Services\Notification\AdminNotificationService;
use App\Services\Studio\StudioPaintService;
use App\Support\DayCatalog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderService
{
    public function __construct(
        protected RentalOrderRepositoryInterface $orderRepository,
        protected PaymentRepositoryInterface $paymentRepository,
        protected AccessoryInventoryService $accessoryInventory,
        protected EquipmentRentalInventoryService $equipmentInventory,
        protected DayCatalogInventoryService $dayCatalogInventory,
        protected AccessoryPricingService $accessoryPricing,
        protected EquipmentRentalPricingService $equipmentPricing,
        protected DayCatalogPricingService $dayCatalogPricing,
        protected AppSettingsService $appSettings,
        protected CartService $cartService,
        protected AdminNotificationService $adminNotifications,
        protected BookingService $bookingService,
        protected StudioPaintService $paintService,
    ) {
    }

    /**
     * @param  array<int, array{type: string, id: int, quantity: int}>|null  $items
     * @return array<string, mixed>
     */
    public function quote(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $items = null,
        ?int $userId = null,
        ?int $excludeOrderId = null,
        ?array $studioMeta = null,
        ?int $paintColorId = null,
    ): array {
        $fromCart = $items === null && $userId;
        $cartBundle = $fromCart ? $this->bundleFromCart($userId) : null;

        if ($cartBundle) {
            $normalized = $cartBundle['rentals'];
            $studioMeta = $studioMeta ?? $cartBundle['studio_meta'];
            $paintColorId = $paintColorId ?? ($studioMeta['paint_color_id'] ?? null);
        } else {
            $normalized = $this->normalizeItems($items ?? []);
        }

        $hasStudio = is_array($studioMeta) && ! empty($studioMeta['studio_id'] ?? null);
        $hasRentals = $this->hasAnyRentals($normalized);

        if (! $hasStudio && ! $hasRentals) {
            throw new BusinessException('Cart is empty. Add a studio or rental items first.', 'cart_empty');
        }

        if ($hasStudio) {
            [$startDate, $endDate] = $this->resolveDatesFromStudioMeta($studioMeta, $startDate, $endDate);
        }

        if (! $startDate || ! $endDate) {
            throw new BusinessException('start_date and end_date are required.', 'dates_required');
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new BusinessException('end_date must be on or after start_date.', 'invalid_date_range');
        }

        [$periodStart, $periodEnd] = $this->accessoryInventory->rentalPeriod(
            $start->toDateString(),
            $end->toDateString(),
        );

        $this->validateRentalStock($normalized, $periodStart, $periodEnd, $excludeOrderId);

        $days = (int) $start->diffInDays($end) + 1;
        $rentalLines = [];
        $rentalSubtotal = 0.0;

        foreach ($this->priceRentals($normalized, $days) as $priced) {
            $rentalLines = array_merge($rentalLines, $priced['lines']);
            $rentalSubtotal += $priced['subtotal'];
        }

        $studioBlock = null;
        $studioAmount = 0.0;
        $paintSurcharge = 0.0;
        $paintLine = null;
        $resolvedPaintColorId = null;

        if ($hasStudio) {
            $studioBlock = $this->quoteStudioPortion($userId, $studioMeta);
            $studioAmount = (float) ($studioBlock['subtotal'] ?? 0);
            $paint = $this->paintService->resolveSurcharge(
                (int) $studioMeta['studio_id'],
                isset($paintColorId) ? (int) $paintColorId : null,
            );
            $paintSurcharge = (float) $paint['surcharge'];
            $resolvedPaintColorId = $paint['color']?->id;
            if ($paintSurcharge > 0 || $paint['color'] !== null) {
                $paintLine = [
                    'item_type' => 'paint',
                    'item_id' => $paint['color']?->id,
                    'description' => $paint['color']?->name ?? 'Wall paint',
                    'quantity' => 1,
                    'days' => $days,
                    'unit_price' => $paintSurcharge,
                    'total_price' => $paintSurcharge,
                    'included' => $paintSurcharge <= 0,
                ];
            }
        }

        $subtotal = round($rentalSubtotal + $studioAmount + $paintSurcharge, 2);
        $taxRate = (float) $this->appSettings->get('tax_rate', 0);
        // Studio quote already includes tax on the booking side; apply tax only to rentals + paint.
        $taxable = round($rentalSubtotal + $paintSurcharge, 2);
        $taxAmount = round($taxable * ($taxRate / 100), 2);
        $studioTax = (float) ($studioBlock['tax_amount'] ?? 0);
        $totalTax = round($taxAmount + $studioTax, 2);
        $studioTotal = (float) ($studioBlock['total_amount'] ?? $studioAmount);
        $totalAmount = round($taxable + $taxAmount + $studioTotal, 2);

        $grouped = [
            'studio' => $studioBlock ? [[
                'item_type' => 'studio',
                'item_id' => (int) $studioMeta['studio_id'],
                'description' => Studio::query()->find((int) $studioMeta['studio_id'])?->name,
                'subtotal' => $studioAmount,
                'tax_amount' => $studioTax,
                'total_amount' => $studioTotal,
                'booking_mode' => $studioMeta['booking_mode'] ?? null,
            ]] : [],
            'paint' => $paintLine ? [$paintLine] : [],
            'rentals' => $rentalLines,
        ];

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'line_items' => array_values(array_filter(array_merge(
                $grouped['studio'],
                $grouped['paint'],
                $grouped['rentals'],
            ))),
            'groups' => $grouped,
            'studio_amount' => round($studioAmount, 2),
            'paint_surcharge' => round($paintSurcharge, 2),
            'paint_color_id' => $resolvedPaintColorId,
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'total_amount' => $totalAmount,
            'studio_meta' => $hasStudio ? $studioMeta : null,
        ];
    }

    /**
     * @param  array{start_date?: string, end_date?: string, customer_notes?: string|null}  $data
     */
    public function checkoutFromCart(int $userId, array $data, ?int $createdBy = null): Order
    {
        $bundle = $this->bundleFromCart($userId);

        return $this->create($userId, [
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'accessories' => $bundle['rentals']['accessories'],
            'equipment' => $bundle['rentals']['equipment'],
            'portable_bathrooms' => $bundle['rentals']['portable_bathrooms'],
            'caravans' => $bundle['rentals']['caravans'],
            'generators' => $bundle['rentals']['generators'],
            'studio_meta' => $bundle['studio_meta'],
        ], $createdBy, clearCart: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, array $data, ?int $createdBy = null, bool $clearCart = false): Order
    {
        if (isset($data['items'])) {
            $normalized = $this->normalizeItems($data['items']);
        } else {
            $normalized = [
                'accessories' => $data['accessories'] ?? [],
                'equipment' => $data['equipment'] ?? [],
                'portable_bathrooms' => $data['portable_bathrooms'] ?? [],
                'caravans' => $data['caravans'] ?? [],
                'generators' => $data['generators'] ?? [],
            ];
        }

        $studioMeta = $data['studio_meta'] ?? null;
        $hasStudio = is_array($studioMeta) && ! empty($studioMeta['studio_id']);
        $hasRentals = $this->hasAnyRentals($normalized);

        if (! $hasStudio && ! $hasRentals) {
            throw new BusinessException('At least one studio or rental item is required.', 'items_required');
        }

        $order = DB::transaction(function () use ($userId, $data, $createdBy, $normalized, $clearCart, $studioMeta, $hasStudio) {
            $this->lockRentalRows($normalized);

            $quote = $this->quote(
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                array_merge(
                    $this->flattenTyped($normalized['accessories'], 'accessory'),
                    $this->flattenTyped($normalized['equipment'], 'equipment'),
                    $this->flattenTyped($normalized['portable_bathrooms'], DayCatalog::PORTABLE_BATHROOM),
                    $this->flattenTyped($normalized['caravans'], DayCatalog::CARAVAN),
                    $this->flattenTyped($normalized['generators'], DayCatalog::GENERATOR),
                ),
                $userId,
                null,
                $studioMeta,
                isset($studioMeta['paint_color_id']) ? (int) $studioMeta['paint_color_id'] : null,
            );

            $bookingId = null;
            if ($hasStudio) {
                $booking = $this->createBookingFromStudioMeta($userId, $studioMeta, $data['customer_notes'] ?? null);
                $bookingId = (int) $booking->id;
            }

            /** @var Order $order */
            $order = $this->orderRepository->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $userId,
                'booking_id' => $bookingId,
                'start_date' => $quote['start_date'],
                'end_date' => $quote['end_date'],
                'days' => $quote['days'],
                'status' => RentalOrderStatus::Confirmed->value,
                'payment_method' => PaymentMethod::Cash->value,
                'subtotal' => $quote['subtotal'],
                'studio_amount' => $quote['studio_amount'] ?? 0,
                'paint_surcharge' => $quote['paint_surcharge'] ?? 0,
                'paint_color_id' => $quote['paint_color_id'] ?? null,
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total_amount'],
                'paid_amount' => 0,
                'remaining_amount' => $quote['total_amount'],
                'customer_notes' => $data['customer_notes'] ?? null,
                'created_by' => $createdBy ?? $userId,
            ]);

            $this->persistRentalLines($order, $quote['line_items']);

            if ($clearCart) {
                $this->cartService->clear($userId);
            }

            return $this->findForAdmin($order->id);
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

    public function findForUser(int $orderId, int $userId): Order
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with($this->defaultWith())
            ->findOrFail($orderId);
    }

    public function findForAdmin(int $orderId): Order
    {
        return Order::query()
            ->with(array_merge($this->defaultWith(), ['user', 'creator', 'booking.studio', 'paintColor']))
            ->findOrFail($orderId);
    }

    public function cancelByCustomer(int $orderId, int $userId): Order
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = Order::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [RentalOrderStatus::Pending, RentalOrderStatus::Confirmed], true)) {
                throw new BusinessException('Order cannot be cancelled in its current state.', 'order_not_cancellable');
            }

            if ($order->start_date && ! Carbon::parse($order->start_date)->startOfDay()->isFuture()) {
                throw new BusinessException('Order can only be cancelled before its start date.', 'order_already_started');
            }

            $order->update(['status' => RentalOrderStatus::Cancelled->value]);

            return $this->findForUser($orderId, $userId);
        });
    }

    public function updateStatus(int $orderId, RentalOrderStatus $status): Order
    {
        return DB::transaction(function () use ($orderId, $status) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $this->assertValidTransition($order->status, $status);

            if ($status === RentalOrderStatus::Confirmed) {
                $this->revalidateStock($order);
            }

            $order->update(['status' => $status->value]);

            return $this->findForAdmin($orderId);
        });
    }

    public function markCashPaid(int $orderId, ?float $amount = null): Order
    {
        return DB::transaction(function () use ($orderId, $amount) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

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

            $payload = [
                'type' => PaymentType::Full->value,
                'amount' => $payAmount,
                'status' => PaymentStatus::Paid->value,
                'provider' => 'cash',
                'paid_at' => now(),
                'order_id' => $order->id,
            ];

            if (Schema::hasColumn('payments', 'rental_order_id')) {
                $payload['rental_order_id'] = $order->id;
            }

            if ($order->booking_id) {
                $payload['booking_id'] = $order->booking_id;
            }

            $this->paymentRepository->create($payload);
            $this->applyPayment($order, $payAmount);

            return $this->findForAdmin($orderId);
        });
    }

    public function applyPayment(Order $order, float $amount): void
    {
        $paidAmount = round((float) $order->paid_amount + $amount, 2);

        $order->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, round((float) $order->total_amount - $paidAmount, 2)),
        ]);

        if ($order->booking_id && (float) $order->remaining_amount <= 0) {
            $booking = $order->booking()->first();
            if ($booking) {
                $booking->update([
                    'paid_amount' => $booking->total_amount,
                    'remaining_amount' => 0,
                ]);
            }
        }
    }

    /**
     * @return array{
     *   rentals: array<string, array<int, array{id: int, quantity: int}>>,
     *   studio_meta: array<string, mixed>|null
     * }
     */
    protected function bundleFromCart(int $userId): array
    {
        $cart = $this->cartService->listForUser($userId);
        $items = [];
        $studioMeta = null;

        foreach ($cart as $item) {
            /** @var CartItem $item */
            if ($item->item_type === CartItem::TYPE_STUDIO) {
                $studioMeta = array_merge((array) ($item->meta ?? []), [
                    'studio_id' => (int) $item->item_id,
                ]);
                continue;
            }

            $items[] = [
                'type' => $item->item_type,
                'id' => (int) $item->item_id,
                'quantity' => (int) $item->quantity,
            ];
        }

        return [
            'rentals' => $this->normalizeItems($items),
            'studio_meta' => $studioMeta,
        ];
    }

    /**
     * @param  array<int, array{type?: string, id: int, quantity: int}>  $items
     * @return array{
     *   accessories: array<int, array{id: int, quantity: int}>,
     *   equipment: array<int, array{id: int, quantity: int}>,
     *   portable_bathrooms: array<int, array{id: int, quantity: int}>,
     *   caravans: array<int, array{id: int, quantity: int}>,
     *   generators: array<int, array{id: int, quantity: int}>
     * }
     */
    protected function normalizeItems(array $items): array
    {
        $out = [
            'accessories' => [],
            'equipment' => [],
            'portable_bathrooms' => [],
            'caravans' => [],
            'generators' => [],
        ];

        foreach ($items as $item) {
            $type = $item['type'] ?? null;
            $row = ['id' => (int) $item['id'], 'quantity' => (int) $item['quantity']];

            match ($type) {
                CartItem::TYPE_ACCESSORY, 'accessory' => $out['accessories'][] = $row,
                CartItem::TYPE_EQUIPMENT, 'equipment' => $out['equipment'][] = $row,
                CartItem::TYPE_PORTABLE_BATHROOM, DayCatalog::PORTABLE_BATHROOM => $out['portable_bathrooms'][] = $row,
                CartItem::TYPE_CARAVAN, DayCatalog::CARAVAN => $out['caravans'][] = $row,
                CartItem::TYPE_GENERATOR, DayCatalog::GENERATOR => $out['generators'][] = $row,
                CartItem::TYPE_STUDIO, 'studio' => null,
                default => throw new BusinessException('Invalid item type.', 'invalid_item_type'),
            };
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, array{id: int, quantity: int}>>  $normalized
     */
    protected function hasAnyRentals(array $normalized): bool
    {
        foreach ($normalized as $list) {
            if ($list !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $studioMeta
     * @return array{0: string, 1: string}
     */
    protected function resolveDatesFromStudioMeta(array $studioMeta, ?string $startDate, ?string $endDate): array
    {
        if ($startDate && $endDate) {
            return [$startDate, $endDate];
        }

        $mode = BookingMode::tryFrom((string) ($studioMeta['booking_mode'] ?? 'hourly')) ?? BookingMode::Hourly;

        if ($mode === BookingMode::Daily) {
            return [
                (string) ($studioMeta['start_date'] ?? $startDate),
                (string) ($studioMeta['end_date'] ?? $endDate ?? $studioMeta['start_date'] ?? $startDate),
            ];
        }

        $startAt = Carbon::parse((string) ($studioMeta['start_at'] ?? now()));
        $endAt = Carbon::parse((string) ($studioMeta['end_at'] ?? $startAt));

        return [$startAt->toDateString(), $endAt->copy()->subSecond()->toDateString()];
    }

    /**
     * @param  array<string, mixed>  $studioMeta
     * @return array<string, mixed>
     */
    protected function quoteStudioPortion(?int $userId, array $studioMeta): array
    {
        if (! $userId) {
            throw new BusinessException('Studio quote requires an authenticated user.', 'unauthenticated', 401);
        }

        $mode = BookingMode::tryFrom((string) ($studioMeta['booking_mode'] ?? 'hourly')) ?? BookingMode::Hourly;
        $guestCount = (int) ($studioMeta['guest_count'] ?? 1);
        $packageId = isset($studioMeta['package_id']) ? (int) $studioMeta['package_id'] : null;
        $holdId = isset($studioMeta['hold_id']) ? (int) $studioMeta['hold_id'] : null;

        return $this->bookingService->createQuote(
            $userId,
            (int) $studioMeta['studio_id'],
            $mode === BookingMode::Hourly ? Carbon::parse((string) $studioMeta['start_at']) : null,
            $mode === BookingMode::Hourly ? Carbon::parse((string) $studioMeta['end_at']) : null,
            $guestCount,
            [],
            [],
            $packageId,
            null,
            null,
            $mode,
            $mode === BookingMode::Daily ? (string) ($studioMeta['start_date'] ?? '') : null,
            $mode === BookingMode::Daily ? (string) ($studioMeta['end_date'] ?? '') : null,
            $holdId,
            [],
            false,
            null,
        );
    }

    /**
     * @param  array<string, mixed>  $studioMeta
     */
    protected function createBookingFromStudioMeta(int $userId, array $studioMeta, ?string $customerNotes)
    {
        $holdId = isset($studioMeta['hold_id']) ? (int) $studioMeta['hold_id'] : null;

        if (! $holdId) {
            throw new BusinessException('Studio cart line is missing an active hold. Re-add the studio to the cart.', 'studio_hold_required');
        }

        return $this->bookingService->createFromHold(
            $holdId,
            $userId,
            (int) ($studioMeta['guest_count'] ?? 1),
            [],
            [],
            isset($studioMeta['package_id']) ? (int) $studioMeta['package_id'] : null,
            null,
            null,
            $customerNotes,
            [],
            false,
            null,
        );
    }

    /**
     * @param  array<string, array<int, array{id: int, quantity: int}>>  $normalized
     */
    protected function validateRentalStock(array $normalized, Carbon $periodStart, Carbon $periodEnd, ?int $excludeOrderId): void
    {
        if ($normalized['accessories'] !== []) {
            $this->accessoryInventory->validateAvailability($normalized['accessories'], $periodStart, $periodEnd, null, $excludeOrderId);
        }
        if ($normalized['equipment'] !== []) {
            $this->equipmentInventory->validateAvailability($normalized['equipment'], $periodStart, $periodEnd, $excludeOrderId);
        }
        foreach ([
            DayCatalog::PORTABLE_BATHROOM => $normalized['portable_bathrooms'],
            DayCatalog::CARAVAN => $normalized['caravans'],
            DayCatalog::GENERATOR => $normalized['generators'],
        ] as $type => $list) {
            if ($list !== []) {
                $this->dayCatalogInventory->validateAvailability($type, $list, $periodStart, $periodEnd, $excludeOrderId);
            }
        }
    }

    /**
     * @param  array<string, array<int, array{id: int, quantity: int}>>  $normalized
     * @return list<array{lines: list<array<string, mixed>>, subtotal: float}>
     */
    protected function priceRentals(array $normalized, int $days): array
    {
        $priced = [];

        if ($normalized['accessories'] !== []) {
            $priced[] = $this->accessoryPricing->buildLines($normalized['accessories'], $days);
        }
        if ($normalized['equipment'] !== []) {
            $priced[] = $this->equipmentPricing->buildLines($normalized['equipment'], $days);
        }
        foreach ([
            DayCatalog::PORTABLE_BATHROOM => $normalized['portable_bathrooms'],
            DayCatalog::CARAVAN => $normalized['caravans'],
            DayCatalog::GENERATOR => $normalized['generators'],
        ] as $type => $list) {
            if ($list !== []) {
                $priced[] = $this->dayCatalogPricing->buildLines($type, $list, $days);
            }
        }

        return $priced;
    }

    /**
     * @param  array<string, array<int, array{id: int, quantity: int}>>  $normalized
     */
    protected function lockRentalRows(array $normalized): void
    {
        $map = [
            'accessories' => [Accessory::class, 'id'],
            'equipment' => [Equipment::class, 'id'],
            'portable_bathrooms' => [PortableBathroom::class, 'id'],
            'caravans' => [Caravan::class, 'id'],
            'generators' => [Generator::class, 'id'],
        ];

        foreach ($map as $key => [$class, $idKey]) {
            $ids = array_keys($this->accessoryInventory->groupItems($normalized[$key]));
            if ($ids !== []) {
                $class::query()->whereIn($idKey, $ids)->lockForUpdate()->get();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function persistRentalLines(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            $type = $line['item_type'] ?? '';

            if ($type === 'accessory') {
                $payload = [
                    'accessory_id' => $line['item_id'],
                    'order_id' => $order->id,
                    'quantity' => $line['quantity'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ];
                if (Schema::hasColumn('accessory_rental_items', 'rental_order_id')) {
                    $payload['rental_order_id'] = $order->id;
                }
                $order->accessoryItems()->create($payload);
            } elseif ($type === 'equipment') {
                $payload = [
                    'equipment_id' => $line['item_id'],
                    'order_id' => $order->id,
                    'quantity' => $line['quantity'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ];
                if (Schema::hasColumn('equipment_rental_items', 'rental_order_id')) {
                    $payload['rental_order_id'] = $order->id;
                }
                $order->equipmentItems()->create($payload);
            } elseif ($type === DayCatalog::PORTABLE_BATHROOM) {
                $order->portableBathroomItems()->create([
                    'portable_bathroom_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ]);
            } elseif ($type === DayCatalog::CARAVAN) {
                $order->caravanItems()->create([
                    'caravan_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ]);
            } elseif ($type === DayCatalog::GENERATOR) {
                $order->generatorItems()->create([
                    'generator_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $items
     * @return list<array{type: string, id: int, quantity: int}>
     */
    protected function flattenTyped(array $items, string $type): array
    {
        return array_map(fn ($i) => ['type' => $type, 'id' => $i['id'], 'quantity' => $i['quantity']], $items);
    }

    protected function revalidateStock(Order $order): void
    {
        if (! $order->start_date || ! $order->end_date) {
            return;
        }

        [$periodStart, $periodEnd] = $this->accessoryInventory->rentalPeriod(
            Carbon::parse($order->start_date)->toDateString(),
            Carbon::parse($order->end_date)->toDateString(),
        );

        $normalized = [
            'accessories' => $order->accessoryItems()->get(['accessory_id', 'quantity'])
                ->map(fn ($i) => ['id' => (int) $i->accessory_id, 'quantity' => (int) $i->quantity])->all(),
            'equipment' => $order->equipmentItems()->get(['equipment_id', 'quantity'])
                ->map(fn ($i) => ['id' => (int) $i->equipment_id, 'quantity' => (int) $i->quantity])->all(),
            'portable_bathrooms' => $order->portableBathroomItems()->get(['portable_bathroom_id', 'quantity'])
                ->map(fn ($i) => ['id' => (int) $i->portable_bathroom_id, 'quantity' => (int) $i->quantity])->all(),
            'caravans' => $order->caravanItems()->get(['caravan_id', 'quantity'])
                ->map(fn ($i) => ['id' => (int) $i->caravan_id, 'quantity' => (int) $i->quantity])->all(),
            'generators' => $order->generatorItems()->get(['generator_id', 'quantity'])
                ->map(fn ($i) => ['id' => (int) $i->generator_id, 'quantity' => (int) $i->quantity])->all(),
        ];

        $this->validateRentalStock($normalized, $periodStart, $periodEnd, $order->id);
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

    protected function notifyAdminsOfNewOrder(Order $order): void
    {
        try {
            $customer = $order->user()->first(['id', 'name', 'phone']);
            $itemsCount = (int) $order->accessoryItems->sum('quantity')
                + (int) $order->equipmentItems->sum('quantity')
                + (int) $order->portableBathroomItems->sum('quantity')
                + (int) $order->caravanItems->sum('quantity')
                + (int) $order->generatorItems->sum('quantity')
                + ($order->booking_id ? 1 : 0);

            $this->adminNotifications->notifyAdmins(
                'order.created',
                'New order',
                sprintf(
                    '%s placed order %s (%d item%s, %s EGP)%s.',
                    $customer?->name ?? 'A customer',
                    $order->order_number,
                    $itemsCount,
                    $itemsCount === 1 ? '' : 's',
                    number_format((float) $order->total_amount, 2),
                    $order->start_date && $order->end_date
                        ? sprintf(' from %s to %s', $order->start_date->toDateString(), $order->end_date->toDateString())
                        : '',
                ),
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'booking_id' => $order->booking_id,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'customer_phone' => $customer?->phone,
                    'items_count' => $itemsCount,
                    'total_amount' => (float) $order->total_amount,
                    'is_paid' => $order->is_paid,
                    'payment_status' => $order->payment_status,
                ],
                null,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return list<string> */
    protected function defaultWith(): array
    {
        return [
            'accessoryItems.accessory.images',
            'equipmentItems.equipment.images',
            'portableBathroomItems.portableBathroom.images',
            'caravanItems.caravan.images',
            'generatorItems.generator.images',
            'payments',
            'booking',
            'paintColor',
        ];
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->orderRepository->existsByOrderNumber($number));

        return $number;
    }
}
