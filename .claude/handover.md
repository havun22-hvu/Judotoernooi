# Session Handover - JudoToernooi

> **Laatste update:** 5 februari 2026
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
| **5 feb 2026** | Verdachte gewicht warnings, adaptive polling, staging DB reset | smallwork.md |
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
| `/health` | Health check endpoint |
| `/local-server` | Local server dashboard |
| `/organisator/login` | Organisator login |
| `/organisator/dashboard` | Toernooi overzicht |

---

*Voor specifieke taken, zie de datum-specifieke handover in `.claude/handover/`*
