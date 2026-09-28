<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organizer = User::factory()->organizer()->create([
            'name' => 'Rahul Menon',
            'email' => 'organizer@test.com',
        ]);

        User::factory()->create(['name' => 'Anjali Nair', 'email' => 'attendee1@test.com']);
        User::factory()->create(['name' => 'Vishnu Das', 'email' => 'attendee2@test.com']);

        $event = $organizer->events()->create([
            'title' => 'Tech Meetup',
            'venue' => 'Kochi',
            'starts_at' => now()->addHours(20),
            'status' => 'published',
        ]);
        $event->ticketTypes()->createMany([
            ['name' => 'General', 'price' => 200, 'quantity' => 100],
            ['name' => 'VIP', 'price' => 500, 'quantity' => 2],
        ]);

        $event = $organizer->events()->create([
            'title' => 'Music Night',
            'venue' => 'Bangalore',
            'starts_at' => now()->addDays(15),
            'status' => 'published',
        ]);
        $event->ticketTypes()->createMany([
            ['name' => 'General', 'price' => 800, 'quantity' => 200],
            ['name' => 'VIP', 'price' => 2000, 'quantity' => 20],
        ]);

        $event = $organizer->events()->create([
            'title' => 'Food Fest',
            'venue' => 'Chennai',
            'starts_at' => now()->addDays(30),
            'status' => 'draft',
        ]);
        $event->ticketTypes()->create(['name' => 'General', 'price' => 100, 'quantity' => 50]);
    }
}
