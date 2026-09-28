<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()->with(['event', 'ticketType'])->latest()->paginate(10);

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->book($request->user(), $request->integer('ticket_type_id'), $request->integer('quantity'));

        return (new BookingResource($booking->load(['event', 'ticketType'])))->response()->setStatusCode(201);
    }

    public function cancel(Booking $booking): BookingResource
    {
        Gate::authorize('cancel', $booking);

        $booking = $this->bookings->cancel($booking);

        return new BookingResource($booking->load(['event', 'ticketType']));
    }
}
