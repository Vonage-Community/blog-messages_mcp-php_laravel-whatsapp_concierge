<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vonage\Client;
use Vonage\Client\Credentials\Keypair;
use Vonage\Messages\Channel\WhatsApp\WhatsAppText;
use Vonage\Messages\Channel\WhatsApp\WhatsAppCustom;

class VonageService
{
    protected Client $client;
    protected Keypair $credentials;

    public function __construct()
    {
        $this->credentials = new Keypair(
            file_get_contents(config('services.vonage.private_key')),
            config('services.vonage.app_id')
        );
        
        $this->client = new Client($this->credentials);
    }

    /**
     * Send a message dictated by the structured payload.
     *
     * Supported payload structure:
     *   [
     *     'to' => '447700900000',
     *     'type' => 'text', // text,list,buttons,image,document,location
     *     'content' => [ ... type-specific ... ]
     *   ]
     *
     * The "from" (WhatsApp number) is read from config.
     *
     * @param  array<string, mixed>  $message
     */
    public function send(array $message): void
    {
        $type = $message['type'] ?? 'text';
        switch ($type) {
            case 'list':
                $this->sendList($message);
                break;
            case 'buttons':
                $this->sendButtons($message);
                break;
            case 'image':
                $this->sendMedia($message, 'image');
                break;
            case 'document':
                $this->sendMedia($message, 'file');
                break;
            case 'location':
                $this->sendLocation($message);
                break;
            case 'text':
            default:
                $this->sendText($message);
                break;
        }
    }

    protected function sendText(array $message): void
    {
        $text = new WhatsAppText(
            $message['to'],
            $this->whatsappNumber(),
            $message['content']['body'] ?? ''
        );
        
        $this->client->messages()->send($text);
    }

    protected function sendList(array $message): void
    {
        $items = $message['content']['items'] ?? [];
        
        if (empty($items)) {
            $this->sendText($message);
            return;
        }
        
        // Build interactive list rows
        $rows = [];
        foreach ($items as $index => $item) {
            // Handle both string items and structured items with title/description
            if (is_string($item)) {
                $title = $this->sanitizeForWhatsApp($item);
                if (strlen($title) > 24) {
                    $title = substr($title, 0, 21) . '...';
                }
                $description = $this->sanitizeForWhatsApp($item);
                if (strlen($description) > 72) {
                    $description = substr($description, 0, 69) . '...';
                }
                $rows[] = [
                    'id' => (string)($index + 1),
                    'title' => $title,
                    'description' => $description,
                ];
            } else {
                $title = $this->sanitizeForWhatsApp($item['title'] ?? $item);
                if (strlen($title) > 24) {
                    $title = substr($title, 0, 21) . '...';
                }
                $description = $this->sanitizeForWhatsApp($item['description'] ?? $item['title'] ?? $item);
                if (strlen($description) > 72) {
                    $description = substr($description, 0, 69) . '...';
                }
                $rows[] = [
                    'id' => $item['id'] ?? (string)($index + 1),
                    'title' => $title,
                    'description' => $description,
                ];
            }
        }
        
        // Build custom interactive list payload
        $custom = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'Travel Suggestions',
                ],
                'body' => [
                    'text' => $message['content']['body'] ?? 'Here are some suggestions:',
                ],
                'action' => [
                    'button' => 'View Options',
                    'sections' => [
                        [
                            'title' => 'Activities',
                            'rows' => $rows,
                        ],
                    ],
                ],
            ],
        ];
        
        // Log the custom payload for debugging
        \Log::info('Sending WhatsApp interactive list', [
            'to' => $message['to'],
            'custom_payload' => $custom,
        ]);
        
        // Send using WhatsAppCustom class (official SDK approach)
        $whatsApp = new WhatsAppCustom(
            $message['to'],
            $this->whatsappNumber(),
            $custom
        );
        
        $this->client->messages()->send($whatsApp);
    }

    protected function sendButtons(array $message): void
    {
        $buttons = [];
        foreach ($message['content']['buttons'] ?? [] as $index => $button) {
            $text = is_string($button) ? $button : ($button['text'] ?? $button['title'] ?? '');
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'] ?? (string)($index + 1),
                    'title' => substr($text, 0, 20), // WhatsApp limit: 20 chars for buttons
                ],
            ];
        }

        // Build custom interactive button payload
        $custom = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $message['content']['body'] ?? 'Choose an option:',
                ],
                'action' => [
                    'buttons' => $buttons,
                ],
            ],
        ];
        
        // Send using WhatsAppCustom class (official SDK approach)
        $whatsApp = new WhatsAppCustom(
            $message['to'],
            $this->whatsappNumber(),
            $custom
        );
        
        $this->client->messages()->send($whatsApp);
    }

    protected function sendMedia(array $message, string $mediaType): void
    {
        // For now, send as text with URL. Implement media support later.
        $text = new WhatsAppText(
            $message['to'],
            $this->whatsappNumber(),
            ($message['content']['caption'] ?? 'Media: ') . ($message['content']['url'] ?? 'N/A')
        );
        
        $this->client->messages()->send($text);
    }

    protected function sendLocation(array $message): void
    {
        // For now, send as text. Implement location support later.
        $text = new WhatsAppText(
            $message['to'],
            $this->whatsappNumber(),
            'Location: ' . json_encode($message['content'])
        );
        
        $this->client->messages()->send($text);
    }

    protected function whatsappNumber(): string
    {
        return config('services.vonage.whatsapp_number');
    }

    /**
     * Sanitize text for WhatsApp interactive messages to avoid error 1020
     * Removes or replaces special characters that WhatsApp doesn't accept
     */
    protected function sanitizeForWhatsApp(string $text): string
    {
        // Replace common accented characters with ASCII equivalents
        $replacements = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ñ' => 'n', 'ç' => 'c',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ñ' => 'N', 'Ç' => 'C',
            'œ' => 'oe', 'Œ' => 'OE',
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}

