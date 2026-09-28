<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Notifications\EventReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEventReminder implements ShouldQueue
{
    use Queueable;

    // retry up to 3 times
    public $tries = 3;

    // wait 10s, 30s, 60s between retries
    public $backoff = [10, 30, 60];

    // store only the id, so a deleted booking can't break the job
    public function __construct(public int $bookingId)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        // mark as sent first so a duplicate job does nothing
        $claimed = Booking::whereKey($this->bookingId)
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]);

        // 0 rows = already reminded, cancelled or deleted
        if ($claimed === 0) {
            return;
        }

        $booking = Booking::with(['user', 'event'])->find($this->bookingId);

        try {
            $booking->user->notify(new EventReminderNotification($booking));
        } catch (Throwable $e) {
            // undo the mark so the retry can send it
            Booking::whereKey($this->bookingId)->update(['reminder_sent_at' => null]);

            throw $e;
        }
    }

    // runs after all retries fail
    public function failed(Throwable $exception): void
    {
        Log::error('Event reminder failed', ['booking_id' => $this->bookingId, 'error' => $exception->getMessage()]);
    }
}
