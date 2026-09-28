<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrganizerEventController extends Controller
{
    // only the logged-in organizer's own events
    public function index(Request $request)
    {
        $events = $request->user()->events()->with('ticketTypes')->latest()->paginate(10);

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        // event and its ticket types are saved together or not at all
        $event = DB::transaction(function () use ($request) {
            $event = $request->user()->events()->create($request->safe()->except('ticket_types'));
            $event->ticketTypes()->createMany($request->validated('ticket_types'));

            return $event;
        });

        return (new EventResource($event->load('ticketTypes')))->response()->setStatusCode(201);
    }

    public function show(Event $event): EventResource
    {
        // 403 unless the organizer owns this event (EventPolicy)
        Gate::authorize('view', $event);

        return new EventResource($event->load('ticketTypes'));
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        Gate::authorize('update', $event);

        $event->update($request->validated());

        return new EventResource($event->load('ticketTypes'));
    }

    public function destroy(Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);

        // can't delete once people have confirmed bookings
        if ($event->bookings()->where('status', 'confirmed')->exists()) {
            throw ValidationException::withMessages(['event' => 'An event with confirmed bookings cannot be deleted.']);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }
}
