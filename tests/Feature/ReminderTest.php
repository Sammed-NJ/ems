<?php

namespace Tests\Feature;

use App\Jobs\SendEventReminder;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Notifications\EventReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_booking_gets_exactly_one_reminder_even_if_command_runs_twice(): void
    {
        Notification::fake();
        $soon = TicketType::factory()->for(Event::factory()->state(['starts_at' => now()->addHours(5)]))->create();
        $later = TicketType::factory()->for(Event::factory()->state(['starts_at' => now()->addDays(5)]))->create();
        $booking = Booking::factory()->create(['ticket_type_id' => $soon->id]);
        Booking::factory()->create(['ticket_type_id' => $later->id]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->assertSuccessful();
        (new SendEventReminder($booking->id))->handle(); // a duplicate job still in the queue

        Notification::assertSentTimes(EventReminderNotification::class, 1);
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_job_for_a_deleted_booking_does_nothing(): void
    {
        Notification::fake();

        (new SendEventReminder(12345))->handle();

        Notification::assertNothingSent();
    }
}
