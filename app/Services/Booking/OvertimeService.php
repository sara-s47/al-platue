<?php

namespace App\Services\Booking;

use App\Enums\BookingChangeType;
use App\Enums\OvertimeStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Content\AppSettingsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected AppSettingsService $appSettings,
    ) {
    }

    /**
     * @return array{overtime_minutes: int, grace_minutes: int, interval_minutes: int, rate: float, amount: float}
     */
    public function calculateOvertime(Model $booking, ?Carbon $actualEndAt = null): array
    {
        $scheduledEnd = Carbon::parse($booking->end_at);
        $actualEnd = $actualEndAt ?? Carbon::now();
        $graceMinutes = (int) $this->appSettings->get('overtime_grace_minutes', 15);
        $intervalMinutes = (int) $this->appSettings->get('overtime_interval_minutes', 15);
        $rate = (float) $this->appSettings->get('overtime_rate', 0);

        $overtimeStart = $scheduledEnd->copy()->addMinutes($graceMinutes);
        $overtimeMinutes = max(0, (int) $overtimeStart->diffInMinutes($actualEnd, false));

        if ($overtimeMinutes <= 0) {
            return [
                'overtime_minutes' => 0,
                'grace_minutes' => $graceMinutes,
                'interval_minutes' => $intervalMinutes,
                'rate' => $rate,
                'amount' => 0,
            ];
        }

        $billableIntervals = (int) ceil($overtimeMinutes / $intervalMinutes);
        $amount = round($billableIntervals * $rate, 2);

        return [
            'overtime_minutes' => $overtimeMinutes,
            'grace_minutes' => $graceMinutes,
            'interval_minutes' => $intervalMinutes,
            'rate' => $rate,
            'amount' => $amount,
        ];
    }

    public function recordOvertime(int $bookingId, ?Carbon $actualEndAt = null): Model
    {
        return DB::transaction(function () use ($bookingId, $actualEndAt) {
            $booking = $this->bookingRepository->findOrFail($bookingId);
            $calculation = $this->calculateOvertime($booking, $actualEndAt);

            if ($calculation['overtime_minutes'] <= 0) {
                throw new BusinessException('No overtime to record.', 'no_overtime');
            }

            $existing = DB::table('overtime_records')
                ->where('booking_id', $bookingId)
                ->where('status', '!=', OvertimeStatus::Paid->value)
                ->first();

            if ($existing) {
                throw new BusinessException('Outstanding overtime record already exists.', 'overtime_exists');
            }

            $id = DB::table('overtime_records')->insertGetId([
                'booking_id' => $bookingId,
                'grace_minutes' => $calculation['grace_minutes'],
                'overtime_minutes' => $calculation['overtime_minutes'],
                'interval_minutes' => $calculation['interval_minutes'],
                'rate' => $calculation['rate'],
                'amount' => $calculation['amount'],
                'status' => OvertimeStatus::Pending->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('booking_changes')->insert([
                'booking_id' => $bookingId,
                'type' => BookingChangeType::Overtime->value,
                'price_difference' => $calculation['amount'],
                'created_at' => now(),
            ]);

            $this->bookingRepository->update($bookingId, [
                'remaining_amount' => round((float) $booking->remaining_amount + $calculation['amount'], 2),
            ]);

            return $this->bookingRepository->findOrFail($bookingId);
        });
    }
}
