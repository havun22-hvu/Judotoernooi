---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-07-15
---

# JudoToernooi — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel:
> `HavunCore/docs/kb/standards/md-doc-grootte.md`.

**Branch:** main (enige branch, geen open PR's) · **Status:** stabiel, Laravel 12.62, scoreboard 1.1.6.
**Prod = staging = main** (15-07). Security-fixes staan live.

## Open — alleen jij kunt dit

| Wat | Details |
|-----|---------|
| **Prod bekijken na de deploy van 15-07** | Security-fixes, login-herbouw en de device-toegangen-fix staan live zonder dat je ze in de browser zag. Geverifieerd: homepage 200, `/api/scoreboard/event` 401 (geen 500), 0 alerts, log schoon. Terugweg: backup `judo_toernooi_handmatig_2026-07-15_16-57-43.sql.gz` |
| **Scoreboard end-to-end testen** | Nooit geverifieerd, en de security-fix raakt precies dat pad (`CheckScoreboardToken`, `DeviceToegang`, `ScoreboardController`). Een 401 op een leeg request bewijst dat de middleware leeft, niet dat een echte app een wedstrijd rond krijgt |
| **Device-sweep** | Fysieke sweep op je P10 — `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md` |
| **Stale blok-1-selecties op prod** | Mat 1 / test-toernooi-2026: wis ze via de amber banner (Blok 2 + Mat 1 → "Wis markeringen") |
| **Login/biometrie: nog gewenst?** | De login is 14-07 herbouwd op `patterns/havun-mobile-login.md` (`140045ab`). Twee open wensen van 26-06, mogelijk achterhaald: passkey-registratie alleen in account/`setup-pin` (niet op het loginscherm), en de smartphone-PWA beperken tot QR-scannen + intro |

## Open — te doen

- **CSP/HSTS-hardening** — uit de security-sweep van 25-06, bewust uitgesteld: vereist
  browser-verificatie en Chrome-integratie staat uit.
- ~~ShouldQueue voor MatUpdate/ScoreboardEvent~~ — **geschrapt 15-07, niet bouwen.** Drie redenen:
  (1) de worker draait `queue:work --sleep=3`, dus bij een lege queue — de normale toestand tussen
  twee scores — komt een score pas na gem. 1,5s / max 3s aan. Voor een scorebord is een verouderde
  score erger dan geen score. (2) De 8 `failed_jobs` van 04-04 wáren queued (`NewChatMessage`,
  `database@default`) en faalden tóch na 3 tries — de queue redde ze niet. (3) `ShouldQueue` zou de
  circuit breaker in `SafelyBroadcasts` betekenisloos maken: die zou dan het wegschrijven naar de
  `jobs`-tabel meten (lukt altijd) i.p.v. de broadcast, en de echte fout verdwijnt naar `failed_jobs`
  waar niemand kijkt. Het incident van 04-04 is al opgelost — met de trait, niet met de queue.
- **`REDUNDANTIE/ARCHITECTUUR.md` (9.2k)** is het laatste doc boven het KB-indexvenster: één
  ASCII-diagram van 84 regels — bewust heel gelaten, splitsen maakt het onleesbaar.

## Recent afgerond (context die nog nut heeft)

- **16-07 — genest `x-model` brak vier formulieren op staging/prod.** Symptoom: `Uncaught Error:
  Property assignments are prohibited in the CSP build` bij vrijwilliger toevoegen. Oorzaak: de
  `@alpinejs/csp`-evaluator staat `foo = x` (Identifier) toe maar gooit op `foo.bar = x`
  (MemberExpression) — en `x-model` compileert intern naar `<expressie> = __placeholder`. Dus élke
  `x-model="a.b"` is stuk zodra je typt. Werkte lokaal (strikte CSP staat uit in `local`).
  22 bindings over 4 views: vrijwilligers, clubs bewerken, stambestand, toernooi/mobiel — alle vier
  de toevoeg/bewerk-formulieren. Fix: getter/setter-methode per component (`nvModel`/`editModel`/
  `formModel`/`njModel`); Alpine's `x-model` honoreert een `{get, set}`-paar en parset de
  assignment-string dan nooit. Guard: `AlpineCspBindingTest` (statisch, scant alle blades) —
  geverifieerd dat hij op de oude code rood is. De e2e CSP-specs misten dit omdat die alleen
  page-load checken, niet interactie. Doc: `docs/alpine-csp-migration.md` → "De assignment-regel".

- **15-07 — alle MD-docs binnen het KB-indexvenster** (`34ce77ad`..`c6b9f517`). 23 docs → index (op
  de oude bestandsnaam, want code linkt erheen) + deeldocs in een gelijknamige map. Docs: 48 → ~190.
  Inhoud verhuisd, niet herschreven — elke kop geverifieerd tegen het origineel, geen dode links.
  **Les:** de norm is tekens, niet regels. De indexer embed de eerste 8000 tekens en halveert bij een
  context-error naar 4000/2000 (`HavunCore DocIndexer:123`, Ollama weigert te lange input i.p.v.
  afkappen). `OVERPOULEN.md` was 198 regels — binnen de norm — maar 12.411 tekens en dus grotendeels
  onvindbaar. Meet met `wc -c`, streef naar ~4000. Recept: `.claude/plan-md-splitsing.md`.
- **15-07 — prod-deploy** (`20ff55bb`). Prod liep 20 commits achter; de scoreboard-API-security van
  die ochtend stond een dag ongedeployd. Geen migraties, geen dependency-wijzigingen — pull +
  cache-clear.
- **15-07 — Device Toegangen mat-rij: één label per rij** (`a6d98d3d`, prod). Codes en knoppen waren
  twee losse kolommen naast elkaar, dus een schermregel las
  `Mat interface | HQ6QALCGS9AQ | LCD | Kort Volledig Koppel TV` — twee labels op één regel. Eerst
  de linkerkolom omdraaien hielp niet (label dubbel); de kolommen moesten samen. Nu één rij = label
  + code + knoppen. **Niet weer uit elkaar trekken.** LCD-QR weg: een TV heeft geen camera.
  Doc: `docs/2-FEATURES/SCOREBORD/TV-LCD-URLS.md`.
- **15-07 — Scoreboard-API security** (`f3445e46`, `34bd9549`, prod). Vier lekken: `/result` scoopte
  niet op het toernooi van het token (elk token kon élk toernooi schrijven); `/event` broadcastte het
  hele `DeviceToegang`-record incl. `api_token` op een publiek kanaal; **Reset nulde `api_token`
  niet** (gereset apparaat schreef door); geen rate limit → nu 120/min per token (niet per IP: één
  NAT-IP per zaal). Review:
  `HavunCore/docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`.
  **Bewust:** Reverb-kanalen blijven publiek (Henk: "prima, als je de url weet").
- **15-07 — `routes/channels.php` verwijderd + `CheckDeviceBinding` naar `attributes`**
  (`371c296f`, `18abd95b`). Het kanalenbestand werd nooit geladen en deed overal `return true` —
  het suggereerde autorisatie die er niet was. De middleware zette het model via `merge()` in de
  input bag: hetzelfde anti-patroon als het token-lek. 14 call-sites mee, 418 mat-tests groen.
- **03-07 — HavunClub-koppeling live op prod**: weegkaart-lookup, judoka-upsert, inschrijvingen,
  resultaten, school-portal. Contract: `HavunCore/docs/kb/contracts/havunclub-koppelingen.md`.

## Vaste context voor dit project

- Artisan altijd met `cd laravel &&` prefix.
- Auth guard is `organisator` — **niet** `web`.
- DB: SQLite lokaal, MySQL productie. **Nooit tests draaien op staging/productie.**
- Realtime via Reverb/WebSockets — geen polling. Alle kanalen zijn publiek, bewust.
- Deploy: `git pull` in het repo-pad (`/var/www/judotoernooi/repo-prod`), **niet** in de symlink.
  Migraties alleen bij expliciete input; auto-migrate op prod mag niet.
- **Alpine draait op de `@alpinejs/csp` build:** geen `Alpine.evaluate(el, string)`, wél
  `Alpine.$data(el).method()` of `x-on:event.window`. Geen compound `@click` (`x = 1; method()`)
  → altijd een aparte methode op de component.
- **CSS-bundle meecommitten** bij nieuwe Tailwind-classes: de oude bundle mist ze anders op prod
  (kostte een deploy op 25-06 en 13-07).
- **Request-scoped data hoort in `$request->attributes`**, nooit `merge()` — dat is de input bag.
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na een server-push.
