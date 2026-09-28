<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateEventSalesStats implements ShouldQueue
{
    // report work goes on the reports queue
    public $queue = 'reports';

    public $tries = 3;

    public $backoff = [10, 30, 60];

    // booking deleted before this runs -> drop the job quietly
    public $deleteWhenMissingModels = true;

    // add sold tickets and revenue to the event in one atomic UPDATE
    public function handle(BookingConfirmed $event): void
    {
        Event::whereKey($event->booking->event_id)->incrementEach([
            'tickets_sold' => $event->booking->quantity,
            'revenue' => $event->booking->total_amount,
        ]);
    }

    // subtract them again when a booking is cancelled
    public function handleCancelled(BookingCancelled $event): void
    {
        Event::whereKey($event->booking->event_id)->decrementEach([
            'tickets_sold' => $event->booking->quantity,
            'revenue' => $event->booking->total_amount,
        ]);
    }

    // runs after all retries fail
    public function failed(BookingConfirmed|BookingCancelled $event, Throwable $exception): void
    {
        Log::error('Updating event sales stats failed', ['booking_id' => $event->booking->id, 'error' => $exception->getMessage()]);
    }
}
