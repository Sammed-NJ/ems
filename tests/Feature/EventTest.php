<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_logout(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'organizer',
        ])->assertCreated()->assertJsonPath('user.role', 'organizer');

        $token = $this->postJson('/api/login', ['email' => 'sam@example.com', 'password' => 'password123'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
    }

    public function test_guest_gets_401_json(): void
    {
        $this->postJson('/api/bookings')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_organizer_creates_event_with_ticket_types(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)->postJson('/api/organizer/events', [
            'title' => 'Tech Talk',
            'venue' => 'Kochi',
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'status' => 'published',
            'ticket_types' => [
                ['name' => 'General', 'price' => 100, 'quantity' => 50],
                ['name' => 'VIP', 'price' => 300, 'quantity' => 5],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.ticket_types');
    }

    public function test_organizer_cannot_edit_another_organizers_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->organizer()->create())
            ->putJson("/api/organizer/events/{$event->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_event_with_confirmed_bookings_cannot_be_deleted(): void
    {
        $booking = Booking::factory()->create();
        $event = $booking->event;

        $this->actingAs($event->organizer)->deleteJson("/api/organizer/events/{$event->id}")->assertUnprocessable();
        $this->assertModelExists($event);
    }

    public function test_public_listing_shows_only_published_upcoming_events_with_filters(): void
    {
        TicketType::factory()->for(Event::factory()->state(['title' => 'Jazz Night', 'starts_at' => now()->addDays(3)]))->create();
        Event::factory()->draft()->create(['title' => 'Jazz Draft']);
        Event::factory()->create(['title' => 'Jazz Past', 'starts_at' => now()->subDay()]);
        Event::factory()->create(['title' => 'Rock Show']);

        $this->getJson('/api/events?search=Jazz')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Jazz Night')
            ->assertJsonPath('data.0.ticket_types.0.seats_remaining', 50)
            ->assertJsonMissingPath('data.0.revenue');

        $this->getJson('/api/events?from='.now()->addDays(5)->toDateString())->assertJsonCount(1, 'data');
    }

    public function test_unknown_resource_returns_404_json(): void
    {
        $this->actingAs(User::factory()->organizer()->create())
            ->getJson('/api/organizer/events/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }
}
