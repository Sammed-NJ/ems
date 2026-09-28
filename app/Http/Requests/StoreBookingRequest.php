<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            // quick check here, the real per-event limit is in BookingService
            'quantity' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}
