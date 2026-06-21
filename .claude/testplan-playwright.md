---
title: Playwright Testplan — functionele dekking
type: claude
scope: judotoernooi
last_updated: 2026-06-21
status: LAAG A AFGEROND — alle 5 flows groen (e2e/flows.auth.spec.ts)
---

# Playwright Testplan — functionele flows

> Doel: van "pagina laadt zonder JS/CSP-fouten" (huidige smoke-suite) naar
> "de app dóét het juiste" — uitslagen, standen, doorschuiven, weging.

## ✅ AFGEROND (21-06) — `e2e/flows.auth.spec.ts`, allemaal groen
- Judoka-CRUD (UI modal) · Uitslag→poulestand · Eliminatie-doorschuiven · Weging · Poule-generatie
- Seeder (`E2eTestSeeder`) bouwt round-robin poule + eliminatie-bracket, schrijft IDs naar
  `database/e2e-ids.json`; specs lezen via `seededIds()`, POSTen via in-page `fetch` (`postJson()`).
- **Resterend = laag B / out-of-scope hieronder** (DnD/kleurbeurt, realtime, facturen, betalingen).

## Huidige stand (baseline 20-06)

- 42 passed · 1 flaky · 2 failed (allebei de cspActions-race → aparte /arch-fix)
- Bestaande dekking = **smoke**: laadt + geen JS/CSP-errors. Geen enkele test
  voert een actie uit en controleert de uitkomst.

## Twee testlagen (bewuste keuze)

| Laag | Hoe | Vangt | Flakiness |
|------|-----|-------|-----------|
| **A. API-flow** | `request`-context met auth-storageState, POST/GET JSON | backend-correctheid end-to-end via HTTP | laag (deterministisch) |
| **B. UI-interactie** | klik echte `data-action`-knop, assert DOM-effect | frontend-wiring (cspActions, Alpine) | midden |

Reden voor mix: scoren via de echte mat-UI hangt op Reverb/Alpine (flaky).
De datapijplijn test ik deterministisch op laag A; de knop-wiring borg ik op
laag B (en dát vangt precies regressies als de cspActions-race).

## Geseede data (E2eTestSeeder)
1 organisator · 1 wedstrijddag-toernooi · 1 blok · 1 mat · 1 poule (5 judokas)
· 5 PWA-toegangscodes. Mogelijk uitbreiden met een 2e poule + eliminatie-poule.

---

## Laag A — API-flow tests (nieuw bestand: `flows.auth.spec.ts`)

### A1. Poule → wedstrijden genereren
- `POST /{org}/{toernooi}/poule/genereer` → redirect/success
- `POST /{org}/{toernooi}/poule/verifieer` → JSON met `totaal_poules`,
  `totaal_wedstrijden`, **`problemen: []`** (poule-regels-check, beschermd door
  13 PHPUnit-tests — e2e bevestigt de HTTP-laag)

### A2. Uitslag invoeren → poulestand klopt
- Genereer wedstrijden, pak 1 wedstrijd-id
- `POST /{org}/{toernooi}/mat/uitslag` met `winnaar_id`, `score_wit/blauw`,
  `uitslag_type=beslissing`, `updated_at` (optimistic lock)
- `POST /{org}/{toernooi}/spreker/standings` → winnaar heeft `wp: 2`, juiste `jp`
- **Edge:** verkeerde `updated_at` → 409 conflict

### A3. Eliminatie winnaar-doorschuiven
- Vereist geseede eliminatie-poule (groep A + `volgende_wedstrijd_id`)
- `POST .../mat/uitslag` op ronde-1 wedstrijd → assert winnaar staat in
  `volgende_wedstrijd_id`-slot, verliezer in `herkansing_wedstrijd_id` (B)
- Dekt de auto-doorschuif-feature (nu alleen PHPUnit)

### A4. Weging
- `POST /{org}/{toernooi}/weging/{judoka}/registreer` met `gewicht`
- → `success`, judoka `aanwezigheid=aanwezig`, `gewicht_gewogen` gezet
- **Edges:** `gewicht=0` → afwezig + uit poules; `gewicht<15` → foutmelding

## Laag B — UI-interactie tests (uitbreiden `authenticated.auth.spec.ts`)

### B1. judoka/index — knop wiring (vangt cspActions-race direct)
- Klik `data-action="open-add-judoka"` → modal `addJudokaModal` niet meer hidden
- Klik `data-action="open-stambestand"` → modal open + lijst laadt

### B2. wedstrijddag → "doorsturen naar zaaloverzicht"
- Henk's oorspronkelijke melding. Klik de doorstuur-knop → wedstrijden
  gegenereerd → zaaloverzicht-chip verschijnt

### B3. mat-interface — JP/WP dropdown
- Open een wedstrijd, kies uitslag in JP-dropdown (incl. G/W),
  assert score-weergave updatet (geen Reverb nodig voor lokale state)

## Out of scope (nu) — apart bespreken
- DnD poule-verplaatsing + kleurbeurt-gedrag (Alpine DnD = zeer flaky in e2e)
- Realtime cross-device broadcast (2 contexts + Reverb)
- Facturen/PDF-download, betalingen (Mollie/Stripe sandbox)

## Aanpak per test
1. Page Object / helper uitbreiden (selectors niet in spec)
2. Seeder evt. uitbreiden (2e poule, eliminatie-poule) — aparte commit
3. Per flow: groen lokaal vóór commit, geen `php artisan test` op server
4. Atomic commits: 1 flow = 1 commit

## Openstaand vóór bouw
- [ ] Akkoord Henk op scope (A1–A4 + B1–B3?)
- [ ] Seeder uitbreiden met eliminatie-poule? (nodig voor A3)
- [ ] cspActions-race eerst fixen (blokkeert B1 niet, maar geeft schone baseline)
