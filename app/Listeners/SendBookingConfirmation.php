<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBookingConfirmation implements ShouldQueue
{
    // email work goes on the emails queue
    public $queue = 'emails';

    // retry up to 3 times, waiting 10s, 30s, 60s
    public $tries = 3;

    public $backoff = [10, 30, 60];

    // booking deleted before this runs -> drop the job instead of retrying forever
    public $deleteWhenMissingModels = true;

    // email the attendee
    public function handle(BookingConfirmed $event): void
    {
        $event->booking->user->notify(new BookingConfirmedNotification($event->booking));
    }

    // runs after all retries fail
    public function failed(BookingConfirmed $event, Throwable $exception): void
    {
        Log::error('Booking confirmation email failed', ['booking_id' => $event->booking->id, 'error' => $exception->getMessage()]);
    }
}
