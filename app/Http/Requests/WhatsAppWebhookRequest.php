<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // We'll handle any request but may add signature verification later.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The Vonage inbound payload for WhatsApp varies, but we require
     * at minimum the `from`, `to`, `message_type` keys.
     * We allow all other fields to pass through.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => 'required|string',
            'to' => 'required|string',
            'message_type' => 'required|string',
            // Allow all other fields
            '*' => 'sometimes',
        ];
    }
}
