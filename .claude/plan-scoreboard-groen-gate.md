---
title: Plan — Scoreboard groene-vlag gate
type: plan
scope: judotoernooi
created: 2026-07-22
status: wacht op "ga maar" (contract vastgesteld met judoscoreboard 22-07)
---

# Plan — Scoreboard groene-vlag gate

Gedeeld contract tussen **judoscoreboard (Android-app)** en **JudoToernooi (server)**.
Dit bestand is de bron-van-waarheid; door te sturen naar de app-kant.

## Probleem (Vince-incident, 22-07 staging)

Organisator herschikt de mat-beurt: een wedstrijd gaat van 🟢 groen (op de mat) naar
🔵 blauw (klaarmaken). De scoreboard-app toont 'm nog als groen. Wordt die stale wedstrijd
gescoord, dan schrijft de server de uitslag weg en draait `EliminatieService::verwerkUitslag`
de bracket-doorschuif voor een wedstrijd die niet aan de beurt is → corrupte doorschuif.

## Domeinmodel (bevestigd in `Mat.php:37-144`, `ScoreboardNotifier.php:13`)

| Kleur | Mat-pointer | Betekenis |
|-------|-------------|-----------|
| 🟢 groen | `actieve_wedstrijd_id` | speelt nu — op de mat |
| 🟡 geel | `volgende_wedstrijd_id` | volgende |
| 🔵 blauw | `gereedmaken_wedstrijd_id` | klaarmaken |

**Groen = `mat.actieve_wedstrijd_id`.** Dat is de enige "aan de beurt".

## Gate-semantiek (belangrijk)

`result()` verwerkt óók **correcties** van al-gespeelde wedstrijden (`verwerkUitslag` met
`oudeWinnaarId`, `ScoreboardController.php:179`). Een naakte "alleen groen"-gate zou die
blokkeren. Daarom:

> **Toestaan:** wedstrijd is groen (`actieve_wedstrijd_id`) **OF** al gespeeld (`is_gespeeld`).
> **Weigeren (409 niet_groen):** niet-groen **én** niet-gespeeld. ← exact het Vince-geval.

Eén predicate, één plek: `Mat::mUitslagToegestaan(Wedstrijd $w): bool` (of `isGroen()` +
losse played-check in `result()`). Hergebruikt door zowel green-check als de result()-gate.

## Contract

### 1. `GET /api/scoreboard/green-check?wedstrijd_id={id}`

Auth: Bearer token (`scoreboard.token` middleware), zelfde prefix/throttle als de rest.
Mat komt uit de token — nooit uit de body.

Validatie: `wedstrijd_id` required|exists. Wedstrijd van een ánder toernooi → **404**
(tenant-isolatie, net als `result()`).

Response (200):
```json
// groen
{ "groen": true,  "actieve_wedstrijd_id": 1234 }

// niet groen → nieuwe groene match meegestuurd (formatMatch, zelfde als current-match)
{ "groen": false, "actieve_wedstrijd_id": 1290, "reden": "niet_groen",
  "match": { "id": 1290, "judoka_wit": {…}, "judoka_blauw": {…}, "poule_naam": "…", … } }

// geen actieve wedstrijd op de mat
{ "groen": false, "actieve_wedstrijd_id": null, "reden": "geen_actieve_wedstrijd",
  "match": null }
```

De app krijgt in één call het harde oordeel + de data voor melding en "wissel naar juiste
wedstrijd"-knop. Geen tweede call naar `current-match` nodig.

### 2. `POST /api/scoreboard/result` — gate erbij

Vóór er iets wordt geschreven: als de gate-semantiek hierboven de wedstrijd weigert →
**409** (consistent met de bestaande optimistic-lock-409):
```json
{ "error": "niet_groen", "actieve_wedstrijd_id": 1290 }
```

Onderscheidend van de bestaande optimistic-lock-409, die een ándere body heeft
(`{ "success": false, "message": …, "server_updated_at": … }`). De app vertakt op de
`error`-key. De niet_groen-gate draait **vroeg**, vóór de optimistic-lock en vóór de write.

### 3. App-kant (judoscoreboard's commitments — hier vastgelegd)

- green-check async bij de **eerste timer-start**; timer loopt meteen door.
  Bij `groen:false` → timer stoppen + melding/wissel tonen.
- **fail-open** op netwerkfout van green-check (een wedstrijd mag niet ophouden op een
  trage server). De result()-gate is het harde vangnet.
- result() 409 `niet_groen` → **permanent**: nooit queuen/herproberen, aparte NL-melding,
  scheids naar de juiste wedstrijd. (5xx/netwerk → wél offline-queue + retry.)

## Implementatie (server)

1. **Predicate** — `Mat.php`: methode die `result()` en green-check delen. Groen OF gespeeld.
2. **green-check** — nieuwe `ScoreboardController::greenCheck()`; hergebruikt `formatMatch()`
   (met eager-load `actieveWedstrijd.judokaWit.club`, `.judokaBlauw.club`, `.poule` — zoals
   `currentMatch()` al doet). Route in de bestaande `scoreboard`-prefix-groep (`api.php:43-49`).
3. **result()-gate** — vroege check bovenaan `result()` (na tenant-check, vóór optimistic-lock),
   409 `{ error: niet_groen, actieve_wedstrijd_id }`.
4. **Doc** — `SCOREBORD/ARCHITECTUUR.md`: green-check endpoint + de twee 409-varianten in de
   result()-responsetabel.

## Tests (`ScoreboardApiTest` / feature)

- green-check: groene wedstrijd → `groen:true`.
- green-check: blauw/geel wedstrijd → `groen:false` + `match` payload van de groene.
- green-check: mat zonder actieve wedstrijd → `groen:false`, `match:null`.
- green-check: wedstrijd ander toernooi → 404.
- result()-gate: niet-groen + niet-gespeeld → 409 `niet_groen`, **geen** DB-write, **geen**
  `verwerkUitslag`.
- result()-gate: groene wedstrijd → 200 (normale flow blijft werken).
- result()-gate: al-gespeelde wedstrijd (correctie) → 200 (correctie blijft werken).
- Bestaande result()-tests blijven groen.

## Open te verifiëren tijdens bouw

- Doet de app ooit een result()-resubmit op een wedstrijd die niet-groen **en** niet-gespeeld
  is anders dan het Vince-geval? Zo nee → gate zoals boven. Zo ja → apart bespreken.
- Poule-matches (niet-eliminatie): de app speelt altijd de groene, dus de gate klopt. De
  web-`MatUitslagController` gaat níét via deze endpoint → organisator-invoer onaangetast.
  Verifiëren dat er geen andere caller van `/api/scoreboard/result` is.

## Rollback

Additief (nieuwe endpoint) + één gate-check in `result()`. Bij regressie: `git revert` op de
fix-commit; de app valt terug op de oude flow (green-check faalt fail-open, gate weg).
