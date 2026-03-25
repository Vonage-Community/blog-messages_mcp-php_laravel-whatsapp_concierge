<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsAppWebhookRequest;
use App\Jobs\ProcessIncomingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppController extends Controller
{
    /**
     * Receive a webhook from Vonage for inbound WhatsApp messages.
     */
    public function receive(WhatsAppWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();

        // Rate limit: 60 requests per minute per phone number
        $key = 'whatsapp:' . ($payload['from'] ?? 'unknown');
        
        if (RateLimiter::tooManyAttempts($key, 60)) {
            \Log::warning('WhatsApp webhook rate limit exceeded', [
                'from' => $payload['from'] ?? 'unknown',
                'retry_after' => RateLimiter::availableIn($key),
            ]);
            
            return response()->json([
                'status' => 'rate_limited',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }
        
        RateLimiter::hit($key, 60);

        // log for debugging - clean format
        \Log::info('WhatsApp webhook received', [
            'from' => $payload['from'] ?? 'unknown',
            'to' => $payload['to'] ?? 'unknown',
            'message_type' => $payload['message_type'] ?? 'unknown',
            'text' => $payload['text'] ?? null,
        ]);

        // For demo purposes we dispatch a job; run synchronously during tests
        if (app()->environment('testing')) {
            // use the Bus facade directly so tests can fake it reliably
            \Log::debug('WhatsAppController dispatch via Bus facade in testing');
            \Illuminate\Support\Facades\Bus::dispatch(new ProcessIncomingMessage($payload));
        } else {
            \Log::debug('WhatsAppController dispatch async');
            ProcessIncomingMessage::dispatch($payload);
        }

        // Respond quickly to Vonage
        return response()->json(['status' => 'received']);
    }
}
