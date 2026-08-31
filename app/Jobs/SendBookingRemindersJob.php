<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendBookingRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notificationService): void
    {
        $windowStart = Carbon::now()->addHours(23);
        $windowEnd = Carbon::now()->addHours(25);

        $bookings = DB::table('bookings')
            ->where('status', BookingStatus::Confirmed->value)
            ->whereBetween('start_at', [$windowStart, $windowEnd])
            ->get(['id']);

        foreach ($bookings as $booking) {
            $alreadySent = DB::table('notifications')
                ->where('type', 'booking_reminder')
                ->where('deep_link', 'bookings/'.$booking->id)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            try {
                $notificationService->sendBookingReminder((int) $booking->id);
            } catch (\Throwable $exception) {
                Log::warning('Failed to send booking reminder', [
                    'booking_id' => $booking->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
