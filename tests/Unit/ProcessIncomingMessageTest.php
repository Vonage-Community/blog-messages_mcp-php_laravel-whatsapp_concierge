<?php

namespace Tests\Unit;

use App\Jobs\ProcessIncomingMessage;
use App\Jobs\SendMessageJob;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessIncomingMessageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calls_openai_and_dispatches_send_job()
    {
        Bus::fake();

        $payload = [
            'from' => '447700900000',
            'to' => '447700900001',
            'message_type' => 'text',
            'text_body' => 'Hello',
        ];

        $openai = \Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('analyzeMessage')
            ->once()
            ->with($payload)
            ->andReturn(['to' => '447700900000', 'type' => 'text', 'content' => ['body' => 'ok']]);

        $this->app->instance(OpenAIService::class, $openai);

        $job = new ProcessIncomingMessage($payload);
        $job->handle($openai);

        Bus::assertDispatched(SendMessageJob::class, function ($j) {
            return $j->message['type'] === 'text';
        });
    }
}
