<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OrganizerEventController;
use App\Http\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

// open to everyone
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/events', [PublicEventController::class, 'index']);

// needs a Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // organizers manage their own events at /api/organizer/events
    Route::middleware('role:organizer')->prefix('organizer')->group(function () {
        Route::apiResource('events', OrganizerEventController::class);
    });

    // attendees book and cancel
    Route::middleware('role:attendee')->group(function () {
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    });
});
