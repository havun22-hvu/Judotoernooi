---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-07-17
---

# JudoToernooi — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel:
> `HavunCore/docs/kb/standards/md-doc-grootte.md`.

**Branch:** main (enige branch, geen open PR's) · **Status:** stabiel, Laravel 12.62, scoreboard 1.1.6.
**Prod = staging = main** (17-07, `a5d1776c`). Geverifieerd op prod ná de band-migratie: 0 ontbrekende
banden, 0 niet-gecategoriseerd, 0 numerieke banden over, homepage 200, 0 alerts.
Terugweg: `judo_toernooi_voor-band-migratie_2026-07-16_23-33-57.sql.gz`.

## Open — alleen jij kunt dit

| Wat | Details |
|-----|---------|
| **Poules- en judoka-pagina bekijken (prod)** | Na 17-07: op poules hoort nog **één** rode banner te staan (was twee), en de gele "judoka's met ontbrekende gegevens"-balk hoort weg te zijn. Data zegt 0/0, maar de UI is niet door mij gezien |
| **Scoreboard end-to-end testen** | Nooit geverifieerd, en de security-fix raakt precies dat pad (`CheckScoreboardToken`, `DeviceToegang`, `ScoreboardController`). Een 401 op een leeg request bewijst dat de middleware leeft, niet dat een echte app een wedstrijd rond krijgt |
| **Device-sweep** | Fysieke sweep op je P10 — `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md` |
| **Stale blok-1-selecties op prod** | Mat 1 / test-toernooi-2026: wis ze via de amber banner (Blok 2 + Mat 1 → "Wis markeringen") |
| **Login/biometrie: nog gewenst?** | De login is 14-07 herbouwd op `patterns/havun-mobile-login.md` (`140045ab`). Twee open wensen van 26-06, mogelijk achterhaald: passkey-registratie alleen in account/`setup-pin` (niet op het loginscherm), en de smartphone-PWA beperken tot QR-scannen + intro |

## Open — te doen
- **Melding bij een te oude judoka is nietszeggend** — voorgesteld op 17-07, Henk heeft niet
  geantwoord. Nu: "Past niet in categorie (leeftijd 17)". Beter: "geen categorie voor 17 jaar —
  hoogste is 16". Dit kostte deze sessie een half onderzoek: het kwam binnen als "de mini's worden
  geweigerd" terwijl het de 17/18-jarigen waren. `Judoka.php:427-432`, `ImportService.php:740-744`.
- **`DynamischeIndelingService::bandNaarNummer()` (`:412-417`) heeft een stille `?? 0`-fallback**
  = wit. Met kleurnamen in de DB klopt het weer, maar élke onbekende bandnotatie wordt zonder
  melding een witte band en `max_band_verschil` doet dan niets. Niet aangeraakt: buiten de scope
  van de band-migratie.
- **Band-validatie accepteert nog alles** (`'band' => 'nullable|string|max:20'`, alle Form Requests).
  Bewust géén `in:wit,geel,...` toegevoegd: dat zou HavunClub-inschrijvingen met "Geel (5e kyu)"
  hard weigeren. De normalisatie via `ValueParser::parseBand()` is de tolerante variant.
- **CSP/HSTS-hardening** — uit de security-sweep van 25-06, bewust uitgesteld: vereist
  browser-verificatie en Chrome-integratie staat uit.
- **Feature-suite is onwerkbaar traag** (>15 min, geen uitkomst; Unit-suite is 1875 tests in 3:53 en
  groen). `php artisan test` spuwt duizenden "Het systeem kan het opgegeven pad niet vinden" en
  genereert ongevraagd coverage — gebruik `php vendor/bin/phpunit --testsuite=Unit --no-coverage`.
  Nooit uitgezocht waaróm; blokkeert wel elke volledige groen-check.
- **ShouldQueue voor broadcast events: geschrapt, niet opnieuw voorstellen.** Reden vastgelegd in
  `docs/3-DEVELOPMENT/STABILITY/CIRCUIT-BREAKER.md` → "Waarom geen ShouldQueue".
- **`REDUNDANTIE/ARCHITECTUUR.md` (9.2k)** is het laatste doc boven het KB-indexvenster: één
  ASCII-diagram van 84 regels — bewust heel gelaten, splitsen maakt het onleesbaar.
- **`docs/alpine-csp-migration.md` zit op 7.9k** — net onder de 8k-indexgrens, geen ruimte meer.
  Volgende toevoeging → eerst splitsen (index + deeldocs).

## Recent afgerond (context die nog nut heeft)
- **17-07 — zwart is enum value 0, en dat brak vier dingen tegelijk** (`f1213ff2`, `be2afa82`,
  `a5d1776c`, prod). Binnengekomen als "zwart wordt niet als band gezien": 21 judoka's stonden in
  "ontbrekende gegevens" terwijl de kolom "Zwart" toonde. `empty($judoka->band)` is waar voor `0`
  én `"0"`. Fix: `Band::isIngevuld()`; nooit meer `empty()` op een band. Meegepakt:
  `ValueParser::parseBand()` maakte van een geïmporteerde zwarte band stilzwijgend **wit**;
  `voerValidatieUit()` schreef `$enum->value` weg (= de bron van de `"0"`); de HavunClub-paden
  lieten de band ongefilterd door (nu genormaliseerd, **null blijft null** — anders overschrijft
  een inschrijving de band uit het stambestand). Alle 541 judoka's + 18 stam-records gemigreerd van
  nummers naar kleurnamen; die nummers kwamen uit het oude Google Apps Script (`wit=6 … zwart=0`).
  **Bijvangst die niemand zag:** met nummers in de DB viel `DynamischeIndelingService::bandNaarNummer()`
  voor élke waarde terug op wit → de poule-solver zag iedereen als witte band en `max_band_verschil`
  deed niets.
- **17-07 — de migratie was bijna een ramp; staging ving het.** `WHERE band = 0` (int) laat MySQL
  élke kleurnaam naar een getal casten → `'groen'` = 0 → de eerste ronde had alle 190 bestaande
  kleurnamen naar 'zwart' herschreven. Oorzaak: PHP cast numerieke array-keys stil naar int.
  Strict mode brak af, 0 rijen geraakt. **SQLite juggelt niet** → lokaal groen terwijl de migratie
  stuk was. Les: een data-migratie draai je op staging vóór prod, altijd, ook als de suite groen is.
- **17-07 — "de mini's worden geweigerd" was geen bug.** De 70 niet-gecategoriseerden waren de
  17/18-jarigen: de hoogste categorie stond op `max_leeftijd 16`. Mini's (2020-2022) werden keurig
  ingedeeld. Henk heeft zelf een categorie toegevoegd. Leeftijd = **kalenderjaar** (toernooijaar −
  geboortejaar), niet de leeftijd op de wedstrijddag: "tot 6 jaar" = geboren in 2020 of later.
- **17-07 — staffel 501-600 (€120)** (`7fde5288`, prod). De prijsregel is `max × €0,20` en gold al
  voor élke bestaande trede; nu vastgelegd in een test, net als de aaneensluiting van de tredes.
  `Toernooi::getStaffelPrijs()` had een eigen kopie van de lijst → las `null` voor nieuwe tredes,
  leest nu de const. Nieuwe trede = één regel in `FreemiumService::STAFFELS`, geen migratie
  (`tier` is een vrije string), UI vult zich dynamisch, staging rekent automatisch de helft.
- **16-07 — genest `x-model` brak vier formulieren op staging/prod.** Symptoom: `Uncaught Error:
  Property assignments are prohibited in the CSP build` bij vrijwilliger toevoegen. Oorzaak: de
  `@alpinejs/csp`-evaluator staat `foo = x` (Identifier) toe maar gooit op `foo.bar = x`
  (MemberExpression) — en `x-model` compileert intern naar `<expressie> = __placeholder`. Dus élke
  `x-model="a.b"` is stuk zodra je typt. Werkte lokaal (strikte CSP staat uit in `local`).
  Fix: getter/setter-methode per component (`nvModel`/`editModel`/`formModel`/`njModel`).
  Guard: `AlpineCspBindingTest` (statisch, scant alle blades). Doc: `docs/alpine-csp-migration.md`
  → "De assignment-regel". **17-07: categorie toevoegen via Instellingen werkte** — indirect bewijs
  dat de fix het doet, maar de vier formulieren zelf zijn nog niet stuk voor stuk getest.
- **15-07 — Scoreboard-API security** (`f3445e46`, `34bd9549`, prod). Vier lekken dicht (toernooi-scope
  op `/result`, `api_token` lekte via een publiek kanaal, Reset nulde het token niet, geen rate limit
  → nu 120/min per token, niet per IP: één NAT-IP per zaal). Review:
  `HavunCore/docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`.
  **Bewust:** Reverb-kanalen blijven publiek (Henk: "prima, als je de url weet").
- **03-07 — HavunClub-koppeling live op prod**: weegkaart-lookup, judoka-upsert, inschrijvingen,
  resultaten, school-portal. Contract: `HavunCore/docs/kb/contracts/havunclub-koppelingen.md`.

## Vaste context voor dit project
- Artisan altijd met `cd laravel &&` prefix.
- Auth guard is `organisator` — **niet** `web`.
- DB: SQLite lokaal, MySQL productie. **Nooit tests draaien op staging/productie.**
- Realtime via Reverb/WebSockets — geen polling. Alle kanalen zijn publiek, bewust.
- Deploy: `git pull` in het repo-pad (`/var/www/judotoernooi/repo-prod`), **niet** in de symlink.
  Migraties alleen bij expliciete input; auto-migrate op prod mag niet.
- **Alpine draait op de `@alpinejs/csp` build.** De assignment-regel (bron van meerdere bugs):
  `foo = x` op de **eigen** component werkt; `foo = x` op een **ancestor** faalt **stil**;
  `foo.bar = x` — élk pad met een punt — gooit `Property assignments are prohibited`. Dus geen
  `x-model="a.b"` (gebruik een `{get,set}`-methode) en geen `Alpine.evaluate(el, string)`; wél
  `Alpine.$data(el).method()` of `x-on:event.window`. Bij twijfel: methode op de component die de
  property bezit. Volledig: `docs/alpine-csp-migration.md` → "De assignment-regel".
  **Strikte CSP staat uit in `local`** → deze klasse bugs is lokaal onzichtbaar, test op staging.
- **CSS-bundle meecommitten** bij nieuwe Tailwind-classes: de oude bundle mist ze anders op prod
  (kostte een deploy op 25-06 en 13-07).
- **Request-scoped data hoort in `$request->attributes`**, nooit `merge()` — dat is de input bag.
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na een server-push.
