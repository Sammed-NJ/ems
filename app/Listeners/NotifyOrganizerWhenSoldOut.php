<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Notifications\TicketTypeSoldOutNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOrganizerWhenSoldOut implements ShouldQueue
{
    public $queue = 'emails';

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public $deleteWhenMissingModels = true;

    // only queue it when this booking took the last seat
    public function shouldQueue(BookingConfirmed $event): bool
    {
        return $event->ticketTypeSoldOut;
    }

    // email the organizer of the event
    public function handle(BookingConfirmed $event): void
    {
        $ticketType = $event->booking->ticketType;

        $ticketType->event->organizer->notify(new TicketTypeSoldOutNotification($ticketType));
    }

    public function failed(BookingConfirmed $event, Throwable $exception): void
    {
        Log::error('Sold-out email failed', ['booking_id' => $event->booking->id, 'error' => $exception->getMessage()]);
    }
}
