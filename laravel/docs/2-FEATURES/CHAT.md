---
title: Real-time Communicatie met Laravel Reverb
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Real-time Communicatie met Laravel Reverb

> **Index-doc:** Reverb draagt chat, mat-updates en de heartbeat. Hieronder de status en het overzicht; de details staan in deeldocs onder `CHAT/` — zie [Waar staat wat](#waar-staat-wat).

## Status: Werkend op staging en production

## Overzicht

Laravel Reverb wordt gebruikt voor twee real-time systemen:

1. **Chat** - Berichten tussen hoofdjury en PWA's (mat, weging, spreker, dojo)
2. **Mat Updates** - Live synchronisatie van scores, beurten en poule status

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---|---|
| [CHAT-FUNCTIONALITEIT.md](CHAT/CHAT-FUNCTIONALITEIT.md) | Je wilt weten welk kanaal wie hoort, hoe vrije chat en de beperkte modus verschillen, of welke widget waar staat. |
| [REVERB-SERVER-SETUP.md](CHAT/REVERB-SERVER-SETUP.md) | Reverb herstarten, supervisor/nginx/.env instellen, of een WebSocket die niet verbindt debuggen. |
| [CHAT-DATABASE-EN-FILES.md](CHAT/CHAT-DATABASE-EN-FILES.md) | Je raakt de chat-tabellen of de chat-classes/views aan. |
| [MAT-UPDATES.md](CHAT/MAT-UPDATES.md) | Je voegt een event-type toe, laat een view meeluisteren, of debugt score/beurt-sync tussen Mat en Publiek. |
| [HEARTBEAT-EN-CONNECTIE-STATUS.md](CHAT/HEARTBEAT-EN-CONNECTIE-STATUS.md) | Je werkt aan de server-side heartbeat of aan de LIVE/OFFLINE-knop in de Publiek PWA. |
