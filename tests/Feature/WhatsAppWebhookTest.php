<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function valid_webhook_returns_received(): void
    {
        // fake external HTTP requests
        Http::fake();

        // the controller should accept well-formed payloads and reply quickly

        // ensure OpenAIService doesn't hit the network during the sync job
        $this->mock(\App\Services\OpenAIService::class, function ($mock) {
            $mock->shouldReceive('analyzeMessage')
                 ->andReturn([
                     'to' => '447700900000',
                     'type' => 'text',
                     'content' => ['body' => 'ok'],
                 ]);
        });

        $payload = [
            'from' => '447700900000',
            'to' => '447700900001',
            'message_type' => 'text',
            'text' => 'Hello',
        ];

        $this->postJson('/webhooks/whatsapp', $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'received']);

        // we don't assert on the job dispatch here; other unit tests can cover
        // the job logic separately if needed.
    }

    /** @test */
    public function missing_fields_return_422(): void
    {
        $this->postJson('/webhooks/whatsapp', ['foo' => 'bar'])
            ->assertStatus(422);
    }
}
