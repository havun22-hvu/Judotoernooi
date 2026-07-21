---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-07-21
---

# JudoToernooi — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels.

**Branch:** main (enige branch, geen open PR's) · **Status:** Laravel 12.62, scoreboard 1.1.6.
**Prod = staging = main** qua code. **Open regressie (21-07): favorieten-tab op de publieke
live-app rendert leeg terwijl endpoint + state kloppen — zie eerste open punt.**
Live Stripe-sleutel 19-07 geroteerd na een lek via de chat — afronding hieronder.

## Open — alleen jij kunt dit

| Wat | Details |
|-----|---------|
| **Tijdzone-keuze `APP_TIMEZONE`** | LCD toonde 16:57 CEST i.p.v. 18:57. Systemisch: `APP_TIMEZONE=UTC` op alle `->format('H:i')` op DB-timestamps. Twee opties: (a) `.env` op prod+staging → `APP_TIMEZONE=Europe/Amsterdam` (jij, wachtwoord/prod-access), (b) hardcoded in `config/app.php` (ik, één commit). Optie a is flexibeler voor multi-timezone SaaS ooit; b is meteen klaar. Backup vóór (`cp .env .env.bak.YYYYMMDD`). |
| **Stripe: afronden na key-lek (morgen)** | De live secret is 19-07 al geroteerd — prod draait op `sk_live_…dkIA3`, API geeft HTTP 200, config gecached, staging op testsleutels. Nog te doen: (1) oude `…4l13` in het Stripe-dashboard écht ingetrokken zien (was met 24u-expiry gerold), (2) Developers → Logs nakijken op requests met `…4l13` vanaf een ander IP dan `188.245.159.115`, (3) verouderde Stripe-regel in `HavunCore/.claude/credentials.md` opruimen. Lek liep via de chat, niet via git (`credentials.md` is gitignored, nooit in history). |
| **Stripe webhook-secret: rollen? (morgen)** | `STRIPE_WEBHOOK_SECRET` (`whsec_…anY7Q`) is **niet** meegerold met de key. Vraag: is die ooit door de chat gegaan? Zo ja rollen — met die secret kan iemand valse webhooks vervalsen en betalingen als betaald markeren. App verifieert via `\Stripe\Webhook::constructEvent()` met één secret uit config (`StripePaymentProvider.php:292`). Stripe's "roll" houdt de oude tot expiry geldig, dus de nieuwe direct plaatsen geeft géén gat. Script nog te schrijven, patroon van `HavunCore/scripts/rotate-stripe-secret.sh` (verborgen `read -rs`, jij draait 'm). `pk_live_` is publishable, geen actie. |
| **Poules- en judoka-pagina bekijken (prod)** | Na 17-07: op poules hoort nog **één** rode banner te staan (was twee), en de gele "judoka's met ontbrekende gegevens"-balk hoort weg te zijn. Data zegt 0/0, maar de UI is niet door mij gezien. |
| **Scoreboard end-to-end testen** | Nooit geverifieerd. Een 401 op een leeg request bewijst dat de middleware leeft, niet dat een echte app een wedstrijd rond krijgt. |
| **Device-sweep** | Fysieke sweep op je P10 — `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md`. |
| **Stale blok-1-selecties op prod** | Mat 1 / test-toernooi-2026: wis ze via de amber banner (Blok 2 + Mat 1 → "Wis markeringen"). |
| **Login/biometrie: nog gewenst?** | De login is 14-07 herbouwd op `patterns/havun-mobile-login.md`. Twee open wensen van 26-06, mogelijk achterhaald: passkey-registratie alleen in account/`setup-pin` (niet op het loginscherm), en de smartphone-PWA beperken tot QR-scannen + intro. |
| **Favorieten-meldingen op Android** | Feature is live, maar knop "Aanzetten" deed op de tablet ogenschijnlijk niets. Er is nu een zichtbare `notificatieStatus`-regel — die wijst de oorzaak aan zodra jij het hertest. |

## Open — te doen
- **Favorieten-tab render leeg op staging (21-07 avond).** Endpoint werkt (POST retourneert
  2 poules met `is_favoriet:true` bij Bram + Emma), state klopt (`toonFavorietPoules` = true,
  container zonder `display:none` in DevTools), maar op scherm alleen de tabjes Bram/Emma —
  geen alerts, geen poule-kaart. Twee losse Alpine expression-errors uit de scorebord-picker
  zijn vandaag verhard (`mat.groen.wit`-guards + JS-methoden `matPreviewTekst`) maar dat
  fixte de tab niet. Volgende stap: klap `<template x-for="poule in favorietenPoules.filter(...)">`
  in DevTools open en kijk of Alpine 'm rendert (verwacht 1 kind voor Bram als
  activeFavoriet). Verdacht: `activeFavoriet` blijft null → filter geeft niks. Check
  `$watch('favorietenPoules', ...)` + `getFirstFavorietId()` in `index.blade.php:1264-1270`.

- **Gewicht + geslacht overal verplicht (21-07) — nog niet in de browser gezien.** Alle
  invoerpaden eisen ze nu, import keurt per rij af (bestand loopt door), en `JudokaGrouper` sluit
  gewichtloze judoka's uit met een melding. Doc: `2-FEATURES/JUDOKA-GEWICHT-VERPLICHT.md`.
  **Te beoordelen op staging:** coach-portaal (nieuw + inline bewerken), Laatkomer-modal op
  poules-pagina, mobiele toevoeg-modal, en een import met een rij zonder gewicht.
  **Let op bestaande data:** toernooien met judoka's zonder gewicht krijgen die nu niet meer
  ingedeeld — ze verschijnen als waarschuwing bij het genereren. Dat is de bedoeling (ze belandden
  eerst in een "Onbekend"-poule), maar het kan bij een lopend toernooi verrassen.
- **`mat_label` bestaat niet in de favorieten-payload.** `index.blade.php:1556/1561` geven
  `poule.mat_label` mee aan `stuurNotificatie()`, maar `PubliekController.php:460` emit
  `'mat' => $mat?->nummer`. Altijd `undefined` → push-melding zegt "Nu op **de mat**" i.p.v.
  "Nu op Mat 3". Accessor `Mat::label` bestaat en is getest. Naam in de melding klopt wél.
- **Dode payload in favorieten-endpoint:** `huidige_wedstrijd`/`volgende_wedstrijd`/
  `gereedmaken_wedstrijd` (`PubliekController.php:463-474`) bevatten alleen ID's en worden in de
  frontend nergens gelezen. Namen komen uit de `judokas`-array.
- **Melding bij een te oude judoka is nietszeggend.** Nu: "Past niet in categorie (leeftijd 17)".
  Beter: "geen categorie voor 17 jaar — hoogste is 16". `Judoka.php:427-432`,
  `ImportService.php:740-744`. Kostte een halve sessie omdat het binnenkwam als "de mini's worden
  geweigerd" terwijl het de 17/18-jarigen waren.
- **`DynamischeIndelingService::bandNaarNummer()` (`:412-417`) heeft een stille `?? 0`-fallback**
  = wit. Met kleurnamen in de DB klopt het weer, maar élke onbekende bandnotatie wordt zonder
  melding een witte band en `max_band_verschil` doet dan niets.
- **Band-validatie accepteert nog alles** (`'band' => 'nullable|string|max:20'`, alle Form Requests).
  Bewust géén `in:wit,geel,...` toegevoegd: dat zou HavunClub-inschrijvingen met "Geel (5e kyu)"
  hard weigeren. `ValueParser::parseBand()` is de tolerante variant.
- **CSP/HSTS-hardening** — uit de security-sweep van 25-06, bewust uitgesteld: vereist
  browser-verificatie en Chrome-integratie staat uit.
- **Feature-suite is onwerkbaar traag** (>15 min, geen uitkomst; Unit-suite is 1875 tests in 3:53 en
  groen). `php artisan test` spuwt duizenden "Het systeem kan het opgegeven pad niet vinden" en
  genereert ongevraagd coverage — gebruik `php vendor/bin/phpunit --testsuite=Unit --no-coverage`.
  Nooit uitgezocht waaróm; blokkeert wel elke volledige groen-check.
- **ShouldQueue voor broadcast events: geschrapt, niet opnieuw voorstellen.** Reden vastgelegd in
  `docs/3-DEVELOPMENT/STABILITY/CIRCUIT-BREAKER.md` → "Waarom geen ShouldQueue".
- **`docs/alpine-csp-migration.md` zit op 7.9k** — net onder de 8k-indexgrens, geen ruimte meer.
  Volgende toevoeging → eerst splitsen (index + deeldocs).
- **Deploy-race (18-07):** een parallelle sessie kan een commit pushen die net na jouw deploy op
  prod aankomt. Mijn CSS-commits waren gedeployed, een derde commit met de bijhorende controller-
  fix (`initieleWedstrijdtijd`) stond op main maar niet in `repo-prod` → 500 op elke scoreboard-
  request. `git pull` op beide repo's fixt het; bij elke deploy dus altijd `pull` doen op
  `repo-prod` én `repo-staging`, ook als je "denkt" dat je al up-to-date bent.

## Vaste context voor dit project
- Artisan altijd met `cd laravel &&` prefix.
- Auth guard is `organisator` — **niet** `web`. Voor tests: `actingAs($org, 'organisator')`.
- DB: SQLite lokaal, MySQL productie. **Nooit tests draaien op staging/productie.**
- Realtime via Reverb/WebSockets — geen polling. Alle kanalen zijn publiek, bewust.
- Deploy: `git pull` in het repo-pad (`/var/www/judotoernooi/repo-prod`), **niet** in de symlink.
  Bij deploy altijd óók `repo-staging` pullen; een derde-sessie kan een commit tussen jouw commits
  hebben gestopt (zie deploy-race hierboven). Migraties alleen bij expliciete input; auto-migrate op
  prod mag niet.
- **Alpine draait op de `@alpinejs/csp` build.** Strikte CSP staat uit in `local` → deze klasse
  bugs is lokaal onzichtbaar, test op staging. Regels — zie `[[csp-alpine-gotchas]]` memory voor de
  hele reeks; kernpunten:
  - **`foo = x`** op eigen component werkt; op ancestor faalt stil; `foo.bar = x` (elk pad met een
    punt) gooit `Property assignments are prohibited`.
  - **Geen `x-model="a.b"`** → getter/setter-methode (`nvModel`/`formModel`).
  - **Geen `?.` in Alpine-expressies** — de parser gooit, hele render stopt. Gebruik een klassieke
    ternary of een component-methode. Guard: `AlpineCspBindingTest` (statische blade-scan).
  - **Geen compound `@click` met assignment + methode** (`activeTab='x'; loadX()` faalt stil) →
    wrapper-methode.
  - **Geen `Alpine.evaluate(el, string)`** → `Alpine.$data(el).method()` of `x-on:event.window`.
- **CSS-bundle meecommitten** bij nieuwe Tailwind-classes: de oude bundle mist ze anders op prod.
- **Request-scoped data hoort in `$request->attributes`**, nooit `merge()` — dat is de input bag.
- **`APP_TIMEZONE=UTC` (default)** → alle `->format('H:i')` op DB-timestamps geven UTC-tijd. Zie
  open-punt hierboven; nog niet opgelost.
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na een server-push.
</content>
</invoke>