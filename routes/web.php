<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Vonage WhatsApp inbound webhook
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->post('/webhooks/whatsapp', [
    App\Http\Controllers\WhatsAppController::class,
    'receive',
]);

// simple media upload endpoint (for demo)
Route::post('/media', [
    App\Http\Controllers\MediaController::class,
    'store',
]);

Route::get('/media/signed/{filename}', [
    App\Http\Controllers\MediaController::class,
    'signedUrl',
]);
