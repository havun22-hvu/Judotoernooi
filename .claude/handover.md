# Session Handover - JudoToernooi

> **Laatste update:** 26 maart 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 26 maart 2026 (avond)

### Wat is gedaan:
- **KRITIEKE BUG GEFIXT:** `initialiseerToernooi()` riep `verwijderOudeToernooien()` aan — bij aanmaken nieuw toernooi werden ALLE bestaande toernooien verwijderd
- **Archief feature:** `is_gearchiveerd` kolom, toggle endpoint, dashboard gesplitst in actief + ingeklapt archief
- **Deployed naar staging**, migratie gedraaid

### Openstaande items:
- [ ] Deploy naar production (alleen staging is gedaan)
- [ ] `verwijderOudeToernooien()` method kan verwijderd worden uit ToernooiService (niet meer aangeroepen)

### Belangrijke context:
- 2 aparte organisator accounts: Henk (id=1, henkvu@gmail.com) en Havun (id=4, havun22@gmail.com)
- Admin klanten-tab filtert sitebeheerder eruit — gewenst gedrag

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
| **27 mrt 2026** | **Toernooi type (intern/open) + homepage redesign + docs cleanup.** Nieuw `toernooi_type` veld: intern verbergt eliminatie/danpunten/dojo/coachkaarten in instellingen. Round-robin tekst versimpeld. Homepage redesign: SVG icons, dieper blauw, strakke typografie, professionele SaaS-look — deployed production. Docs: 11 bestanden gearchiveerd, smallwork/handover ingekort, planning docs gemarkeerd als GEREALISEERD. FREEMIUM.md uitgebreid. | smallwork.md, FREEMIUM.md |
| **26 mrt 2026** | **Wimpelcompetitie review + hardening.** Volledige code review van wimpel/competitie flow. 5 fixes: negatieve punten geblokkeerd, duplicate milestone punten unique validatie, milestone delete bescherming (uitreikingen check), N+1 performance fix (eager loading), spreker knop verborgen zonder actief toernooi. Simplify: Rule import, inline subquery. Error reports onderzocht: APK 404 (timing), upgrade 404 (timing). | WIMPELTOERNOOI.md |
| **25 mrt 2026** | **UI polish: datumformaat + upgrade links.** Alle `diffForHumans()` vervangen door `d-m-Y H:i` (relatieve tijd als tooltip). Dubbele "Laatste login" verwijderd uit admin klanten view. Upgrade-link banner bij judoka import limiet (linkt naar Instellingen → Organisatie tab). `laatste_login` nu ook gezet bij registratie. | smallwork.md |
| **24 mrt 2026** | **Freemium polish + server herstructurering.** Eliminatie + danpunten verborgen voor free tier. Freemium messaging verzacht ("Geblokkeerd" rood → "Beschikbaar bij betaald pakket" grijs). Upgrade hint bij max deelnemers. Freemium banner toont nu ook voor admin (`plan_type` check i.p.v. `isFreeTier()`). Activiteiten log: details als expandable rij. **Server:** Production + staging omgezet naar git clone + symlink structuur (`repo-prod/laravel`, `repo-staging/laravel`). `git pull` werkt nu correct. Deploy docs bijgewerkt. Judoka zoekfilters (geboortejaar, geslacht, status). | FREEMIUM.md, deploy.md |
| **24 mrt 2026** | **Clubs Uitnodigen pagina heringedeeld:** Plaats en email kolommen verwijderd. Kopieer-icoon (was `~`) vervangen door clipboard SVG. WhatsApp en Email nu aparte kolommen met iconen (gekleurd als data beschikbaar, grijs als niet). Klik opent mailto:/wa.me met voorgevuld bericht. | smallwork.md |
| **22 mrt 2026** | **Scoreboard ↔ Mat koppeling + Reverb fix + UI fixes.** Poule sortering fix. Scoreboard auth met mat URL+pincode. Reverb mat listener bug gefixt. Staging rsync. Scoreboard API config `env()`. **Spreker tabs** vaste breedte (max-w-4xl). **Mat selectie bug:** `isEchtGespeeld` skip voor bestaande selectie-items. **Reverb race condition:** matSelectie direct uit event data bij beurt update. Scoreboard backend deployed staging. | smallwork.md |
| **21 mrt 2026** | **JudoScoreBoard: volledige UI + backend integratie.** Expo React Native app: ControlScreen (Y/W/I scoring, osaekomi, timer, golden score, hantei beslissing), LoginScreen, WaitingScreen, WebSocket service (Reverb). Web display (Blade + Reverb) voor TV/LCD. Download page. Backend: ScoreboardController (5 endpoints), migratie, events, middleware — alles deployed naar staging. Scoreboard device aangemaakt (code: OMX9P8NALY5X, pin: 2779, mat 1). APK build vereist `eas init` interactief + keystore. | SCOREBORD-APP.md, LAYOUT.md |
| **9 mrt 2026** | **Stripe Connect: OAuth → Account Links.** StripePaymentProvider omgeschreven van legacy OAuth (ca_... client_id) naar Stripe Account Links onboarding. Controller callback checkt charges_enabled/payouts_enabled. Toernooi edit view: 3 onboarding statussen (geen/pending/gekoppeld). Afrekenen view: dynamische knoptekst per provider. STRIPE_CLIENT_ID verwijderd. BETALINGEN.md bijgewerkt. | BETALINGEN.md |
| **9 mrt 2026** | **Judoka database import:** "Uit database" knop toegevoegd aan toernooi deelnemersbeheer. Organisator kan stam judoka's importeren in een toernooi met automatische classificatie. `StambestandService` gefixt (`eigenaar()` → `organisator`, classificatie toegevoegd). Feature doc `JUDOKA-DATABASE.md`. Deployed staging, stambestand nog leeg — moet getest met testdata. | JUDOKA-DATABASE.md |
| **9 mrt 2026** | **Freemium model herdefiniëring:** Free tier nu met demo CSV downloads (30/40/50 judoka's). Geen auto-seed meer, klant importeert zelf. Eigen CSV max 20, handmatig max 20, totaal max 50. Geen print in free tier. `is_demo` veld + migratie toegevoegd. Doc issue #3466 (inconsistent prices) resolved. | FREEMIUM.md |
| **8 mrt 2026** | **Stripe betaling + admin facturen:** Stripe upgrade betaling getest op staging (werkend). Factuurnummer aangepast naar `JT-YYYYMMDD-{slug}-NNN` (was `JT-YYYYMMDD-NNN`). Stripe description bevat nu herkenbare referentie. Nieuwe admin pagina `/admin/facturen` met alle betalingen (klant, toernooi, provider, factuurnummer, status). Deployed staging. | BETALINGEN.md |
| **7 mrt 2026** | **Doc Intelligence cleanup:** 257 issues naar 0. 230 duplicate false positives (casing Judotoernooi vs JudoToernooi), 1 inconsistent false positive, 26 broken links resolved. 2 echte broken links gefixt (ONTWIKKELAAR.md link naar INSTALLATIE.md, BETALINGEN.md cross-project link). Mat-interface.png screenshot updated. | MEMORY.md |

Oudere sessies: zie `.claude/archive/`
