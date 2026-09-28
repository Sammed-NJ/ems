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
            // only published events that haven't started yet
            ->where('status', 'published')
            ->where('starts_at', '>', now())
            // optional filters, each applied only when sent
            ->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->from, fn ($q, $from) => $q->whereDate('starts_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->whereDate('starts_at', '<=', $to))
            // load ticket types in one query to avoid N+1
            ->with('ticketTypes')
            ->orderBy('starts_at')
            // 10 per page, keep filters in the page links
            ->paginate(10)
            ->withQueryString();

        return EventResource::collection($events);
    }
}
