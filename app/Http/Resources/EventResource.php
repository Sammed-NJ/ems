<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'starts_at' => $this->starts_at,
            'status' => $this->status,
            // included only when ticket types were eager loaded
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            // sales numbers only shown to the event's organizer
            'tickets_sold' => $this->when($isOwner, $this->tickets_sold),
            'revenue' => $this->when($isOwner, $this->revenue),
        ];
    }
}
