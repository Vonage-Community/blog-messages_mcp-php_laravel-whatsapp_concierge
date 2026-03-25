<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    /** @test */
    public function it_returns_url_for_uploaded_file()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->postJson('/media', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('url', $response->json());

        Storage::disk('public')->assertExists(str_replace(Storage::disk('public')->url(''), '', $response->json('url')));
    }

    /** @test */
    public function it_can_generate_a_signed_url()
    {
        Storage::fake('public');

        // create a dummy file
        Storage::disk('public')->put('media/example.txt', 'test');

        $response = $this->getJson('/media/signed/example.txt');
        $response->assertStatus(200);
        $this->assertStringContainsString('/storage/media/example.txt', $response->json('url'));
    }
}
