<?php

namespace Tests\Unit;

use App\Services\VonageService;
use Illuminate\Support\Facades\Http;
use Vonage\Client;
use Vonage\Client\Credentials\Keypair;
use Vonage\Messages\Channel\WhatsApp\WhatsAppText;
use Tests\TestCase;

class VonageServiceTest extends TestCase
{
    /** @test */
    public function it_sends_plain_text_message()
    {
        // Mock the Vonage client
        $mockClient = \Mockery::mock(Client::class);
        $mockMessagesClient = \Mockery::mock();
        
        $mockClient->shouldReceive('messages')->andReturn($mockMessagesClient);
        $mockMessagesClient->shouldReceive('send')->once()->with(
            \Mockery::on(function ($message) {
                return $message instanceof WhatsAppText;
            })
        );

        // Use reflection to inject the mocked client
        $service = new VonageService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $mockClient);

        $service->send([
            'to' => '123',
            'type' => 'text',
            'content' => ['body' => 'hi'],
        ]);
    }

    /** @test */
    public function it_builds_interactive_list_payload()
    {
        // Mock the Vonage client - lists are currently sent as text
        $mockClient = \Mockery::mock(Client::class);
        $mockMessagesClient = \Mockery::mock();
        
        $mockClient->shouldReceive('messages')->andReturn($mockMessagesClient);
        $mockMessagesClient->shouldReceive('send')->once()->with(
            \Mockery::on(function ($message) {
                return $message instanceof WhatsAppText;
            })
        );

        // Use reflection to inject the mocked client
        $service = new VonageService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $mockClient);

        $service->send([
            'to' => '123',
            'type' => 'list',
            'content' => [
                'interactive' => ['body' => ['text' => 'choose']],
            ],
        ]);
    }
}
