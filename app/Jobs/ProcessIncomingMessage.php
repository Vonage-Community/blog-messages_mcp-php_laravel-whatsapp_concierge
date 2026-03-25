<?php

namespace App\Jobs;

use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The raw webhook payload.
     *
     * @var array<string, mixed>
     */
    public array $payload;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAIService $openAI): void
    {
        // Extract message text - handle both regular text and interactive replies
        $messageText = $this->payload['text'] ?? '';
        
        // If this is a reply from an interactive message (list/button selection)
        if (isset($this->payload['message_type']) && $this->payload['message_type'] === 'reply') {
            $reply = $this->payload['reply'] ?? [];
            $messageText = $reply['title'] ?? $reply['id'] ?? '';
            
            // Log the interactive selection
            \Log::info('Interactive message reply received', [
                'from' => $this->payload['from'],
                'reply_id' => $reply['id'] ?? null,
                'reply_title' => $reply['title'] ?? null,
            ]);
        }
        
        // Save the incoming message for conversation history
        \App\Models\Conversation::create([
            'from_number' => $this->payload['from'],
            'message_text' => $messageText,
            'is_user' => true,
        ]);

        try {
            // use the OpenAIService to determine message type and build content
            $response = $openAI->analyzeMessage($this->payload);
        } catch (\Exception $e) {
            \Log::error('OpenAI service failed', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);

            // Send a fallback message
            $response = [
                'to' => $this->payload['from'],
                'type' => 'text',
                'content' => ['body' => 'Sorry, I\'m having trouble processing your request right now. Please try again later.'],
            ];
        }

        \Log::info('OpenAI response generated', [
            'to' => $response['to'] ?? 'unknown',
            'type' => $response['type'] ?? 'unknown',
            'content_preview' => $response['type'] === 'text' 
                ? ($response['content']['body'] ?? null)
                : json_encode($response['content'] ?? null),
        ]);

        // dispatch the send job with whatever structure the service returned
        SendMessageJob::dispatch($response);
    }
}
