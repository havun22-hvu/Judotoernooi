# Session Handover - JudoToernooi

> **Laatste update:** 14 februari 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## 🚀 Quick Start

**Lees in volgorde:**

1. `CLAUDE.md` - Project regels en conventies
2. `.claude/handover.md` - Dit bestand (algemeen overzicht)
3. `.claude/handover/2026-02-02-10plus-production.md` - **Actuele status & planning**

---

## Laatste Sessies

| Datum | Onderwerp | Handover |
|-------|-----------|----------|
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
