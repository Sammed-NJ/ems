<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', 'after:now'],
            'status' => ['required', 'in:draft,published'],
            'ticket_types' => ['required', 'array', 'min:1'],
            'ticket_types.*.name' => ['required', 'string', 'max:100'],
            'ticket_types.*.price' => ['required', 'numeric', 'min:0'],
            'ticket_types.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
