---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-07-22
---

# JudoToernooi — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels.

**Branch:** main (enige branch, geen open PR's) · **Status:** Laravel 12.62, scoreboard 1.1.6.
**Prod = staging = main (22-07, alles synchroon, geen branches/PR's).** Hele stapel live op
productie: eliminatie/tenant/scoreboard-fixes + scoreboard groene-vlag gate + poule-scoring
CSP-fix + spreker-volgorde-fix (afgeronde poules op afrondtijd i.p.v. poule-nummer, via
`reorder`; `SprekerVolgordeTest`). Live Stripe-sleutel 19-07 geroteerd na een chat-lek —
afronding hieronder.

## Open — alleen jij kunt dit

| Wat | Details |
|-----|---------|
| **Scoreboard client-side reload (jouw project)** | Server stuurt nu een verse `ScoreboardAssignment` (`event: match.assign`) bij deelnemer-wijziging én een `green-check`-endpoint (live op prod). In de Android-app moet een nieuwe assign de al-getoonde wedstrijd **vervangen** i.p.v. negeren, en de green-check + 409 `niet_groen` afhandelen (contract: `.claude/plan-scoreboard-groen-gate.md`). |
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
- **Deelnemers-tab herstructureren naar geneste accordions (MPC, fase 1 — 21-07).** Henk wil:
  categorie in/uitklapbaar (bestaat), en bij vaste gewichtsklassen **elke gewichtsklasse óók
  in/uitklapbaar** (nu een knoppen-balk met single-select via `nullableSelection`/`openGewicht`
  → vervangen door geneste inklap-headers). Zoeken (server-side over álle judoka's) blijft. Lost
  vermoedelijk meteen de **H-15/D-15-klapbug** op (`.claude/plan-deelnemers-tab.md`, symptoom 1:
  x-collapse-cache/CSP/nested-x-data hypotheses — niet los fixen, meenemen in de redesign; pas op
  dezelfde x-collapse-valkuil niet te herhalen). Huidige structuur: `index.blade.php:572-701`.
  **Wacht op 3 antwoorden van Henk vóór docs+plan:** (1) meerdere gewichtsklassen tegelijk open of
  één tegelijk? (2) beginstand categorieën én gewichtsklassen open/dicht? (3) dynamische categorie
  blijft platte lijst zonder extra laag — akkoord?

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
  - **Geen compound `@`-handler met een methode-call + `;`** (`updateJP(...); saveScore(...)` óf
    `activeTab='x'; loadX()` faalt stil op CSP) → wrapper-methode. Guard: `AlpineCspBindingTest`
    (`no_event_handler_chains_a_method_call_with_a_semicolon`).
  - **Geen `Alpine.evaluate(el, string)`** → `Alpine.$data(el).method()` of `x-on:event.window`.
- **CSS-bundle meecommitten** bij nieuwe Tailwind-classes: de oude bundle mist ze anders op prod.
- **Request-scoped data hoort in `$request->attributes`**, nooit `merge()` — dat is de input bag.
- **`APP_TIMEZONE=UTC` (default)** → alle `->format('H:i')` op DB-timestamps geven UTC-tijd. Zie
  open-punt hierboven; nog niet opgelost.
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na een server-push.
</content>
</invoke>