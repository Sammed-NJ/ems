<?php

use Illuminate\Support\Facades\Schedule;

// run every hour, skip if the previous run is still going
Schedule::command('bookings:send-reminders')->hourly()->withoutOverlapping();
