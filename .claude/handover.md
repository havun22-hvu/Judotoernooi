---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-07-18
---

# JudoToernooi — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel:
> `HavunCore/docs/kb/standards/md-doc-grootte.md`.

**Branch:** main (enige, geen open PR's) · **Status:** stabiel, Laravel 12.62, scoreboard 1.1.6.
**Prod = staging = main = `47c2142c`.** 17/18-07 ging een reeks fixes live rond de publieke
favorieten-tab, scorebord/LCD, TV-koppelen, device-binding en eliminatie-byes (zie git). Terugweg
band-migratie: `judo_toernooi_voor-band-migratie_2026-07-16_23-33-57.sql.gz`.

## Open — alleen jij kunt dit (test op prod, tablet)

| Wat | Details |
|-----|---------|
| **Favorieten-tab end-to-end** | Net gefixt (`?.` brak de kaart zodra een favoriet op de mat kwam). Ververs één keer; Abel/Zoë horen nu hun komende partij/klaar-staan te tonen — round-robin én eliminatie. Nog niet door mij op een echt toestel gezien |
| **Byes-knop** | `f1788c86`: knop hoort te verdwijnen zodra de eerste échte wedstrijd gespeeld is. Speel een paar partijen in een eliminatie-poule en check dat de knop weg is (en geen ronde-2-judoka meer doorschuift) |
| **Scoreboard-app end-to-end** | Nooit met een echte app geverifieerd; de security-fix (15-07) raakt dat pad. Een 401 op een leeg request bewijst alleen dat de middleware leeft |
| **Poules-/judoka-pagina (prod)** | Sinds de band-migratie: op poules hoort nog **één** rode banner (was twee) en de gele "ontbrekende gegevens"-balk hoort weg. Data zegt 0/0, UI niet door mij gezien |
| **Device-sweep** | Fysieke sweep op je P10 — `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md` |
| **Login/biometrie: nog gewenst?** | Login is 14-07 herbouwd (`140045ab`). Twee open wensen van 26-06, mogelijk achterhaald: passkey-registratie alleen in account/`setup-pin`, en de smartphone-PWA beperken tot QR-scannen + intro |

## Open — te doen
- **Melding bij een te oude judoka is nietszeggend** — nu "Past niet in categorie (leeftijd 17)".
  Beter: "geen categorie voor 17 jaar — hoogste is 16". `Judoka.php:427-432`,
  `ImportService.php:740-744`. Henk had hier nog niet op geantwoord.
- **`DynamischeIndelingService::bandNaarNummer()` (`:412-417`) heeft een stille `?? 0`-fallback**
  = wit. Elke onbekende bandnotatie wordt zonder melding wit en `max_band_verschil` doet dan niets.
- **Band-validatie accepteert nog alles** (`'band' => 'nullable|string|max:20'`). Bewust géén
  `in:wit,geel,…`: dat zou HavunClub-inschrijvingen met "Geel (5e kyu)" weigeren; `ValueParser::parseBand()`
  is de tolerante normalisatie.
- **CSP/HSTS-hardening** — uit de security-sweep van 25-06, bewust uitgesteld (vereist
  browser-verificatie, Chrome-integratie staat uit).
- **Feature-suite is onwerkbaar traag** (>15 min, geen uitkomst). `php artisan test` spuwt duizenden
  "pad niet vinden" + ongevraagde coverage — gebruik `php vendor/bin/phpunit --testsuite=Unit
  --no-coverage` (of losse bestanden). Nooit uitgezocht waaróm.
- **ShouldQueue voor broadcast events: geschrapt, niet opnieuw voorstellen.** Reden:
  `docs/3-DEVELOPMENT/STABILITY/CIRCUIT-BREAKER.md` → "Waarom geen ShouldQueue".
- **`docs/alpine-csp-migration.md` zit op 7.9k** — net onder de 8k-KB-indexgrens. Volgende
  toevoeging → eerst splitsen (index + deeldocs). `REDUNDANTIE/ARCHITECTUUR.md` (9.2k) staat er
  bewust boven: één ASCII-diagram, splitsen maakt het onleesbaar.

## Vaste context voor dit project
- Artisan altijd met `cd laravel &&` prefix.
- Auth guard is `organisator` — **niet** `web`. Kaal `->middleware('auth')` = default guard `web` =
  niemand ingelogd (kostte 17-07 de TV-koppel-route). `$request->user()` idem → `auth('organisator')->user()`.
- DB: SQLite lokaal, MySQL productie. **Nooit tests draaien op staging/productie.** Een data-migratie
  altijd eerst op staging: MySQL cast VARCHAR naar getal bij `WHERE col = 0`, SQLite niet → lokaal
  groen ≠ MySQL-veilig.
- Realtime via Reverb/WebSockets — geen polling. Alle kanalen zijn publiek, bewust.
- Deploy: `git pull` in het repo-pad (`/var/www/judotoernooi/repo-prod`), **niet** in de symlink.
  Migraties alleen bij expliciete input; auto-migrate op prod mag niet.
- **Alpine draait op de `@alpinejs/csp` build** — lokaal onzichtbare bugklasse (strikte CSP uit in
  `local`), test op staging. Twee terugkerende breakers: (1) `foo.bar = x` / `x-model="a.b"` gooit
  `Property assignments are prohibited`; `foo = x` op een **ancestor** faalt stíl. (2) **optional
  chaining `?.` in een Alpine-expressie gooit en stopt de hele render.** Beide → een component-methode
  (in JS mag alles). Guard: `AlpineCspBindingTest` (scant alle blades op x-model-paden én `?.`).
  Volledig: [[csp-alpine-gotchas]] + `docs/alpine-csp-migration.md`.
- **CSS-bundle meecommitten** bij nieuwe Tailwind-classes: de oude bundle mist ze anders op prod.
- **Request-scoped data hoort in `$request->attributes`**, nooit `merge()` (= de input bag).
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na een server-push.
