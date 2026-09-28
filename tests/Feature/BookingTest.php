<?php

namespace Tests\Feature;

use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\TicketTypeSoldOutNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $user, TicketType $ticketType, int $quantity)
    {
        return $this->actingAs($user)->postJson('/api/bookings', [
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
        ]);
    }

    public function test_attendee_can_book_and_total_is_calculated_on_the_server(): void
    {
        Notification::fake();
        $ticketType = TicketType::factory()->create(['price' => 150, 'quantity' => 10]);
        $attendee = User::factory()->create();

        $this->book($attendee, $ticketType, 3)
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '450.00');

        $this->assertSame(3, $ticketType->fresh()->sold);
        $this->assertSame(3, $ticketType->event->fresh()->tickets_sold);
        Notification::assertSentTo($attendee, BookingConfirmedNotification::class);
    }

    public function test_cannot_book_more_than_remaining_seats(): void
    {
        $ticketType = TicketType::factory()->create(['quantity' => 2]);

        $this->book(User::factory()->create(), $ticketType, 3)->assertUnprocessable();
        $this->assertSame(0, $ticketType->fresh()->sold);
    }

    public function test_max_five_tickets_per_user_per_event_across_bookings(): void
    {
        $ticketType = TicketType::factory()->create();
        $attendee = User::factory()->create();

        $this->book($attendee, $ticketType, 4)->assertCreated();
        $this->book($attendee, $ticketType, 2)->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_cannot_book_draft_or_past_events(): void
    {
        $attendee = User::factory()->create();
        $draft = TicketType::factory()->for(Event::factory()->draft())->create();
        $past = TicketType::factory()->for(Event::factory()->state(['starts_at' => now()->subDay()]))->create();

        $this->book($attendee, $draft, 1)->assertUnprocessable();
        $this->book($attendee, $past, 1)->assertUnprocessable();
    }

    public function test_organizer_is_notified_only_when_ticket_type_sells_out(): void
    {
        Notification::fake();
        $ticketType = TicketType::factory()->create(['quantity' => 3]);
        $organizer = $ticketType->event->organizer;

        $this->book(User::factory()->create(), $ticketType, 2)->assertCreated();
        Notification::assertNotSentTo($organizer, TicketTypeSoldOutNotification::class);

        $this->book(User::factory()->create(), $ticketType, 1)->assertCreated();
        Notification::assertSentTo($organizer, TicketTypeSoldOutNotification::class);
    }

    public function test_no_listener_runs_when_the_booking_transaction_rolls_back(): void
    {
        \Illuminate\Support\Facades\Event::fake([BookingConfirmed::class]);
        $ticketType = TicketType::factory()->create();

        try {
            DB::transaction(function () use ($ticketType) {
                app(\App\Services\BookingService::class)->book(User::factory()->create(), $ticketType->id, 1);
                throw new \RuntimeException('Something failed after the booking was saved');
            });
        } catch (\RuntimeException) {
        }

        \Illuminate\Support\Facades\Event::assertNotDispatched(BookingConfirmed::class);
        $this->assertSame(0, Booking::count());
    }

    public function test_attendee_can_cancel_own_booking_and_seats_are_released(): void
    {
        $ticketType = TicketType::factory()->create();
        $attendee = User::factory()->create();
        $bookingId = $this->book($attendee, $ticketType, 2)->json('data.id');

        $this->actingAs($attendee)->postJson("/api/bookings/{$bookingId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, $ticketType->fresh()->sold);
    }

    public function test_cannot_cancel_within_24_hours_of_the_event(): void
    {
        $ticketType = TicketType::factory()->for(Event::factory()->state(['starts_at' => now()->addHours(10)]))->create();
        $attendee = User::factory()->create();
        $bookingId = $this->book($attendee, $ticketType, 1)->json('data.id');

        $this->actingAs($attendee)->postJson("/api/bookings/{$bookingId}/cancel")->assertUnprocessable();
        $this->assertSame(1, $ticketType->fresh()->sold);
    }

    public function test_cannot_cancel_someone_elses_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs(User::factory()->create())->postJson("/api/bookings/{$booking->id}/cancel")->assertForbidden();
    }

    public function test_organizer_cannot_book(): void
    {
        $this->book(User::factory()->organizer()->create(), TicketType::factory()->create(), 1)->assertForbidden();
    }
}
