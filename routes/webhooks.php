<?php

use Illuminate\Support\Facades\Route;

// Vonage WhatsApp inbound webhook - no web middleware to avoid CSRF
Route::post('/webhooks/whatsapp', [
    App\Http\Controllers\WhatsAppController::class,
    'receive',
]);