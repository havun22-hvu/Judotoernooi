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

## Havun Standaarden (verplicht — zie HavunCore KB)

Bij elke code-wijziging gelden de centrale Havun-normen. Lees bij twijfel de relevante doc:

| Norm | Centrale doc |
|------|-------------|
| 6 Onschendbare Regels | `HavunCore/CLAUDE.md` |
| Auth-standaard (magic + bio/QR + wachtwoord-optin) | `HavunCore/docs/kb/reference/authentication-methods.md` |
| Test-quality policy (kritieke paden 100 %, MSI ≥ 80 %) | `HavunCore/docs/kb/reference/test-quality-policy.md` |
| Quality standards (>80 % coverage nieuwe code, form requests, rate-limit) | `HavunCore/docs/kb/reference/havun-quality-standards.md` |
| Productie-deploy eisen (SSL/SecHeaders/Mozilla/Hardenize/Internet.nl) | `HavunCore/docs/kb/reference/productie-deploy-eisen.md` |
| V&K-systeem (qv:scan + qv:log) | `HavunCore/docs/kb/reference/qv-scan-latest.md` |
| Test-repair anti-pattern (VP-17) | `HavunCore/docs/kb/runbooks/test-repair-anti-pattern.md` |
| Universal login screen | `HavunCore/docs/kb/patterns/universal-login-screen.md` |
| Werkwijze + beschermingslagen + DO NOT REMOVE | `HavunCore/docs/kb/runbooks/claude-werkwijze.md` |

> **Bij twijfel:** `cd D:/GitHub/HavunCore && php artisan docs:search "<onderwerp>"`
