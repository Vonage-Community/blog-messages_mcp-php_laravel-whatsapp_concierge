<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService
{
    /**
     * Analyze an incoming Vonage webhook payload and produce a structured
     * message suitable for VonageService->send().
     *
     * This uses the cheapest OpenAI conversational model and relies on the
     * model to output valid JSON of the form:
     *
     * {
     *   "to": "447700900000",
     *   "type": "text", // or list, buttons, image, document, location
     *   "content": { ... }
     * }
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function analyzeMessage(array $payload): array
    {
        if (empty($payload['from'])) {
            \Log::error('Missing from field in webhook payload', ['payload' => $payload]);
            throw new \InvalidArgumentException('Webhook payload must contain a valid "from" field');
        }

        // Get recent conversation history (last 6 messages for 3 turns)
        $recentConversations = \App\Models\Conversation::where('from_number', $payload['from'])
            ->latest()
            ->take(6)
            ->get()
            ->reverse() // oldest first
            ->toArray();

        $history = '';
        foreach ($recentConversations as $conv) {
            $role = $conv['is_user'] ? 'User' : 'Assistant';
            $history .= "$role: " . $conv['message_text'] . "\n";
        }

        $conversationContext = !empty($history) 
            ? "Conversation history:\n$history"
            : '';

        $system = 'You are an expert travel concierge specializing in personalized vacation planning. Help users discover amazing destinations, book accommodations, find activities, and get travel tips. Always be friendly, knowledgeable, and enthusiastic about travel. Use the conversation history to maintain context and provide relevant, continuing responses that advance their travel planning. '
            .'CRITICAL: You must respond with ONLY valid JSON in this exact format: '
            .'{"type": "text", "content": {"body": "your message"}} '
            .'OR for lists of activities/recommendations (5-10 items): '
            .'{"type": "list", "content": {"body": "Here are some suggestions:", "items": [{"title": "Short Title", "description": "Brief description of the activity"}, {"title": "Another Activity", "description": "What makes this special"}]}} '
            .'OR for yes/no or simple choice questions (2-3 options): '
            .'{"type": "buttons", "content": {"body": "your question?", "buttons": [{"text": "Yes"}, {"text": "No"}]}} '
            .'IMPORTANT: For list items, title must be 24 characters or less. Description can be longer and more detailed. '
            .'When users ask about activities, things to do, or recommendations, use the "list" type with structured items. '
            .'When asking binary questions or offering 2-3 simple choices, use "buttons" type. '
            .'Do NOT include any text outside the JSON structure. Do NOT use markdown formatting.';

        $user = $conversationContext . 'Current user message: ' . ($payload['text'] ?? '');

        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
            ])
            ->throw()
            ->json();

        $text = data_get($response, 'choices.0.message.content', '');
        $decoded = json_decode($text, true);

        // Validate JSON was parsed successfully
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            \Log::warning('OpenAI returned invalid JSON', [
                'response' => $text,
                'json_error' => json_last_error_msg(),
            ]);
            
            return [
                'to' => $payload['from'],
                'type' => 'text',
                'content' => ['body' => 'Sorry, I could not understand your request.'],
            ];
        }

        // Ensure required fields are present
        $decoded['to'] = $payload['from'];
        
        if (!isset($decoded['type'])) {
            $decoded['type'] = 'text';
        }
        
        if (!isset($decoded['content'])) {
            // Handle legacy content_preview field
            if (isset($decoded['content_preview'])) {
                $decoded['content'] = $decoded['content_preview'];
                unset($decoded['content_preview']);
            } elseif (isset($decoded['body'])) {
                // OpenAI returned flat structure with 'body' at root level
                $decoded['content'] = ['body' => $decoded['body']];
                unset($decoded['body']);
            } else {
                // No content found - log and return fallback
                \Log::warning('OpenAI returned valid JSON but missing content field', [
                    'decoded' => $decoded,
                ]);
                
                return [
                    'to' => $payload['from'],
                    'type' => 'text',
                    'content' => ['body' => 'Sorry, I could not understand your request.'],
                ];
            }
        }

        return $decoded;
    }
}
