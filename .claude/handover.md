# Session Handover - JudoToernooi

> **Laatste update:** 9 maart 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## ⚡ EERSTVOLGENDE OPDRACHT: Free Tier Implementatie

**Freemium model is geherdefinieerd (9 mrt 2026). Docs: `laravel/docs/2-FEATURES/FREEMIUM.md`**

### Wat klaar is:
- `FREEMIUM.md` bijgewerkt met nieuwe free tier flow
- Demo CSV's gegenereerd (`storage/app/demo/demo-30/40/50.csv`)
- `FreemiumService` constanten: `FREE_MAX_EIGEN_IMPORT=20`, `FREE_MAX_HANDMATIG=20`
- `is_demo` veld + migratie op judokas tabel
- `getDemoCsvPath()` methode

### Nog te bouwen (4 taken):

1. **Download route voor demo CSV's**
   - `GET /{org}/toernooi/{toernooi}/demo-csv/{variant}` (30/40/50)
   - Alleen voor free tier toernooien
   - Retourneert CSV als download

2. **Import pagina UI aanpassen**
   - Free tier: toon demo CSV download knoppen (30, 40, 50)
   - Free tier: eigen CSV upload beperkt tot max 20 judoka's
   - Free tier: melding "Upgrade voor onbeperkte import"
   - Betaald: normale import (geen limiet)

3. **Import limiet enforcement**
   - `JudokaController::import()` — check free tier max 20 eigen import
   - `JudokaController::store()` / `CoachPortalController` — check max 20 handmatig
   - Bestaande `canAddMoreJudokas()` check (totaal max 50) blijft

4. **Testen**
   - Free tier: demo CSV import werkt
   - Free tier: eigen CSV >20 rijen wordt geblokkeerd
   - Free tier: handmatig >20 wordt geblokkeerd
   - Upgrade: alle limieten opgeheven

### Daarna: Sprint 1 Code Hardening

Code audit rapport: `.claude/code-review-2026-02-14.md`

1. Health endpoint auth (`/health/detailed`)
2. Hardcoded wachtwoord defaults (`config/toernooi.php`)
3. Dode `api.php` verwijderen
4. DB transactions in WedstrijddagController

---

## 🚀 Quick Start

**Lees in volgorde:**

1. `CLAUDE.md` - Project regels en conventies
2. `.claude/handover.md` - Dit bestand (algemeen overzicht)
3. `.claude/code-review-2026-02-14.md` - **Code audit rapport met Sprint 1-5**
4. `.claude/handover/2026-02-02-10plus-production.md` - Actuele status & planning

---

## Laatste Sessies

| Datum | Onderwerp | Handover |
|-------|-----------|----------|
| **22 mrt 2026** | **Scoreboard ↔ Mat koppeling + Reverb fix + UI fixes.** Poule sortering fix. Scoreboard auth met mat URL+pincode. Reverb mat listener bug gefixt. Staging rsync. Scoreboard API config `env()`. **Spreker tabs** vaste breedte (max-w-4xl). **Mat selectie bug:** `isEchtGespeeld` skip voor bestaande selectie-items. **Reverb race condition:** matSelectie direct uit event data bij beurt update. Scoreboard backend deployed staging. | smallwork.md |
| **21 mrt 2026** | **JudoScoreBoard: volledige UI + backend integratie.** Expo React Native app: ControlScreen (Y/W/I scoring, osaekomi, timer, golden score, hantei beslissing), LoginScreen, WaitingScreen, WebSocket service (Reverb). Web display (Blade + Reverb) voor TV/LCD. Download page. Backend: ScoreboardController (5 endpoints), migratie, events, middleware — alles deployed naar staging. Scoreboard device aangemaakt (code: OMX9P8NALY5X, pin: 2779, mat 1). APK build vereist `eas init` interactief + keystore. | SCOREBORD-APP.md, LAYOUT.md |
| **9 mrt 2026** | **Stripe Connect: OAuth → Account Links.** StripePaymentProvider omgeschreven van legacy OAuth (ca_... client_id) naar Stripe Account Links onboarding. Controller callback checkt charges_enabled/payouts_enabled. Toernooi edit view: 3 onboarding statussen (geen/pending/gekoppeld). Afrekenen view: dynamische knoptekst per provider. STRIPE_CLIENT_ID verwijderd. BETALINGEN.md bijgewerkt. | BETALINGEN.md |
| **9 mrt 2026** | **Judoka database import:** "Uit database" knop toegevoegd aan toernooi deelnemersbeheer. Organisator kan stam judoka's importeren in een toernooi met automatische classificatie. `StambestandService` gefixt (`eigenaar()` → `organisator`, classificatie toegevoegd). Feature doc `JUDOKA-DATABASE.md`. Deployed staging, stambestand nog leeg — moet getest met testdata. | JUDOKA-DATABASE.md |
| **9 mrt 2026** | **Freemium model herdefiniëring:** Free tier nu met demo CSV downloads (30/40/50 judoka's). Geen auto-seed meer, klant importeert zelf. Eigen CSV max 20, handmatig max 20, totaal max 50. Geen print in free tier. `is_demo` veld + migratie toegevoegd. Doc issue #3466 (inconsistent prices) resolved. | FREEMIUM.md |
| **8 mrt 2026** | **Stripe betaling + admin facturen:** Stripe upgrade betaling getest op staging (werkend). Factuurnummer aangepast naar `JT-YYYYMMDD-{slug}-NNN` (was `JT-YYYYMMDD-NNN`). Stripe description bevat nu herkenbare referentie. Nieuwe admin pagina `/admin/facturen` met alle betalingen (klant, toernooi, provider, factuurnummer, status). Deployed staging. | BETALINGEN.md |
| **7 mrt 2026** | **Doc Intelligence cleanup:** 257 issues naar 0. 230 duplicate false positives (casing Judotoernooi vs JudoToernooi), 1 inconsistent false positive, 26 broken links resolved. 2 echte broken links gefixt (ONTWIKKELAAR.md link naar INSTALLATIE.md, BETALINGEN.md cross-project link). Mat-interface.png screenshot updated. | MEMORY.md |
| **6 mrt 2026** | **Drag root cause fix + PWA op dek:** Root cause: `WedstrijddagController` gebruikte globale `max_kg_verschil` i.p.v. per-poule `isDynamisch()`. Gewichtsrange in vaste titels, oranje header, ontbrekende waarschuwingsdriehoek, groene stip na drag — allemaal gefixt. `buildPouleResponse()` helper in PouleController. Homepage footer sticky verwijderd. Publieke PWA: "KLAAR" badge weg, blauwe "Op dek" status toegevoegd (banner, achtergrond, tab indicator). Cross-poule gereedmaken match lookup gefixt. | smallwork.md |
| **3 mrt 2026** | **AutoFix message pattern filtering:** `excluded_message_patterns` toegevoegd aan `config/autofix.php` + `shouldProcess()`. Filtert EADDRINUSE, ECONNREFUSED, disk full, sock permission errors. Probleem: vendor errors met `artisan` in stack trace passeerden file pattern filter. Deployed production. | MEMORY.md |
| **2 mrt 2026** | **AutoFix EADDRINUSE fix:** React Socket errors excluded van AutoFix (`config/autofix.php`). Reverb draait al via Supervisor, dubbele start pogingen zijn false positives. Deployed staging + production. | smallwork.md |
| **23 feb 2026** | **Git credentials + repo cleanup:** SSH deploy key (`deploy_judotoernooi`) aangemaakt voor production+staging servers (was HTTPS zonder credentials). Repo opgeschoond: 25+ junk files verwijderd (test data, uploads, autofix backups). `.gitignore` bijgewerkt. Beide servers clean en in sync. | smallwork.md |
| **22 feb 2026** | **Wimpel handmatig uitreiken + Security hardening:** Handmatige milestone uitreiking op judoka detail pagina (dropdown + datum, historische registratie zonder punten aanpassing). `getBereikteWimpelMilestones()` toont nu ook handmatig uitgereikt. **Security audit:** CSP `unsafe-eval` verwijderd, `APP_DEBUG=false` + `SESSION_ENCRYPT=true` + `SESSION_SECURE_COOKIE=true` op production+staging, rate limiting op PIN verify (throttle:5,1), `escapeshellarg()` in BackupService. | smallwork.md |
| **20 feb 2026** | **Help pagina verbeteringen:** Variabel gewicht/leeftijd uitleg (max kg verschil, v.lft). Mollie vereenvoudigd (alleen eigen account). Kruisfinale/eliminatie: alleen praktisch. Blokverdeling flow gefixt (Zaaloverzicht → matten aanpassen → Einde Voorbereiding → weegkaarten). Poule titel uitleg (vast vs variabel). Validatie timing gefixt. Poule generatie 3 stappen. IJF eliminatie beschrijving gecorrigeerd (herkansing alleen kwartfinale verliezers). Uitschrijven knop in Zoek Match modal (alle judoka's). BLOKVERDELING.md ook gefixt. | help.blade.php, en.json |
| **18 feb 2026** | **Error handling & reporting:** Coach portal 404 fix (int param + Judoka::find). Global ModelNotFoundException handler in app.php. Custom error pages (404/403/419/500) met "Meld dit probleem" knop → email naar havun22@gmail.com met volledige debug info (exception, stack trace, route, params). Import duplicate fix (unique constraint verwijderd, naamgenoot waarschuwing). Wimpel ParseError fix (Blade fn() in @json). AutoFixService toegevoegd door gebruiker. | smallwork.md |
| **17 feb 2026** | **Club toggle fix + PWA punten + Reverb UI:** Club toggle was kapot door `getPortalUrl()` side effect (`ensureClubPivot` koppelde clubs automatisch terug). Verwijderd. Clubs met judoka's nu disabled (niet loskoppelbaar). PWA favorieten toonde verkeerde punten (pivot formula vs WP/JP live). Polling verwijderd → Reverb only + auto-fallback. Reverb health check + restart knop in toernooi settings. Activity log details verrijkt met blok/mat badges. | `memory/MEMORY.md` |
| **16 feb 2026** | **Eliminatie DnD fix:** Judoka's in eliminatie poules nu draggable (sortable-poule + judoka-item classes). Titelbalk counts updaten correct na DnD (berekenEliminatieWedstrijden JS, updatePouleCountsFromServer). #undefined fix (data-poule-nummer). select-none op zaaloverzicht + poules pagina. Deployed staging + production. | smallwork.md |
| **15 feb 2026** | **Danpunten (JBN):** Feature compleet: toernooi toggle, JBN lidnummer per judoka (import + coach portal + edit), CSV export voor JBN (bruine banden, gewonnen wedstrijden). UI reorganisatie: Weging/Dojo/Danpunten als aparte blokken. Deployed naar staging. | `DANPUNTEN.md`, smallwork.md |
| **14 feb 2026** | **Page Builder verwijderd → Havunity:** Alle page builder code verwijderd (28 bestanden, 4077 regels). Publieke info tab vereenvoudigd (icon + judoschool). Preview link + URL kopiëren in edit pagina. Test tab → Admin. Nieuw project `D:\GitHub\Havunity\` opgezet (PLAN.md, CLAUDE.md, context.md). | smallwork.md |
| **13 feb 2026 (2)** | **Offline Noodpakket Server (Fase 1):** Complete infrastructuur gebouwd. OfflineExportService (SQLite export, getest: 51 judokas/77 wedstrijden/120KB). Go launcher (leest bundle.zip runtime). OfflinePackageBuilder (combineert launcher+PHP+Laravel+data). PowerShell build.ps1 (automatisch Go+PHP downloaden, Laravel strippen, compileren). OfflineMode middleware. Artisan `offline:export` command. **Build script nog niet gedraaid** (Go download nodig). | `offline/README.md`, `memory/offline-pakket.md` |
| **13 feb 2026** | Noodplan pagina reorganisatie: exports/prints boven, noodscenario's onder. JSON backup verwijderd (zit in server pakket). Poules printen in POULE EXPORT sectie. Live sync uitgebreid met weeg-gegevens + aanwezigheid. Offline Server Pakket knop (premium/free tier). | `memory/offline-pakket.md` |
| **12 feb 2026 (2)** | Pagina Builder Pro publieke rendering: 19 block partials + orchestrator, sections/header/footer detectie, fallback naar legacy blokken. Double-escape fix (`e()` + `{{ }}`). Deployed staging + production. | smallwork.md |
| **12 feb 2026** | IJF B-bracket vereenvoudigd: `b_repechage` hernoemd naar `b_halve_finale`, `aantal_brons` ondersteuning (1 of 2, standaard 2), B-groep count fix (6 bij IJF), herkomst labels fix ("uit A-1/4", "B-1/2 winnaar", "uit A-1/2"). **WIP: B-1/4 finale nog niet geïmplementeerd** — huidige IJF B-bracket heeft alleen B-1/2 + Brons (4 wed). Zie hieronder. | `memory/MEMORY.md` (Eliminatie sectie) |
| **10 feb 2026 avond** | Eliminatie bracket DnD hersteld: HTML5 DnD terug voor PC (ondragstart/ondrop/ondragover), SortableJS alleen als touch-only fallback (group per poule, DOM revert). dropJudoka opgeschoond (debug logs weg, laadWedstrijden terug). Beurtaanduiding (double-click kleuren) intact. | `memory/eliminatie-beurtaanduiding.md` |
| **10 feb 2026** | Mat interface polling → Reverb push: 30sec polling vervangen door WebSocket events (score, beurt, poule_klaar, bracket). MatUpdate::dispatch in plaatsJudoka/verwijderJudoka. | CHAT.md, INTERFACES.md |
| **9 feb 2026 ochtend** | Puntencompetitie spreker integratie: docs bijgewerkt, geen poule-uitslagen naar spreker, wél milestone-uitreikingen met afvinken. **WIP - alleen docs, nog geen code** | WIMPELTOERNOOI.md |
| **8 feb 2026 nacht** | Eliminatie B-bracket byes: spread verliezers over alle B(1) wedstrijden, bracket rendering fixes (slots, lookup tables) | ELIMINATIE/README.md, smallwork.md |
| **8 feb 2026 avond** | Offline Pakket MVP: standalone HTML download met 6 tabs (weeglijst, zaaloverzicht, schema's, scores, vrijwilligers, noodplan) | `memory/offline-pakket.md` |
| **8 feb 2026** | B-groep aparte mat (eliminatie): `b_mat_id`, zaaloverzicht A/B split, mat interface groep filter | ELIMINATIE/README.md |
| **8 feb 2026** | Blokverdeling fix: vaste categorieën classificatie, sortering, kruisfinale blokken | smallwork.md |
| **7 feb 2026 avond** | Club delete fix, backup restore, registratie fix, weeglijst AFWEZIG logica, admin klant delete | smallwork.md |
| **7 feb 2026** | Geboortejaar parser compleet, CSP fix, milestone backups | smallwork.md |
| **5 feb 2026** | Suspicious weight warnings, organisator instellingen, clubs constraint | smallwork.md |
| **4 feb 2026** | Poule/clubs UI, WhatsApp, print landscape | GEBRUIKERSHANDLEIDING.md (clubs sectie) |
| **4 feb 2026** | Blok view fixes, U-terminologie | CLASSIFICATIE.md (U-terminologie sectie) |
| **4 feb 2026** | Band/kyu cleanup, Band enum consolidatie | smallwork.md, CODE-STANDAARDEN.md §13 |
| 3 feb 2026 | Real-time mat updates via Reverb | Zie `CHAT.md` sectie 2 |
| 3 feb 2026 | Staging testing, Email log, QR fix | smallwork.md |
| 2 feb 2026 | 10+ Production Ready | `.claude/handover/2026-02-02-10plus-production.md` |
| 1 feb 2026 | Redundantie systeem | `.claude/handover/` (afgerond) |
| 24 jan 2026 | Reverb fix, DB reset | `.claude/handover/2026-01-24-avond-reverb-fix-db-reset.md` |

---

## Project Status

### ✅ Afgerond
- **Redundantie systeem** - Local server, hot standby, failover
- **10+ Improvements** - Error handling, CI/CD, tests, security
- **Core features** - Import, weging, mat, spreker, live, eliminatie
- **Real-time sync** - Reverb WebSockets voor scores, beurten, poule status
- **Production deploy** - Alle 3 omgevingen in sync (commit 7458156)

### 🔧 In Progress
- **Danpunten (JBN)** - Feature compleet, deployed staging + production. Docs: `DANPUNTEN.md`
- **Noodplan pagina** - Gereorganiseerd (branch `feature/noodplan-scenarios`), nog niet gemerged naar main
- **Offline Server Pakket** - Go launcher + portable PHP wordt gebouwd in terminal sessie. Download knop staat klaar op noodplan pagina (premium only). Zie `memory/offline-pakket.md`
- **Havunity** - Nieuw project `D:\GitHub\Havunity\`, alleen docs/plan. Laravel installatie in aparte sessie
- **IJF B-1/4 finale** - Huidige IJF B-bracket heeft alleen B-1/2 + Brons (4 wed, 6 judoka's). De B-1/4 finale moet nog geïmplementeerd worden

### 🎯 Volgende Stap
- **Live toernooi** - Klaar voor eerste echte toernooi
- Monitoring op https://judotournament.org

---

## Documentatie Structuur

```
CLAUDE.md                           # Project entry point
├── laravel/docs/README.md          # Docs index
│   ├── 1-GETTING-STARTED/          # Installatie, configuratie
│   ├── 2-FEATURES/                 # Feature docs
│   │   ├── GEBRUIKERSHANDLEIDING.md
│   │   ├── BETALINGEN.md
│   │   ├── CLASSIFICATIE.md
│   │   └── NOODPLAN-HANDLEIDING.md
│   ├── 3-DEVELOPMENT/              # Development guides
│   │   ├── CODE-STANDAARDEN.md     # **VERPLICHT**
│   │   └── STABILITY.md            # Error handling
│   ├── 3-TECHNICAL/                # Technical docs
│   │   ├── API.md
│   │   ├── DATABASE.md
│   │   └── REDUNDANTIE.md
│   ├── 4-PLANNING/                 # Future features
│   ├── 5-REGLEMENT/                # JBN rules
│   └── 6-INTERNAL/                 # Lessons learned
└── .claude/
    ├── handover.md                 # Dit bestand
    ├── handover/                   # Datum-specifieke handovers
    ├── context.md                  # Extra project context
    └── commands/                   # Custom commands
```

---

## Key Commands

```bash
# Development
php artisan serve --port=8007
npm run dev

# Testing
php artisan test
php artisan validate:production

# Deploy
git pull && npm run build
php artisan config:cache
php artisan route:cache
```

---

## Belangrijke URLs

| URL | Functie |
|-----|---------|
| `/registreren` | Nieuwe organisator aanmelden |
| `/login` | Organisator login |
| `/{slug}/dashboard` | Organisator dashboard |
| `/admin` | Sitebeheerder dashboard |
| `/admin/klanten` | Klantenbeheer (is_test, kortingsregeling, delete) |
| `/health` | Health check endpoint |
| `/local-server` | Local server dashboard |

---

*Voor specifieke taken, zie de datum-specifieke handover in `.claude/handover/`*
