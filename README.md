## WhatsApp Travel Concierge Demo

A Laravel-based WhatsApp travel concierge application that uses OpenAI for intelligent responses and Vonage Messages API for WhatsApp communication.

### Features

- WhatsApp webhook integration
- AI-powered travel concierge responses with conversation memory
- Interactive buttons for yes/no questions
- Queue-based message processing
- Error handling and logging

### Getting started

1. Copy `.env.example` to `.env` and populate the following variables:
   ```ini
   DB_CONNECTION=sqlite
   VONAGE_APP_ID=your_app_id
   VONAGE_PRIVATE_KEY=/path/to/private.key
   VONAGE_WHATSAPP_NUMBER=14155551234
   OPENAI_API_KEY=sk-...
   QUEUE_CONNECTION=database
   ```

2. Install dependencies and migrate the database:
   ```bash
   composer install
   php artisan migrate
   ```

3. Run the local server and queue worker:
   ```bash
   php artisan serve
   php artisan queue:work --tries=3 --timeout=30
   ```

4. Expose the app to the internet using `ngrok`:
   ```bash
   ngrok http 8000
   ```
   Note the forwarding URL (e.g. `https://abc123.ngrok.io`).

5. In your Vonage application dashboard set the **Inbound webhook URL** to `https://your-tunnel-url/webhooks/whatsapp`.

### Usage

Send WhatsApp messages to your Vonage number. The concierge will respond with travel advice, maintaining conversation context.

### Testing

Run tests with:
```bash
php artisan test
```

### Monitoring

Monitor application logs in real-time:
```bash
tail -f storage/logs/laravel.log
```

Monitor queue worker:
```bash
php artisan queue:work --verbose
```

Check failed jobs:
```bash
php artisan queue:failed
```

### Troubleshooting

#### Messages Not Being Received

**Problem:** WhatsApp messages sent but webhook not triggered.

**Solutions:**
1. Verify ngrok tunnel is active: `curl https://your-tunnel-url/webhooks/whatsapp`
2. Check Vonage Dashboard webhook URL matches your ngrok URL
3. Ensure webhook URL ends with `/webhooks/whatsapp`
4. Check Laravel logs for incoming requests

#### OpenAI Errors

**Problem:** `OpenAI service failed` in logs.

**Solutions:**
1. Verify `OPENAI_API_KEY` is set correctly in `.env`
2. Check OpenAI API status: https://status.openai.com
3. Review error message in logs for rate limits or quota issues
4. Fallback message will be sent automatically

#### Vonage Authentication Errors (401)

**Problem:** `HTTP request returned status code 401: Unauthorised`

**Solutions:**
1. Verify Vonage credentials in `.env`:
   - `VONAGE_APP_ID` matches your application ID
   - `VONAGE_PRIVATE_KEY` path points to correct `.key` file
   - `VONAGE_WHATSAPP_NUMBER` is in E.164 format (e.g., `12067365726`)
2. Ensure Messages API capability is enabled in Vonage Dashboard
3. Verify WhatsApp number is linked to your Vonage application
4. Re-download private key from Vonage Dashboard if needed
5. Check private key file permissions: `chmod 600 private_*.key`

#### Queue Worker Not Processing Jobs

**Problem:** Jobs stuck in queue, not being processed.

**Solutions:**
1. Ensure queue worker is running: `php artisan queue:work`
2. Restart queue worker after code changes: `php artisan queue:restart`
3. Check database connection if using `database` queue driver
4. Clear failed jobs: `php artisan queue:flush`
5. Monitor worker with `--verbose` flag for detailed output

#### Rate Limiting

**Problem:** `rate_limited` response from webhook.

**Solutions:**
1. Each phone number is limited to 60 requests per minute
2. Wait for the `retry_after` seconds indicated in response
3. Adjust rate limit in `WhatsAppController` if needed for testing

#### Database Errors

**Problem:** `no such table: conversations` or similar.

**Solutions:**
1. Run migrations: `php artisan migrate`
2. For fresh start: `php artisan migrate:fresh`
3. Verify database connection in `.env`

#### Job Retry Logic

Failed message sends will automatically retry:
- **Attempt 1:** Immediate
- **Attempt 2:** After 10 seconds
- **Attempt 3:** After 30 seconds
- **Attempt 4:** After 60 seconds (final attempt)

After 3 failed attempts, the job moves to failed jobs table.

### Architecture

**Message Flow:**
1. User sends WhatsApp message → Vonage webhook
2. `WhatsAppController` validates and rate-limits request
3. `ProcessIncomingMessage` job saves message and calls OpenAI
4. OpenAI returns structured response with conversation context
5. `SendMessageJob` sends response via Vonage (with retry logic)
6. Response saved to conversation history

**Key Components:**
- `WhatsAppController` - Webhook endpoint with rate limiting
- `ProcessIncomingMessage` - Handles incoming messages, calls OpenAI
- `SendMessageJob` - Sends messages via Vonage with retry logic
- `OpenAIService` - Generates AI responses with conversation memory
- `VonageService` - Sends messages using Vonage SDK
- `Conversation` - Stores message history for context
