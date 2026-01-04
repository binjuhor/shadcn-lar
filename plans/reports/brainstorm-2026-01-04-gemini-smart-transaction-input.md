# Brainstorm: Gemini Smart Transaction Input

**Date:** 2026-01-04
**Status:** Agreed
**Feature:** Voice & receipt image transaction input using Gemini API

---

## Problem Statement

User wants to input financial transactions quickly via:
1. **Voice input** - speak transaction details in Vietnamese/English
2. **Receipt/bill screenshot** - upload image for OCR extraction

Target: Mobile-first web app with API for future native mobile app.

---

## Requirements

| Requirement | Detail |
|-------------|--------|
| API Key | Server-side from `.env` (GEMINI_API_KEY) |
| Primary Language | Vietnamese first, English later |
| Platform | Mobile-first web, API for future mobile app |
| Accuracy | 80-95%+ with editable preview |
| UX | Single page with dropzone + voice button |

---

## Agreed Solution: API-First Backend Proxy

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│ Clients: Web (Inertia) | Mobile App | PWA              │
└────────────────────────┬────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│ API Layer (/api/v1/finance/)                           │
│  POST /parse-voice    - audio → transaction            │
│  POST /parse-receipt  - image → transaction            │
│  POST /transactions   - save transaction               │
│  GET  /categories     - user categories                │
│  GET  /accounts       - user accounts                  │
│                                                         │
│ Auth: Sanctum (session for web, token for mobile)      │
└────────────────────────┬────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│ Service Layer                                           │
│  GeminiTransactionParser                               │
│   - parseVoice(audio, lang)                            │
│   - parseReceipt(image, lang)                          │
│   - matchCategory(hint, categories)                    │
│   - matchAccount(hint, accounts)                       │
└─────────────────────────────────────────────────────────┘
```

### File Structure

```
Modules/Finance/
├── app/
│   ├── Http/Controllers/
│   │   ├── SmartInputController.php         # Web page
│   │   └── Api/
│   │       └── SmartInputApiController.php  # API endpoints
│   └── Services/
│       └── GeminiTransactionParser.php      # Shared service
├── routes/
│   ├── web.php   # Add smart-input route
│   └── api.php   # New API routes
└── resources/js/pages/
    └── smart-input/
        ├── index.tsx
        └── components/
            ├── voice-recorder.tsx
            ├── image-dropzone.tsx
            └── transaction-preview.tsx
```

### API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/v1/finance/parse-voice` | Parse audio to transaction |
| POST | `/api/v1/finance/parse-receipt` | Parse image to transaction |
| POST | `/api/v1/finance/transactions` | Create transaction |
| GET | `/api/v1/finance/categories` | List user categories |
| GET | `/api/v1/finance/accounts` | List user accounts |

### Response Format

```json
{
  "success": true,
  "data": {
    "type": "expense",
    "amount": 50000,
    "description": "Highland Coffee",
    "suggested_category": { "id": 5, "name": "Food & Drink" },
    "suggested_account": { "id": 2, "name": "Cash" },
    "transaction_date": "2026-01-04",
    "confidence": 0.92,
    "raw_extracted_text": "..."
  }
}
```

---

## Vietnamese Parsing Strategy

### Voice Patterns

| Input | Extracted |
|-------|-----------|
| "Ăn sáng 35 nghìn" | expense, 35000, Food |
| "Lương tháng 15 triệu" | income, 15000000, Salary |
| "Đổ xăng 200k hôm qua" | expense, 200000, Transport, yesterday |
| "Cafe 50k" | expense, 50000, Food |

### Receipt Keywords

| Vietnamese | English | Field |
|------------|---------|-------|
| Tổng cộng, Thành tiền | Total | amount |
| Ngày | Date | transaction_date |
| Store name (top) | - | description |

### Number Shortcuts

| Shortcut | Value |
|----------|-------|
| k, nghìn | ×1,000 |
| tr, triệu | ×1,000,000 |
| tỷ | ×1,000,000,000 |

---

## UX Design (Mobile-First)

```
┌─────────────────────────────────────┐
│  Smart Transaction Input            │
│  ─────────────────────────────────  │
│                                     │
│  ┌─────────────────────────────┐   │
│  │                             │   │
│  │  📷 Drag & drop receipt     │   │
│  │     or tap to upload        │   │
│  │                             │   │
│  │  ────── or ──────           │   │
│  │                             │   │
│  │  🎤 Hold to speak           │   │
│  │  "Cafe 50k hôm nay"         │   │
│  │                             │   │
│  └─────────────────────────────┘   │
│                                     │
│  ┌─────────────────────────────┐   │
│  │ Extracted:                  │   │
│  │ Type: [Expense ▼]           │   │
│  │ Amount: 50,000 VND          │   │
│  │ Description: Cafe           │   │
│  │ Category: [Food & Drink ▼]  │   │
│  │ Account: [Cash ▼]           │   │
│  │ Date: 04/01/2026            │   │
│  │                             │   │
│  │ Confidence: 95% ██████████░ │   │
│  └─────────────────────────────┘   │
│                                     │
│      [Cancel]  [Save Transaction]   │
└─────────────────────────────────────┘
```

### Mobile Optimizations

- Large touch targets (min 48px)
- Full-screen dropzone
- Direct camera access button
- Haptic feedback on success
- Offline queue support (future)

---

## Implementation Phases

### Phase 1: Web Version (Current)
1. Create GeminiTransactionParser service
2. Create API endpoints
3. Create SmartInputController (web)
4. Build smart-input page with React components
5. Add route to sidebar

### Phase 2: Mobile App (Future)
1. Implement Sanctum token auth
2. Mobile app consumes same API
3. Add push notifications
4. Offline sync support

---

## Technical Notes

### Gemini Model
- Use `gemini-2.0-flash` for fast response
- Vision API for receipt parsing
- Audio API or transcribe first then parse

### Prompt Engineering (Key to Accuracy)

```
You are a Vietnamese financial transaction parser.

Extract from input:
- type: "income" or "expense"
- amount: number in VND
- description: brief description
- category_hint: suggested category
- date_hint: relative date if mentioned

Vietnamese number shortcuts:
- "k" or "nghìn" = ×1,000
- "tr" or "triệu" = ×1,000,000

Date shortcuts:
- "hôm nay" = today
- "hôm qua" = yesterday
- "tuần trước" = last week

Return valid JSON only, no explanation.
```

### Error Handling
- Gemini API timeout: 30s max
- Invalid image: return error with message
- Low confidence (<50%): warn user to verify
- Rate limiting: queue requests

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Parse accuracy | 80-95% |
| Response time | <3s |
| User correction rate | <20% |
| Mobile usability score | >4/5 |

---

## Dependencies

- `GEMINI_API_KEY` in .env (already set)
- Laravel Sanctum for API auth
- react-dropzone for image upload
- MediaRecorder API for voice

---

## Next Steps

1. Implement GeminiTransactionParser service
2. Create API routes with Sanctum
3. Build web smart-input page
4. Test with Vietnamese receipts/voice
5. Add to Finance sidebar navigation

---

## Unresolved Questions

None - all requirements clarified.
