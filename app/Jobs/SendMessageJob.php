<?php

namespace App\Jobs;

use App\Services\VonageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [10, 30, 60];

    /**
     * Structured payload that tells VonageService what to send.
     *
     * @var array<string, mixed>
     */
    public array $message;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $message
     */
    public function __construct(array $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(VonageService $vonage): void
    {
        if (empty($this->message['to'])) {
            \Log::error('SendMessageJob: Missing required "to" field', [
                'message' => $this->message,
            ]);
            $this->fail(new \InvalidArgumentException('Message must contain a valid "to" field'));
            return;
        }

        if (empty($this->message['type'])) {
            \Log::error('SendMessageJob: Missing required "type" field', [
                'message' => $this->message,
            ]);
            $this->fail(new \InvalidArgumentException('Message must contain a valid "type" field'));
            return;
        }

        \Log::info('Sending message via Vonage', [
            'to' => $this->message['to'],
            'type' => $this->message['type'],
        ]);

        try {
            $vonage->send($this->message);
            
            // Save the assistant response for conversation history
            if ($this->message['type'] === 'text' && isset($this->message['content']['body'])) {
                \App\Models\Conversation::create([
                    'from_number' => $this->message['to'],
                    'message_text' => $this->message['content']['body'],
                    'is_user' => false,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Vonage service failed to send message', [
                'error' => $e->getMessage(),
                'message' => $this->message,
            ]);
            $this->fail($e);
        }
    }
}
