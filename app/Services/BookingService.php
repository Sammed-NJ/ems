<?php

namespace App\Services;

use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    // max tickets one user can hold for a single event
    public const MAX_TICKETS_PER_EVENT = 5;

    // no cancellations this close to the event start
    public const CANCELLATION_CUTOFF_HOURS = 24;

    public function book(User $user, int $ticketTypeId, int $quantity): Booking
    {
        // everything below commits together or rolls back together
        return DB::transaction(function () use ($user, $ticketTypeId, $quantity) {
            // find which event this ticket type belongs to
            $ticketType = TicketType::findOrFail($ticketTypeId);

            // lock event + ticket type so parallel bookings run one by one
            $event = Event::whereKey($ticketType->event_id)->lockForUpdate()->firstOrFail();
            $ticketType = TicketType::whereKey($ticketTypeId)->lockForUpdate()->firstOrFail();

            // only published events that haven't started can be booked
            if ($event->status !== 'published' || $event->starts_at->isPast()) {
                $this->fail('ticket_type_id', 'This event is not open for booking.');
            }

            // stop overselling
            if ($ticketType->seatsRemaining() < $quantity) {
                $this->fail('quantity', "Only {$ticketType->seatsRemaining()} seats remaining for this ticket type.");
            }

            // tickets this user already holds for this event, across all ticket types
            $alreadyBooked = $user->bookings()
                ->where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->sum('quantity');

            // enforce the per-user limit
            if ($alreadyBooked + $quantity > self::MAX_TICKETS_PER_EVENT) {
                $this->fail('quantity', 'You can book at most '.self::MAX_TICKETS_PER_EVENT." tickets per event. You already have {$alreadyBooked}.");
            }

            // reserve the seats
            $ticketType->increment('sold', $quantity);

            // save the booking, price taken from the database not the request
            $booking = $user->bookings()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantity,
                'unit_price' => $ticketType->price,
                'total_amount' => round($ticketType->price * $quantity, 2),
                'status' => 'confirmed',
            ]);

            // fired only after commit, second arg tells if this took the last seat
            BookingConfirmed::dispatch($booking, $ticketType->seatsRemaining() === 0);

            return $booking;
        });
    }

    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            // lock the booking so it can't be cancelled twice at the same time
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'confirmed') {
                $this->fail('booking', 'This booking is already cancelled.');
            }

            // must be more than 24 hours before the event
            if ($booking->event->starts_at->lte(now()->addHours(self::CANCELLATION_CUTOFF_HOURS))) {
                $this->fail('booking', 'Bookings can only be cancelled up to '.self::CANCELLATION_CUTOFF_HOURS.' hours before the event starts.');
            }

            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            // give the seats back right here, not in a queued listener
            TicketType::whereKey($booking->ticket_type_id)->decrement('sold', $booking->quantity);

            // fired only after commit, queues the cancellation email
            BookingCancelled::dispatch($booking);

            return $booking;
        });
    }

    // throw a 422 with the message under the given field
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
