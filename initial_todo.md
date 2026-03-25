# Demo travelconcierge WhatsApp app

This checklist describes the work required to build a Laravel demo that:

* accepts incoming WhatsApp messages from Vonage (real account),
* uses OpenAI (cheapest model) to decide which WhatsApp message type to reply with
  and to generate the content, and
* sends replies (text/lists/buttons/images/documents/location) via the
  Vonage Messages API.

---

## Setup & configuration

- [x] add Vonage credentials (`VONAGE_APP_ID`, `VONAGE_PRIVATE_KEY`,
      `VONAGE_WHATSAPP_NUMBER`) to `.env`
- [x] add OpenAI credentials (`OPENAI_API_KEY`) to `.env`
- [x] configure `config/services.php` entries for `vonage` and `openai`

## Routing & controllers

- [x] add `/webhooks/whatsapp` POST route in `routes/web.php`
- [x] create `WhatsAppController` to receive/validate Vonage payloads
- [x] normalize inbound message data and dispatch `ProcessIncomingMessage` job

## Queue jobs

- [x] create `ProcessIncomingMessage` job: call OpenAI service & dispatch
  `SendMessageJob`
- [x] create `SendMessageJob` job: send the appropriate Vonage API request
## Services

- [x] implement `VonageService` with methods for all supported message types
  (text, interactive list, interactive buttons, media, location)
- [x] implement `OpenAIService` that:
    * classifies inbound text into a message type, and
    * returns structured content for that type
  (prompt design using cheapest OpenAI model, e.g. `gpt-3.5-turbo`)
## Media handling

- [x] store uploaded images/PDFs under `storage/app/public` and serve public
  URLs suitable for Vonage media payloads (MediaController)
- [x] add route `/media` and simple controller returning URL
- [x] optionally add route/helper for serving signed URLs if needed

## Testing & quality

- [x] add `tests/Feature/WhatsAppWebhookTest.php` covering happy/failure flows
- [x] add `tests/Unit/VonageServiceTest.php`, `ProcessIncomingMessageTest.php`,
      and `MediaUploadTest.php` (unit + feature)
- [x] run the new test(s) locally
- [x] run `vendor/bin/pint --dirty --format agent` on modified PHP files

## Deployment & webhook

- [ ] document (README) using `ngrok` or other tunnel to expose webhook URL
- [ ] configure Vonage Applications inbound webhook to the `/webhooks/whatsapp`
      URL
 - [x] document (README) using `ngrok` or other tunnel to expose webhook URL

---

## Review

- Plan created at root as `todo.md`.
- Each checklist item will be completed in its own commit with a brief
  explanation.
- After finishing implementation, Ill add a summary here describing what
  changed, why, and any followups.
- Testing will begin with a single focused PHPUnit run; you can ask for the
  full suite afterward.

### Progress so far

* Configured environment variables and services entries for Vonage & OpenAI.
* Added webhook route, controller with validation and job dispatch.
* Implemented `ProcessIncomingMessage` and `SendMessageJob`.
* Built `VonageService` and `OpenAIService` with minimal request logic.
* Added media upload endpoint and storage support via `MediaController`.
* Wrote feature test for webhook and media upload, plus unit tests for
  `VonageService` and the incoming‑message job.  All tests should pass with
  fakes/mocks in place.
* Ran `vendor/bin/pint` on all modified PHP files.

Next steps: add deployment documentation (ngrok instructions), extend
OpenAI prompt, continue preparing outbound message logic, and wire up the
remaining service methods as the demo evolves.

### Completed since last update

* Added unit tests covering the Vonage service, the incoming-message job, and
  media upload endpoint.
* Created media controller and route plus corresponding feature test.
* Updated README with full setup instructions and ngrok guidance.

All remaining checkboxes are either optional or pertain to external
configuration (Vonage application webhook setting).
