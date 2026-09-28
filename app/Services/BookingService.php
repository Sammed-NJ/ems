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
    public const MAX_TICKETS_PER_EVENT = 5;

    public const CANCELLATION_CUTOFF_HOURS = 24;

    public function book(User $user, int $ticketTypeId, int $quantity): Booking
    {
        return DB::transaction(function () use ($user, $ticketTypeId, $quantity) {
            $ticketType = TicketType::findOrFail($ticketTypeId);

            // lock event + ticket type so parallel bookings run one by one
            $event = Event::whereKey($ticketType->event_id)->lockForUpdate()->firstOrFail();
            $ticketType = TicketType::whereKey($ticketTypeId)->lockForUpdate()->firstOrFail();

            if ($event->status !== 'published' || $event->starts_at->isPast()) {
                $this->fail('ticket_type_id', 'This event is not open for booking.');
            }

            if ($ticketType->seatsRemaining() < $quantity) {
                $this->fail('quantity', "Only {$ticketType->seatsRemaining()} seats remaining for this ticket type.");
            }

            $alreadyBooked = $user->bookings()
                ->where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->sum('quantity');

            if ($alreadyBooked + $quantity > self::MAX_TICKETS_PER_EVENT) {
                $this->fail('quantity', 'You can book at most '.self::MAX_TICKETS_PER_EVENT." tickets per event. You already have {$alreadyBooked}.");
            }

            $ticketType->increment('sold', $quantity);

            $booking = $user->bookings()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantity,
                'unit_price' => $ticketType->price,
                'total_amount' => round($ticketType->price * $quantity, 2),
                'status' => 'confirmed',
            ]);

            BookingConfirmed::dispatch($booking, $ticketType->seatsRemaining() === 0);

            return $booking;
        });
    }

    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'confirmed') {
                $this->fail('booking', 'This booking is already cancelled.');
            }

            if ($booking->event->starts_at->lte(now()->addHours(self::CANCELLATION_CUTOFF_HOURS))) {
                $this->fail('booking', 'Bookings can only be cancelled up to '.self::CANCELLATION_CUTOFF_HOURS.' hours before the event starts.');
            }

            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            TicketType::whereKey($booking->ticket_type_id)->decrement('sold', $booking->quantity);

            BookingCancelled::dispatch($booking);

            return $booking;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
