<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Notifications\BookingCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBookingCancellation implements ShouldQueue
{
    public $queue = 'emails';

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public $deleteWhenMissingModels = true;

    // email the attendee
    public function handle(BookingCancelled $event): void
    {
        $event->booking->user->notify(new BookingCancelledNotification($event->booking));
    }

    // runs after all retries fail
    public function failed(BookingCancelled $event, Throwable $exception): void
    {
        Log::error('Booking cancellation email failed', ['booking_id' => $event->booking->id, 'error' => $exception->getMessage()]);
    }
}
