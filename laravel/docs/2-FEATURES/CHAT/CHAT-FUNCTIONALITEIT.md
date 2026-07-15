---
title: Chat: keuzes, kanalen, flow en UI
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Chat: keuzes, kanalen, flow en UI

> Onderdeel van [Real-time Communicatie met Laravel Reverb](../CHAT.md).

# 1. Chat Functionaliteit

Realtime chat tussen hoofdjury en PWA's (mat, weging, spreker, dojo) via Laravel Reverb WebSockets.

## Technische keuzes

- **Laravel Reverb** - Officieel Laravel WebSocket server (gratis, self-hosted)
- **Geen polling** - Direct push via WebSockets
- **Kanaal-gebaseerd** - Elk device krijgt eigen kanaal
- **ShouldBroadcastNow** - Directe broadcast zonder queue

## Kanalen structuur

```
chat.{toernooi_id}.hoofdjury       - Hoofdjury
chat.{toernooi_id}.mat.{mat_id}    - Per mat
chat.{toernooi_id}.weging          - Weging
chat.{toernooi_id}.spreker         - Spreker
chat.{toernooi_id}.dojo            - Dojo scanner
chat.{toernooi_id}.alle_matten     - Broadcast naar alle matten
chat.{toernooi_id}.iedereen        - Broadcast naar iedereen
```

## Communicatie flow

### Standaard (vrije chat)
- **Iedereen kan naar iedereen sturen** - PWA's en hoofdjury hebben dezelfde opties:
  - Iedereen (broadcast)
  - Alle matten
  - Specifieke mat (mat 1, mat 2, etc.)
  - Weging
  - Spreker
  - Dojo
  - Hoofdjury

### Beperkte modus (toggle door hoofdjury)
- Hoofdjury kan "vrije chat" uitschakelen via toggle in Instellingen
- Bij uitschakelen: **PWA's kunnen alleen naar hoofdjury sturen**
- Hoofdjury behoudt alle opties
- Nuttig bij misbruik door vrijwilligers

## UI Componenten

### Alle PWA's + Hoofdjury
1. **Toast notificatie** - Bij nieuw bericht, verschijnt bovenaan scherm
2. **Chat icoontje** - Altijd zichtbaar in hoek, met badge voor ongelezen
3. **Chatvenster** - Opent bij klik op icoontje, toont berichtengeschiedenis

### Hoofdjury extra
- Buttons om ontvanger te selecteren (alle matten, mat X, weging, spreker)
- Klik op inkomend bericht om direct te antwoorden aan die afzender

---

