# JudoToernooi — Claude Instructions

> **Type:** SaaS multi-tenant toernooi-management — https://judotournament.org
> **Bedrijfsmodel:** Havun verhuurt aan judoscholen (organisatoren).
> **Stack:** Laravel 11 + Blade + Alpine.js (CSP) + Tailwind, MySQL prod / SQLite local.
> **Onveranderlijke regels:** [`CONTRACTS.md`](CONTRACTS.md) — eerst raadplegen.
> **Detail-context + handover:** `.claude/context.md` + `.claude/handover.md`

## De 5 Onschendbare Regels

1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen (coverage >82,5%, huidig 89,6%)
5. ALTIJD toestemming vragen bij grote wijzigingen

## ⛔ Hard nooit's

- **Geen `php artisan test` op staging/production** — `.env` overschrijft SQLite → wist MySQL data (incident 4 apr 2026)
- **Geen polling** (setInterval/setTimeout fetch) — altijd Reverb/WebSockets
- **Geen direct-op-server editen** — altijd Local → GitHub → Server
- **Nieuwe broadcast events:** verplicht `use \App\Events\Concerns\SafelyBroadcasts;`

## Sessie-start sync (AutoFix kan op server pushen)

```bash
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-prod && git add -A && git diff --cached --quiet || git commit -m 'autofix' && git push"
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-staging && git add -A && git diff --cached --quiet || git commit -m 'autofix' && git push"
cd D:\GitHub\JudoToernooi && git pull
```

## SaaS-mindset

Werkt dit voor **alle** organisatoren? Wat bij 50 toernooien tegelijk? Wat ziet de klant bij errors? Geen "werkt op mijn machine".

## Werkwijze + bug fix

LEES → DENK → DOE → DOCUMENTEER. Max 2 fix-pogingen, daarna verslag aan gebruiker (symptoom / waar gezocht / wat geprobeerd / hypothese).

## Bescherming bestaande code

`{{-- DO NOT REMOVE --}}` views niet aanraken zonder toestemming. Verwijder NOOIT UI-elementen die je niet begrijpt — lees eerst de feature-docs.

## Test-data discipline

Bugs nooit "wegwerken" door data handmatig recht te zetten. Code moet werken, niet de data toevallig goed staan.

## Kerndocs

- `laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md` — verplichte leesstof
- `laravel/docs/3-DEVELOPMENT/STABILITY.md` — error handling + Reverb-bescherming
- `laravel/docs/2-FEATURES/BETALINGEN.md` — Mollie + Stripe (Connect / Platform €0,50)
- `laravel/docs/2-FEATURES/CLASSIFICATIE.md` — poule-indeling
- Service-architectuur (Eliminatie/PouleIndeling/BlokMatVerdeling refactor): `.claude/context.md`

## Server-paden

Local `D:\GitHub\JudoToernooi\laravel` · Staging `/var/www/judotoernooi/repo-staging` · Production `/var/www/judotoernooi/repo-prod` (symlinks naar `/laravel`). Deploy = `git pull` in **repo pad**, niet in symlink. SSH 188.245.159.115.
