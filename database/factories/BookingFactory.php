<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_type_id' => TicketType::factory(),
            'event_id' => fn (array $attributes) => TicketType::find($attributes['ticket_type_id'])->event_id,
            'quantity' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'status' => 'confirmed',
        ];
    }
}
