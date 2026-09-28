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

    public $tries = 3;

    public $backoff = [10, 30, 60];

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

        if ($claimed === 0) {
            return;
        }

        $booking = Booking::with(['user', 'event'])->find($this->bookingId);

        try {
            $booking->user->notify(new EventReminderNotification($booking));
        } catch (Throwable $e) {
            Booking::whereKey($this->bookingId)->update(['reminder_sent_at' => null]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Event reminder failed', ['booking_id' => $this->bookingId, 'error' => $exception->getMessage()]);
    }
}
