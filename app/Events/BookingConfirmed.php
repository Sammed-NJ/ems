<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// ShouldDispatchAfterCommit: held until the transaction commits, dropped if it rolls back
class BookingConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public bool $ticketTypeSoldOut,
    ) {}
}
