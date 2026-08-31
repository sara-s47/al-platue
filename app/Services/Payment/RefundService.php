<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        protected RefundRepositoryInterface $refundRepository,
        protected PaymentRepositoryInterface $paymentRepository,
        protected BookingRepositoryInterface $bookingRepository,
        protected PaymobGateway $paymobGateway,
    ) {
    }

    public function processRefund(
        int $paymentId,
        float $amount,
        string $reason,
        ?int $processedBy = null,
    ): Model {
        return DB::transaction(function () use ($paymentId, $amount, $reason, $processedBy) {
            $payment = $this->paymentRepository->findOrFail($paymentId);

            if (! in_array($payment->status, [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
            ], true)) {
                throw new BusinessException('Payment is not eligible for refund.', 'payment_not_refundable');
            }

            $existingRefunds = (float) DB::table('refunds')
                ->where('payment_id', $paymentId)
                ->where('status', RefundStatus::Processed->value)
                ->sum('amount');

            $refundable = (float) $payment->amount - $existingRefunds;

            if ($amount <= 0 || $amount > $refundable) {
                throw new BusinessException(
                    "Refund amount must be between 0 and {$refundable}.",
                    'invalid_refund_amount',
                );
            }

            $refund = $this->refundRepository->create([
                'payment_id' => $paymentId,
                'booking_id' => $payment->booking_id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => RefundStatus::Pending->value,
            ]);

            $providerReference = $this->processProviderRefund($payment, $amount);

            $refund = $this->refundRepository->update($refund->id, [
                'status' => RefundStatus::Processed->value,
                'provider_reference' => $providerReference,
                'processed_at' => now(),
            ]);

            $newRefundedTotal = $existingRefunds + $amount;
            $paymentStatus = $newRefundedTotal >= (float) $payment->amount
                ? PaymentStatus::Refunded->value
                : PaymentStatus::PartiallyRefunded->value;

            $this->paymentRepository->update($paymentId, ['status' => $paymentStatus]);

            $booking = $this->bookingRepository->findOrFail((int) $payment->booking_id);
            $this->bookingRepository->update($booking->id, [
                'paid_amount' => max(0, round((float) $booking->paid_amount - $amount, 2)),
                'remaining_amount' => round((float) $booking->remaining_amount + $amount, 2),
            ]);

            return $refund;
        });
    }

    protected function processProviderRefund(Model $payment, float $amount): string
    {
        return 'refund-' . $payment->id . '-' . time();
    }
}
