---
title: Chat: database en key files
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Chat: database en key files

> Onderdeel van [Real-time Communicatie met Laravel Reverb](../CHAT.md).

## Database

Berichten worden opgeslagen in `chat_messages`:

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| van_type | string | hoofdjury, mat, weging, spreker, dojo |
| van_id | int | Mat nummer (alleen bij mat) |
| naar_type | string | hoofdjury, mat, weging, spreker, dojo, alle_matten, iedereen |
| naar_id | int | Mat nummer (alleen bij mat) |
| bericht | text | Inhoud van het bericht |
| gelezen_op | datetime | Wanneer gelezen |
| created_at | datetime | Verzonden op |

## Key files (Chat)

- `app/Events/NewChatMessage.php` - Broadcast event
- `app/Http/Controllers/ChatController.php` - API endpoints
- `app/Models/ChatMessage.php` - Model met scopes
- `resources/views/partials/chat-widget.blade.php` - PWA widget
- `resources/views/partials/chat-widget-hoofdjury.blade.php` - Hoofdjury widget

---

