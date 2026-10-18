<?php

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BusinessException;
use App\Models\AccessoryRental;
use App\Models\Booking;
use App\Models\EquipmentRental;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Accessory\AccessoryRentalService;
use App\Services\Booking\BookingService;
use App\Services\Equipment\EquipmentRentalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected PaymentRepositoryInterface $paymentRepository,
        protected BookingRepositoryInterface $bookingRepository,
        protected PaymobGateway $paymobGateway,
        protected BookingService $bookingService,
        protected AccessoryRentalService $accessoryRentalService,
        protected EquipmentRentalService $equipmentRentalService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $billingData
     * @return array<string, mixed>
     */
    public function initiate(
        int $bookingId,
        PaymentType $type,
        ?float $amount = null,
        array $billingData = [],
    ): array {
        return DB::transaction(function () use ($bookingId, $type, $amount, $billingData) {
            $booking = $this->bookingRepository->findOrFail($bookingId);
            $payAmount = $amount ?? (float) $booking->remaining_amount;

            if ($payAmount <= 0) {
                throw new BusinessException('No amount due for this booking.', 'nothing_to_pay');
            }

            $payment = $this->paymentRepository->create([
                'booking_id' => $bookingId,
                'type' => $type->value,
                'amount' => $payAmount,
                'status' => PaymentStatus::Pending->value,
                'provider' => 'paymob',
            ]);

            $gatewayResponse = $this->paymobGateway->initiatePayment(
                (int) round($payAmount * 100),
                "booking-{$bookingId}-payment-{$payment->id}",
                $billingData,
            );

            $this->paymentRepository->update($payment->id, [
                'provider_reference' => (string) $gatewayResponse['order_id'],
            ]);

            return array_merge($gatewayResponse, [
                'payment_id' => $payment->id,
                'amount' => $payAmount,
            ]);
        });
    }

    /**
     * Records cash collected by an admin for a booking. Defaults to the full remaining amount.
     */
    public function recordCashPayment(int $bookingId, ?float $amount = null): Model
    {
        return DB::transaction(function () use ($bookingId, $amount) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->status === BookingStatus::Cancelled) {
                throw new BusinessException('Cannot record a payment for a cancelled booking.', 'booking_cancelled');
            }

            $remaining = (float) $booking->remaining_amount;
            $payAmount = round($amount ?? $remaining, 2);

            if ($remaining <= 0 || $payAmount <= 0) {
                throw new BusinessException('No amount due for this booking.', 'nothing_to_pay');
            }

            if ($payAmount > $remaining) {
                throw new BusinessException('Amount exceeds the remaining balance.', 'amount_exceeds_remaining');
            }

            $this->paymentRepository->create([
                'booking_id' => $booking->id,
                'type' => ((float) $booking->paid_amount <= 0 && $payAmount >= $remaining
                    ? PaymentType::Full
                    : PaymentType::Balance)->value,
                'amount' => $payAmount,
                'status' => PaymentStatus::Paid->value,
                'provider' => 'cash',
                'paid_at' => now(),
            ]);

            $paidAmount = round((float) $booking->paid_amount + $payAmount, 2);

            return $this->bookingRepository->update($booking->id, [
                'paid_amount' => $paidAmount,
                'remaining_amount' => max(0, round((float) $booking->total_amount - $paidAmount, 2)),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, string $hmac): array
    {
        if (! $this->paymobGateway->verifyWebhookSignature($payload, $hmac)) {
            throw new BusinessException('Invalid webhook signature.', 'invalid_webhook_signature', 403);
        }

        $parsed = $this->paymobGateway->parseWebhook($payload);
        $providerReference = $parsed['transaction_id'];

        $existing = DB::table('payments')
            ->where('provider_reference', $providerReference)
            ->where('status', PaymentStatus::Paid->value)
            ->first();

        if ($existing) {
            return ['status' => 'already_processed', 'payment_id' => (int) $existing->id];
        }

        return DB::transaction(function () use ($parsed, $providerReference) {
            $payment = DB::table('payments')
                ->where('provider_reference', $parsed['order_id'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new BusinessException('Payment record not found.', 'payment_not_found', 404);
            }

            if ($payment->status === PaymentStatus::Paid->value) {
                return ['status' => 'already_processed', 'payment_id' => (int) $payment->id];
            }

            if (! $parsed['success'] || $parsed['pending']) {
                $this->paymentRepository->update((int) $payment->id, [
                    'status' => PaymentStatus::Failed->value,
                    'provider_reference' => $providerReference,
                ]);

                return ['status' => 'failed', 'payment_id' => (int) $payment->id];
            }

            $this->paymentRepository->update((int) $payment->id, [
                'status' => PaymentStatus::Paid->value,
                'provider_reference' => $providerReference,
                'paid_at' => now(),
            ]);

            if ($payment->accessory_rental_id) {
                $rental = AccessoryRental::query()->lockForUpdate()->findOrFail((int) $payment->accessory_rental_id);
                $this->accessoryRentalService->applyPayment($rental, (float) $payment->amount);

                return ['status' => 'processed', 'payment_id' => (int) $payment->id];
            }

            if ($payment->equipment_rental_id) {
                $rental = EquipmentRental::query()->lockForUpdate()->findOrFail((int) $payment->equipment_rental_id);
                $this->equipmentRentalService->applyPayment($rental, (float) $payment->amount);

                return ['status' => 'processed', 'payment_id' => (int) $payment->id];
            }

            $booking = $this->bookingRepository->findOrFail((int) $payment->booking_id);
            $paidAmount = round((float) $booking->paid_amount + (float) $payment->amount, 2);
            $remainingAmount = max(0, round((float) $booking->total_amount - $paidAmount, 2));

            $this->bookingRepository->update($booking->id, [
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
            ]);

            if ($booking->status === BookingStatus::Pending && $remainingAmount <= 0) {
                $this->bookingService->confirmBooking($booking->id);
            }

            return ['status' => 'processed', 'payment_id' => (int) $payment->id];
        });
    }

    public function getPaymentStatus(int $paymentId): Model
    {
        return $this->paymentRepository->findOrFail($paymentId);
    }
}
