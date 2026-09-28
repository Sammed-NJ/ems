<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBookingConfirmation implements ShouldQueue
{
    public $queue = 'emails';

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public $deleteWhenMissingModels = true;

    public function handle(BookingConfirmed $event): void
    {
        $event->booking->user->notify(new BookingConfirmedNotification($event->booking));
    }

    public function failed(BookingConfirmed $event, Throwable $exception): void
    {
        Log::error('Booking confirmation email failed', ['booking_id' => $event->booking->id, 'error' => $exception->getMessage()]);
    }
}
