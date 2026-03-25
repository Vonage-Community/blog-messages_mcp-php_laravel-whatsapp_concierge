# WhatsApp Interactive Messages Implementation Plan

## Overview
Enhance the travel concierge to use WhatsApp's native interactive message types for a better user experience.

## Current Status
✅ **Working:** Basic text messages with formatted lists (numbered text)
✅ **Working:** OpenAI integration with conversation memory
✅ **Working:** Vonage SDK integration

## Goals
Implement 4 interactive message types:
1. **Reply Buttons** - For yes/no or simple choice questions (up to 3 buttons)
2. **List Messages** - For multiple options (up to 10 items in interactive list)
3. **Location Messages** - For sharing place coordinates
4. **Document Messages** - For PDF itineraries

---

## Task Breakdown

### 1. Interactive List Messages (Priority: HIGH)
**Goal:** When user asks "What is there to do in Tel Aviv", send an interactive list they can tap to select an option.

**Vonage Format:**
```json
{
  "message_type": "custom",
  "custom": {
    "type": "interactive",
    "interactive": {
      "type": "list",
      "body": {"text": "Here are some suggestions:"},
      "action": {
        "button": "View Options",
        "sections": [{
          "title": "Activities",
          "rows": [
            {"id": "1", "title": "Carmel Market", "description": "Local goods and street food"},
            {"id": "2", "title": "Beaches", "description": "Mediterranean coast"}
          ]
        }]
      }
    }
  }
}
```

**Implementation Steps:**
- [ ] Update `VonageService::sendList()` to send interactive list instead of formatted text
- [ ] Handle inbound webhook for list selections (message_type: "reply")
- [ ] Update OpenAI prompt to return list items with descriptions
- [ ] Test list message flow end-to-end

**Inbound Response Format:**
```json
{
  "message_type": "reply",
  "reply": {
    "id": "1",
    "title": "Carmel Market",
    "description": "Local goods and street food"
  }
}
```

---

### 2. Reply Buttons (Priority: HIGH)
**Goal:** For yes/no or simple questions, use interactive buttons instead of text.

**Use Cases:**
- "Would you like to book accommodations?" → [Yes] [No]
- "When are you traveling?" → [This Week] [Next Month] [Not Sure]

**Vonage Format:**
```json
{
  "message_type": "custom",
  "custom": {
    "type": "interactive",
    "interactive": {
      "type": "button",
      "body": {"text": "Would you like to book accommodations?"},
      "action": {
        "buttons": [
          {"type": "reply", "reply": {"id": "yes", "title": "Yes"}},
          {"type": "reply", "reply": {"id": "no", "title": "No"}}
        ]
      }
    }
  }
}
```

**Implementation Steps:**
- [ ] Update `VonageService::sendButtons()` to send interactive buttons
- [ ] Update OpenAI prompt to use buttons for binary/simple choices
- [ ] Handle inbound button responses
- [ ] Test button message flow

---

### 3. Location Messages (Priority: MEDIUM)
**Goal:** When user asks for location of a place, send coordinates.

**Challenge:** Need to get coordinates for locations mentioned by user.

**Options:**
- **Option A (Simple):** Use OpenAI to generate approximate coordinates (less accurate)
- **Option B (Better):** Integrate Google Maps Geocoding API
- **Option C (Demo):** Hardcode a few popular locations for demo purposes

**Vonage Format:**
```json
{
  "message_type": "location",
  "location": {
    "lat": "32.0853",
    "long": "34.7818",
    "name": "Carmel Market",
    "address": "HaCarmel St, Tel Aviv-Yafo, Israel"
  }
}
```

**Implementation Steps:**
- [ ] Decide on approach (recommend Option C for demo)
- [ ] Update `VonageService::sendLocation()` to send proper location messages
- [ ] Update OpenAI prompt to detect location requests
- [ ] Create location database/mapping for demo locations
- [ ] Test location message flow

---

### 4. PDF Itinerary Generation (Priority: MEDIUM)
**Goal:** Generate a beautiful PDF itinerary and send as document.

**Use Case:**
User: "Create a 3-day itinerary for Tel Aviv"
→ OpenAI generates itinerary
→ Laravel generates PDF
→ Upload to storage
→ Send as WhatsApp document

**Implementation Steps:**
- [ ] Install `barryvdh/laravel-dompdf` package
- [ ] Create itinerary Blade template
- [ ] Create `ItineraryService` to generate PDFs
- [ ] Update `VonageService::sendMedia()` to send documents
- [ ] Store PDFs temporarily (or use signed URLs)
- [ ] Update OpenAI prompt to detect itinerary requests
- [ ] Test PDF generation and sending

**Vonage Format:**
```json
{
  "message_type": "file",
  "file": {
    "url": "https://example.com/itinerary.pdf",
    "caption": "Your Tel Aviv 3-Day Itinerary"
  }
}
```

---

## OpenAI Prompt Updates

The OpenAI prompt needs to intelligently choose message types:

**Decision Logic:**
1. **List** - User asks for recommendations/options (5-10 items)
2. **Buttons** - Binary choice or 2-3 simple options
3. **Location** - User asks "where is X" or "location of X"
4. **Document** - User asks for itinerary/plan/detailed guide
5. **Text** - Everything else

**Updated Response Format:**
```json
{
  "type": "list",
  "content": {
    "body": "Here are some suggestions:",
    "items": [
      {"id": "1", "title": "Carmel Market", "description": "Local goods and street food"},
      {"id": "2", "title": "Beaches", "description": "Mediterranean coastline"}
    ]
  }
}
```

---

## Testing Plan

### Manual Testing Scenarios:
1. **List Message:**
   - User: "What is there to do in Tel Aviv"
   - Expected: Interactive list with 5 activities
   - User taps option → Receives detailed info

2. **Button Message:**
   - User: "I want to plan a trip"
   - Bot: "When are you traveling?" [This Week] [Next Month] [Not Sure]
   - User taps button → Conversation continues

3. **Location Message:**
   - User: "Where is Carmel Market?"
   - Expected: Location pin with coordinates

4. **Document Message:**
   - User: "Create a 3-day itinerary for Tel Aviv"
   - Expected: PDF document with itinerary

### Automated Testing:
- [ ] Unit test for each message type in `VonageService`
- [ ] Test OpenAI response parsing for each type
- [ ] Test inbound webhook handling for interactive responses

---

## Implementation Order (Recommended)

### Phase 1: Interactive Lists (Day 1)
- Implement list message sending
- Update OpenAI prompt for lists
- Handle list selection responses
- **Demo-ready:** User can select from activity lists

### Phase 2: Reply Buttons (Day 1)
- Implement button message sending
- Update OpenAI prompt for buttons
- Handle button responses
- **Demo-ready:** User can answer questions with buttons

### Phase 3: Location Messages (Day 2)
- Create location mapping for demo
- Implement location message sending
- Update OpenAI prompt for location detection
- **Demo-ready:** User can get location pins

### Phase 4: PDF Itineraries (Day 2-3)
- Install and configure dompdf
- Create itinerary template
- Implement PDF generation
- Implement document sending
- **Demo-ready:** User can receive PDF itineraries

---

## Technical Notes

### Vonage SDK Usage
The Vonage PHP SDK doesn't have built-in classes for interactive messages, so we'll need to use the raw HTTP client:

```php
$this->client->messages()->send([
    'from' => $this->whatsappNumber(),
    'to' => $message['to'],
    'channel' => 'whatsapp',
    'message_type' => 'custom',
    'custom' => [
        'type' => 'interactive',
        'interactive' => [
            'type' => 'list',
            // ... rest of structure
        ]
    ]
]);
```

### Inbound Webhook Updates
Need to handle new message types:
- `message_type: "reply"` - User selected from list/button
- Extract `reply.id` and `reply.title` for context

### Storage Considerations
For PDF documents:
- Store in `storage/app/public/itineraries/`
- Generate signed URLs with expiration
- Clean up old PDFs with scheduled task

---

## Success Criteria

✅ User can receive and interact with list messages
✅ User can tap buttons to answer questions
✅ User can receive location pins for places
✅ User can receive PDF itineraries
✅ All message types work seamlessly in conversation flow
✅ Tests pass for all message types
✅ Demo showcases all 4 interactive message types

---

## Questions to Resolve

1. **Location API:** Use Google Maps API or hardcode demo locations?
   - **Recommendation:** Hardcode 5-10 popular Tel Aviv locations for demo

2. **PDF Storage:** Temporary files or permanent storage?
   - **Recommendation:** Temporary with 24-hour expiration

3. **OpenAI Model:** Continue with gpt-3.5-turbo or upgrade to gpt-4?
   - **Recommendation:** Stick with gpt-3.5-turbo for cost, works well

4. **Error Handling:** What if user's WhatsApp doesn't support interactive messages?
   - **Recommendation:** Fallback to formatted text (current behavior)

---

## Estimated Effort

- **Interactive Lists:** 2-3 hours
- **Reply Buttons:** 1-2 hours  
- **Location Messages:** 2-3 hours (with hardcoded locations)
- **PDF Itineraries:** 4-5 hours
- **Testing & Polish:** 2-3 hours

**Total:** ~12-16 hours for complete implementation

---

## Next Steps

1. Review and approve this plan
2. Start with Phase 1 (Interactive Lists)
3. Test each phase before moving to next
4. Iterate based on demo feedback
