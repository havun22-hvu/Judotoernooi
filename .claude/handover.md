# Session Handover - JudoToernooi

> **Laatste update:** 6 april 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 6 april 2026 (avond)

### Wat is gedaan:

**Judoka Self-Check feature ontwerp**
- Feature-doc: `laravel/docs/2-FEATURES/JUDOKA-SELFCHECK.md`
- Judoka's kunnen gewicht + geboortejaar wijzigen via weegkaart PWA
- Organisator kiest bestemming: direct naar judokalijst of via coach portal (sync)
- Alleen actief als coach portal aanstaat
- Gewichtsmutatie deadline veld toegevoegd aan toernooi instellingen

**Security & code review**
- Auth + ownership check op TV link endpoint (was open voor iedereen)
- XSS fix: `@js()` voor toernooinaam in device-toegangen
- Dode poll endpoint verwijderd (Reverb handles dit)
- Max-iterations guard op TV code generatie
- LCD `match.start`/`match.assign` gededupliceerd naar `loadMatch()`

**Publieke app Reverb fix**
- mat-updates-listener gebruikte interne Reverb config (0.0.0.0:8081) i.p.v. app URL (443)
- WebSocket verbinding werkt nu correct — favorieten krijgen real-time updates

**UI fixes**
- LCD URL verborgen, alleen copy-knop (consistent met mat interface)
- Weegkaarten toggle tooltip toegevoegd
- Gewichtsmutatie deadline datumveld in toernooi instellingen

**Nginx redirects**
- `havun.nl/tv` → production (judotournament.org/tv)
- `havun.nl/tvs` → staging (staging.judotournament.org/tv)

### Openstaande items:
- [ ] Chromecast Cast testen (na 24u test device propagatie)
- [ ] Als Cast niet werkt: app publishen in Google Console
- [ ] Judoka Self-Check feature bouwen (doc staat klaar)
- [ ] LCD winnaar bug: opgelost in JudoScoreBoard app, verifiëren op staging
- [ ] Docs: TV/LCD setup handleiding voor organisatoren

### Belangrijke context:
- Service worker op staging kan oude views cachen → hard refresh (Ctrl+Shift+R) nodig na deploy
- Cast SDK werkt alleen in Chrome browser
- CLAUDE.md regel: NOOIT polling, ALTIJD Reverb
- Gewichtsmutatie deadline validatie: moet >= inschrijving_deadline en <= toernooidatum

---

## Vorige Sessie: 6 april 2026 (middag)

### Wat is gedaan:

**TV Koppelsysteem — werkend op staging**
- TV opent `havun.nl/tv` → toont 4-cijferige code
- Organisator voert code in bij device-toegangen → TV redirect via Reverb naar LCD scorebord
- TvKoppeling model, TvController, TvLinked broadcast event

**Google Cast integratie — in progress**
- Application ID: C11C3563 (unpublished)
- Test device: 26111HFDD5F9AN "Chromecast Henk"
- Cast Receiver pagina: `/cast/receiver`
- **Probleem:** `requestSession()` geeft "invalid_parameter" — wacht op 24u propagatie

**LCD scorebord**
- Auto-fullscreen overlay voor TV browsers

**Overige UI fixes**
- Emoji's → SVG, regenboog knoppen → uniform blauw
- Scorebord instelling: radio buttons
- Publieke app: positienummers → bandkleur

---

## Vorige Sessie: 5 april 2026 (avond)

### Wat is gedaan:

**UI professionalisering app-breed**
- 7 views: emoji's → SVG iconen, regenboog knoppen → uniform blauw
- Afsluiten: roze "Meisjes" → neutraal grijs
- Scorebord instelling: checkbox → radio buttons + hint "geldt vanaf volgende wedstrijd"
- Publieke app: positienummers (1-5) → bandkleur-bolletje

**LCD scorebord layout**
- Y/W/I scores halverwege timer hoogte (margin-top: -12vh, z-index layering)
- Shido's gecentreerd tussen scores en onderkant (margin-top/bottom: auto)
- Horizontale lijnen tussen Y/W/I score-boxen

**QR codes device-toegangen**
- QR knoppen per mat voor zowel Interface als LCD
- Server-side QR generatie via simplesoftwareio/simple-qrcode (was externe API)
- Strakke tabel-layout voor knoppen (Interface/LCD rijen)

**Reverb-staging herstart** — zombie proces op poort 8081

### Openstaande items:
- [ ] QR codes testen op staging (net gedeployed)
- [ ] Deploy naar production na goedkeuring QR functie
- [ ] Device-toegangen layout mogelijk nog fine-tunen

---

## Vorige Sessie: 5 april 2026 (middag)

### Wat is gedaan:

**1. SafelyBroadcasts trait collision gefixt**
- `SafelyBroadcasts::dispatch()` botste met `Dispatchable::dispatch()` → FatalError op ELKE broadcast
- Fix: `insteadof` syntax in alle 5 event classes (MatUpdate, ScoreboardEvent, ScoreboardAssignment, NewChatMessage, MatHeartbeat)
- Root cause: commit `3044e71f` (4 apr) hernoemde `safeBroadcast()` naar `dispatch()` zonder trait collision op te lossen

**2. LCD scoreboard env() → config() fix**
- `scoreboard-live.blade.php` gebruikte `env()` voor Reverb config → retourneert NULL na `config:cache`
- Fix: afgeleid van `config('app.url')` — werkt altijd

**3. LCD osaekomi dubbele entries gefixt**
- `renderOsaekomiTimes()` deduplicatie met `new Set()`

**4. STABILITY.md bijgewerkt (KB)**
- `insteadof` syntax als VERPLICHT gedocumenteerd met incident referentie
- Reverb Config Regels tabel toegevoegd (allowed_origins array, geen env() in Blade)
- Verwijzing naar postmortem, BroadcastConfigValidator, reverb:health, tests

**5. Memory bijgewerkt**
- `feedback_max-pogingen-echt-stoppen.md` — les: na 2 pogingen ECHT stoppen
- `feedback_lees-error-messages.md` — les: gelogde exception lezen, niet browser-symptoom

### FOUTEN DEZE SESSIE (zelfreflectie):
- 5+ debug pogingen i.p.v. max 2 → root cause gemist (allowed_origins als string)
- Nginx HTTP/2 als schuldige aangewezen (rode haring)
- Diagnose "bediening broadcast niets" was fout — andere sessie had dit al gebouwd
- Niet goed ingelezen in wat de andere sessie al had gedaan

### Openstaande items:
- [ ] **LCD winnaar overlay** — `match.end` event wordt niet naar LCD gestuurd na uitslag bevestiging. Timer/scores/osaekomi werken wél. Traceer waar de bevestiging vandaan komt en voeg ScoreboardEvent dispatch toe.
- [ ] **Deploy production** — categorie wedstrijdinstellingen + broadcast safeguards + trait collision fix
- [ ] Coverage naar 60% target (nu ~40% geschat, 397 tests)

### Bekende issues:
- 5 PHP security vulnerabilities (1 high phpunit, 4 medium)
- `staging_judo_toernooi.jobs` tabel ontbreekt op staging (queue errors in log)

### Belangrijke context voor volgende sessie:
- **Parallelle sessie** heeft Reverb broadcasting grotendeels gefixt (allowed_origins, SafelyBroadcasts error logging, circuit breaker reset, systemd/supervisor conflict). Zie postmortem in `docs/postmortem/`.
- **LCD scoreboard werkt** — namen, timer, scores, osaekomi komen door. Alleen winnaar overlay ontbreekt.
- **`insteadof` syntax** is nu verplicht bij SafelyBroadcasts — gedocumenteerd in STABILITY.md

---

## Vorige Sessies

### 4-5 april 2026 (ochtend/andere sessie)
- Reverb broadcasting failure: 5 fouten opgelost (allowed_origins, SafelyBroadcasts logging, circuit breaker, env(), systemd conflict)
- Safeguards: reverb:health command, BroadcastConfigValidator, ReverbConfigTest (9), ReverbHealthCheckTest (4)
- UI professionalisering, LCD scorebord layout, per-categorie wedstrijdinstellingen

### 1 april 2026
- Bug fix: Live Matten tab publieke PWA — ontbrekende `>` op div tag

### 31 maart 2026
- Biometrische login redirect + post-merge hooks

### Migraties (production):
Alle migraties gedraaid t/m batch 75 (4 april 2026).
