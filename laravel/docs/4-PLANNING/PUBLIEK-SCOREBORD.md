---
title: Feature: Publiek Scorebord (Mobiel)
type: reference
scope: judotoernooi
last_check: 2026-04-22
---

# Feature: Publiek Scorebord (Mobiel)

> **Status:** OPEN
> **Prioriteit:** Medium — nice to have, lage kosten
> **Geschatte effort:** Klein — bestaande infra hergebruiken

## Idee

Ouders/coaches kunnen de live score volgen op hun eigen telefoon via de publieke app. Geen inlog nodig, gewoon een link of tab.

## Aanpak

- Bestaande `scoreboard-live.blade.php` als basis
- Nieuwe **mobiel-vriendelijke layout** bouwen (portrait + landscape)
  - Portrait: scores boven/onder, timer in midden (zoals Android app portrait)
  - Landscape: huidige LCD layout nabouwen (zoals Android app landscape)
- Responsive: detecteer orientation, switch layout
- Zelfde Reverb WebSocket channel (`scoreboard-display.{toernooiId}.{matId}`)
- Toegankelijk via publieke app als tab of link per mat

## Schaalbaarheid

- Reverb draait op de server (internet), niet lokaal
- Publiek gebruikt eigen 4G/5G, niet zaal WiFi
- WebSocket events zijn kleine JSON pakketjes
- 200+ connecties is geen probleem voor Reverb

## Noodplan (lokaal WiFi)

Bij failover naar lokaal netwerk:
- Publieke app automatisch uitschakelen
- Alleen vrijwilligers krijgen lokale URL
- Geen extra belasting op het lokale netwerk

## Referentie

- Huidige LCD view: `resources/views/pages/mat/scoreboard-live.blade.php`
- Android app layout: portrait + landscape scoreboard (nabouwen)
- Reverb channel: `scoreboard-display.{toernooiId}.{matId}`
- Event handler: `handleEvent()` in scoreboard-live — herbruikbaar
