<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListEventsRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;

class PublicEventController extends Controller
{
    public function index(ListEventsRequest $request)
    {
        $events = Event::query()
            ->where('status', 'published')
            ->where('starts_at', '>', now())
            ->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->from, fn ($q, $from) => $q->whereDate('starts_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->whereDate('starts_at', '<=', $to))
            ->with('ticketTypes')
            ->orderBy('starts_at')
            ->paginate(10)
            ->withQueryString();

        return EventResource::collection($events);
    }
}
