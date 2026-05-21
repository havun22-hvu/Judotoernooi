---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-05-21
---

# JudoToernooi — Handover

> Vul dit aan aan het einde van elke sessie.

## Huidige status

**Status:** Stabiel in productie — multi-tenant SaaS op judotoernament.org
**Branch:** master (schoon)
**AutoFix:** actief op production + staging

## Openstaande items

*(geen bekende openstaande items — voeg toe bij volgende sessie)*

## Kritieke context voor volgende sessie

- Artisan altijd met `cd laravel &&` prefix
- Auth guard is `organisator` — NIET `web`
- DB is SQLite lokaal, MySQL production — NOOIT test draaien op staging/production
- Realtime via Reverb/WebSockets — geen polling
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na `git push` server → lokaal
- Deploy: `git pull` in repo-pad (`/var/www/judotoernooi/repo-prod`), NIET in symlink

## Sessie-log

*(voeg hier sessie-samenvattingen toe)*
