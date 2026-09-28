<?php

namespace App\Console\Commands;

use App\Jobs\SendEventReminder;
use App\Models\Booking;
use Illuminate\Console\Command;

class SendEventReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Queue a reminder for every confirmed booking whose event starts in the next 24 hours';

    public function handle(): void
    {
        $count = 0;

        // confirmed, not yet reminded, event starting within 24 hours
        Booking::query()
            ->select('id')
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->whereHas('event', fn ($q) => $q->whereBetween('starts_at', [now(), now()->addDay()]))
            // 500 rows at a time so 10,000+ bookings don't fill memory
            ->chunkById(500, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    // one queued job per booking
                    SendEventReminder::dispatch($booking->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} reminders.");
    }
}
