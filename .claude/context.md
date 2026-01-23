# Context - JudoToernooi

> Technische details en specificaties

## Project Overzicht

| Aspect | Waarde |
|--------|--------|
| **Naam** | JudoToernooi (multi-tenant platform) |
| **Type** | Laravel 11 + Blade + Alpine.js + Tailwind |
| **URL** | judotournament.org |
| **Status** | Production (live) |

> **Let op:** "WestFries Open" is een specifiek toernooi van klant Cees Veen, niet de naam van het platform.

## Omgevingen

| Omgeving | URL | Pad | Database | APP_ENV |
|----------|-----|-----|----------|---------|
| **Local** | localhost:8007 | `D:\GitHub\JudoToernooi\laravel` | SQLite | local |
| **Staging** | (geen publiek domein) | `/var/www/staging.judotoernooi/laravel` | MySQL (staging_judo_toernooi) | staging |
| **Production** | judotournament.org | `/var/www/judotoernooi/laravel` | MySQL (judo_toernooi) | production |

> **Staging:** Alleen op server, geen publiek domein. Test via SSH.

```bash
php artisan serve --port=8007   # http://localhost:8007
```

**Server:** 188.245.159.115 (root, SSH key)

---

## Functionaliteit

### Core Features
- **Toernooi Management** - Aanmaken/configureren toernooien
- **Deelnemers Import** - CSV/Excel import met automatische classificatie
- **Poule Indeling** - Automatisch algoritme voor optimale verdeling
- **Blok/Mat Planning** - Verdeling over tijdsblokken en matten
- **Weging Interface** - QR scanner en naam zoeken
- **Mat Interface** - Wedstrijden beheren en uitslagen registreren
- **Eliminatie** - Double elimination met kruisfinales

### Classificatie

> **Volledige docs:** `laravel/docs/2-FEATURES/CLASSIFICATIE.md`

**Presets:**
| Type | Opslag |
|------|--------|
| JBN 2025/2026 | Hardcoded PHP (`Toernooi::getJbn20XXGewichtsklassen()`) |
| Eigen presets | Database (`gewichtsklassen_presets`) |

**Harde criteria (NOOIT overschreden):**
- Categorie niveau: max_leeftijd, geslacht, band_filter, gewichtsklassen
- Poule niveau: max_kg_verschil, max_leeftijd_verschil

**Zachte criteria (prioriteiten):**
- Alleen bij meerdere mogelijke indelingen: gewicht, band, groepsgrootte, club

### Indeling Modi (per leeftijdsgroep)

| Modus | Wanneer | Hoe |
|-------|---------|-----|
| **Vaste klassen** | max_kg_verschil = 0 | JBN gewichtsklassen |
| **Dynamisch** | max_kg_verschil > 0 | Groepen op basis van werkelijk gewicht |

---

## Mollie Betalingen

> **Docs:** `laravel/docs/2-FEATURES/BETALINGEN.md`

### Twee Modi

| Modus | Geld naar | OAuth nodig | Toeslag |
|-------|-----------|-------------|---------|
| **Connect** | Organisator's Mollie | Ja | Nee |
| **Platform** | JudoToernooi's Mollie | Nee | Ja (€0,50) |

### Database Velden

**toernooien tabel:**
```
betaling_actief          - boolean: betalingen aan/uit
inschrijfgeld            - decimal: bedrag per judoka
mollie_mode              - 'connect' of 'platform'
platform_toeslag         - decimal: toeslag in euro's
platform_toeslag_percentage - boolean: toeslag als %?
mollie_account_id        - string (connect mode)
mollie_access_token      - text, encrypted
mollie_refresh_token     - text, encrypted
mollie_token_expires_at  - datetime
mollie_onboarded         - boolean: succesvol gekoppeld?
mollie_organization_name - string: naam van Mollie org
```

**betalingen tabel:**
```
toernooi_id, club_id     - foreign keys
mollie_payment_id        - string, unique
bedrag                   - decimal
aantal_judokas           - integer
status                   - open/pending/paid/failed/expired/canceled
betaald_op               - timestamp
```

**judokas tabel:**
```
betaling_id              - nullable foreign key
betaald_op               - timestamp
```

### Bestanden

```
app/Services/MollieService.php     - Hybride service (Connect + Platform)
app/Models/Betaling.php            - Payment records
app/Models/Toernooi.php            - Mollie helper methods
config/services.php                - Mollie configuratie
```

### Toernooi Model Methods

```php
$toernooi->usesMollieConnect()       // Eigen Mollie?
$toernooi->usesPlatformPayments()    // Via platform?
$toernooi->hasMollieConfigured()     // Klaar voor betalingen?
$toernooi->calculatePaymentAmount(5) // Bedrag incl. toeslag
$toernooi->getMollieStatusText()     // Status voor UI
$toernooi->getPlatformFee()          // Toeslag ophalen
```

### Routes

```
GET  toernooi/{toernooi}/mollie/authorize  → Start OAuth flow (mollie.authorize)
GET  mollie/callback                       → OAuth callback (mollie.callback)
POST toernooi/{toernooi}/mollie/disconnect → Ontkoppelen (mollie.disconnect)
POST mollie/webhook                        → Payment updates (mollie.webhook, CSRF excluded!)
GET  betaling/simulate                     → Simulatie pagina (betaling.simulate)
POST betaling/simulate                     → Simulatie complete (betaling.simulate.complete)

# Coach Portal betaling returns (onder /club/{token}/ of /coach/{code}/)
GET  {token}/betaling/succes               → Na succesvolle betaling (betaling.succes)
GET  {token}/betaling/geannuleerd          → Bij geannuleerde betaling (betaling.geannuleerd)
```

### Environment Variables

```env
# Platform mode
MOLLIE_PLATFORM_API_KEY=live_xxx
MOLLIE_PLATFORM_TEST_KEY=test_xxx

# Connect mode (OAuth)
MOLLIE_CLIENT_ID=app_xxx
MOLLIE_CLIENT_SECRET=xxx
MOLLIE_REDIRECT_URI=${APP_URL}/mollie/callback

# Platform fee
MOLLIE_PLATFORM_FEE=0.50
```

### OAuth Flow (Connect Mode)

```
1. Organisator → Instellingen → "Koppel Mollie"
2. Redirect → Mollie OAuth authorize URL
3. Organisator logt in, authoriseert app
4. Callback met code → exchange voor tokens
5. Tokens encrypted opslaan bij toernooi
6. mollie_onboarded = true
```

### Webhook (Kritiek!)

```php
// routes/web.php - CSRF uitsluiten
Route::post('/mollie/webhook', [MollieController::class, 'webhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

- Webhook is source of truth (niet redirect)
- Kan meerdere keren komen → idempotent maken
- Valideer payment ID, check status, update database

### Status (9 januari 2026)

- ✅ Migration aangemaakt
- ✅ MollieService met dual mode
- ✅ Toernooi model uitgebreid
- ✅ Config en .env.example
- ✅ Routes en Controller
- ✅ Views: instellingen sectie (Organisatie tab)
- ⏳ Testen met echt Mollie account (Connect mode)

---

## Authenticatie & Device Binding

> **Volledige docs:** `laravel/docs/4-PLANNING/PLANNING_AUTHENTICATIE_SYSTEEM.md`
> **Rollen:** `laravel/docs/6-INTERNAL/ROLLEN_HIERARCHIE.md`

### Overzicht

| Rol | Authenticatie | Financieel |
|-----|---------------|------------|
| **Superadmin** | Wachtwoord (prod) / PIN (dev) | ✅ |
| **Organisator** | Email + wachtwoord | ✅ |
| **Beheerders** | Email + wachtwoord (toegevoegd) | ❌ |
| **Hoofdjury** | URL + PIN + device binding | ❌ |
| **Mat/Weging/Spreker/Dojo** | URL + PIN + device binding | ❌ |
| **Coachkaart** | Device binding + foto | - |

### Device Binding Systeem

**Twee losse lijsten:**
1. **Device toegangen** = URL + PIN per rol (niet gekoppeld aan persoon)
2. **Vrijwilligerslijst** = notitie met naam + telefoon + rol (optioneel)

**Flow:**
1. Organisator maakt toegangen aan per rol (bijv. 3x Mat, 2x Weging)
2. Organisator appt URL + PIN naar vrijwilliger (buiten systeem)
3. Vrijwilliger opent URL, voert PIN in → device wordt gebonden
4. Daarna: device herkend → direct toegang
5. Token verloren? → PIN opnieuw invoeren

**Database:** `device_toegangen` tabel
```
toernooi_id, rol, mat_nummer, code, pincode, device_token, device_info, gebonden_op, laatst_actief
```
> **Let op:** Geen naam/telefoon/email - organisator beheert dit zelf via WhatsApp

### Coachkaart Device Binding

**Tegen delen van QR-codes:**
1. Coach activeert kaart op telefoon → device binding
2. Upload pasfoto OF maak selfie
3. QR pas zichtbaar na foto
4. Dojo-scanner toont foto → vrijwilliger vergelijkt gezicht

### Einde Toernooi

Wanneer getriggerd:
- Alle device bindings gereset
- Statistieken berekend en getoond

### Te verwijderen
- ~~Service Login pagina~~ (`pages/auth/service-login.blade.php`)
- ~~Toernooi-level wachtwoorden per rol~~

### Routes
```
/login              → Organisator/Beheerder login
/toegang/{code}     → Device binding flow (PIN invoer)
/weging/{id}        → Weging interface (device-gebonden)
/mat/{id}           → Mat interface (device-gebonden)
/jury/{id}          → Hoofdjury interface (device-gebonden)
/spreker/{id}       → Spreker interface (device-gebonden)
/dojo/{id}          → Dojo scanner (device-gebonden)
```

---

## Project Structuur

```
laravel/
├── app/
│   ├── Enums/              # Leeftijdsklasse, Band, Geslacht
│   ├── Models/             # Toernooi, Judoka, Poule, Betaling
│   ├── Services/           # MollieService, EliminatieService
│   └── Http/Controllers/
├── config/
│   ├── toernooi.php        # Toernooi defaults
│   └── services.php        # Mollie config
├── database/migrations/
├── docs/                   # Documentatie
└── resources/views/
```

---

## Database

| Omgeving | Type | Database | User |
|----------|------|----------|------|
| Local | SQLite | database/database.sqlite | - |
| Staging | MySQL | staging_judo_toernooi | judotoernooi |
| Production | MySQL | judo_toernooi | judotoernooi |

---

## Local Development

```bash
cd laravel
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=8007
```

**Let op:**
- Local: SQLite (geen MySQL nodig)
- Poort 8007 (zie HavunCore server.md voor alle poorten)

---

## Deploy (Staging)

```bash
ssh root@188.245.159.115
cd /var/www/staging.judotoernooi/laravel

git pull origin master
composer install --no-dev
php artisan migrate

# ALTIJD caches clearen VOOR opnieuw opbouwen (voorkomt sessie/login problemen!)
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Daarna opnieuw cachen
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Deploy (Production)

```bash
ssh root@188.245.159.115
cd /var/www/judotoernooi/laravel

git pull origin master
composer install --no-dev
php artisan migrate

# ALTIJD caches clearen VOOR opnieuw opbouwen (voorkomt sessie/login problemen!)
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Daarna opnieuw cachen
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Let op:** Zonder cache:clear kunnen gebruikers in een redirect loop komen bij login door corrupte sessies!

---

## Gerelateerde HavunCore Docs

- `HavunCore/docs/kb/patterns/mollie-payments.md` - Mollie pattern
- `HavunCore/.claude/context.md` - Server credentials

---

## Laatste Sessie: 19 januari 2026 (avond)

### Wat is gedaan:
- **Kruisfinales check** - alleen bij vaste categorieën (max_kg=0 EN max_lft=0)
  - Bestand: `PouleIndelingService.php:413-420`

- **Eliminatie niet als probleem-poule** - uitgesloten van "Problematische poules" sectie
  - Bestand: `resources/views/pages/poule/index.blade.php`
  - Filter: `$p->type !== 'eliminatie' && $p->type !== 'kruisfinale'`

- **Eliminatie wedstrijden formule** - was complex, nu simpel:
  - 2 brons: `2N - 5`
  - 1 brons: `2N - 4`
  - Leest `aantal_brons` uit toernooi instellingen
  - Bestand: `Poule.php:149-158` (`berekenEliminatieWedstrijden`)

- **Eliminatie poule layout**:
  - 5 kolommen grid
  - Zelfde judoka-chip als normale poules:
    ```
    Naam (lft)       x.y kg
    judoschool       band
    ```
  - Bestand: `resources/views/pages/poule/index.blade.php:256-290`

- **Wedstrijdsysteem dropdown op alle poule types**:
  - Poule (voorronde): → Eliminatie, → Kruisfinale
  - Eliminatie: → Poules, → Kruisfinale (bestond al)
  - Kruisfinale: → Poules, → Eliminatie
  - Bestand: `resources/views/pages/poule/index.blade.php`

### LES (KRITIEK):
- **EERST VRAGEN, DAN IMPLEMENTEREN** - 30 min verspild door niet te luisteren
- Bij UI wijzigingen: vraag exact wat de gebruiker wil zien, bevestig, dan pas coderen

---

## Sessie: 19 januari 2026 (middag)

### Wat is gedaan:
- **gewichtsklasse kolom te kort** - migration gemaakt om van 10 naar 50 chars uit te breiden
  - Error: "17.9-18.5kg" (12 chars) paste niet in kolom van 10 chars
  - Fix: `2026_01_19_162817_extend_gewichtsklasse_column_length.php`

- **Mollie ontkoppelen werkte niet** - APP_URL was verkeerd op production
  - `.env` had `APP_URL=https://staging.judotournament.org` i.p.v. `https://judotournament.org`
  - Form action ging naar staging → cross-origin fail

- **Mollie koppelen** - opent nu in nieuw venster (`target="_blank"`)

- **wedstrijd_systeem werd niet opgeslagen** (Poules/Kruisfinale/Eliminatie dropdown)
  - **Oorzaak:** JavaScript `updateJsonInput()` verzamelde NIET het `wedstrijd_systeem` veld
  - Alle andere velden (label, leeftijd, geslacht, etc.) werden wel meegenomen
  - **Fix:**
    1. `updateJsonInput()` → voeg `wedstrijd_systeem` toe aan JSON
    2. `collectConfiguratie()` (presets) → voeg `wedstrijd_systeem` toe
    3. Controller → extract `wedstrijd_systeem` uit JSON en sla apart op

- **CheckToernooiRol middleware** - `route('dashboard')` bestond niet, vervangen door `route('home')`

### Openstaande items:
- [ ] **Import preview UI verbeteren** (PRIORITEIT)
- [ ] Debug logging verwijderen uit edit.blade.php (console.log statements)
- [ ] Openstaande bugs van 17 jan: vals-positieve gewichtsrange markering

### LESSEN (KRITIEK):
1. **Bij "veld wordt niet opgeslagen"** → EERST checken hoe form data wordt verzameld (JavaScript), niet direct backend debuggen
2. **Direct deployen naar staging** als gebruiker daar test - niet lokaal fixen en vergeten te deployen
3. **APP_URL in .env** moet kloppen per omgeving - verschil veroorzaakt cross-origin problemen

### Categorieën: Vast vs Variabel

**VASTE categorieën** (`max_kg_verschil = 0` EN `max_leeftijd_verschil = 0`):
- ✅ Poules mogelijk
- ✅ Kruisfinales mogelijk
- ✅ Eliminatie mogelijk
- Blokverdeling: hele gewichtscategorie als 1 chip

**VARIABELE categorieën** (`max_kg_verschil > 0` OF `max_leeftijd_verschil > 0`):
- ✅ Alleen poules mogelijk
- ❌ Geen kruisfinales
- ❌ Geen eliminatie
- Blokverdeling: elke poule apart als chip (verdeling op aansluiting + gelijke blokken)

**UI:**
- Dropdown in poule titel om achteraf eliminatie → poules te wijzigen (bij te weinig judoka's)
- `eliminatie_gewichtsklassen` veld is DEPRECATED - niet gebruiken

### UI Conventies

| Element | Notatie | Voorbeeld |
|---------|---------|-----------|
| Poule nummer | `#` prefix | #1, #42, #80 |
| Wedstrijd nummer | `W` prefix | W1, W2 |

### Belangrijke bestanden:
- `resources/views/pages/toernooi/edit.blade.php` → `updateJsonInput()` functie (regel ~840)
- `app/Http/Controllers/ToernooiController.php` → `update()` method
- Presets: `collectConfiguratie()` functie in edit.blade.php

---

## Laatste Sessie: 21 januari 2026 (avond)

### Wat is gedaan:
- **Import warnings per club** - Admin judoka pagina toont nu warnings gegroepeerd per club met contactgegevens (email/telefoon)
- **Import nooit falen op null gewichtsklasse** (KRITIEK FIX)
  - 14 judoka's werden geweigerd omdat U7 geen gewichtsklassen had
  - Fix: `gewichtsklasse` is NOOIT null - altijd 'Onbekend' of 'Variabel'
  - Regel: "gewichtscategorie is NIET verplicht, alleen opgegeven gewicht"
- **Migrations voor import velden**:
  - `import_warnings` op judokas tabel (persistent warnings)
  - `import_fouten` op toernooien tabel (nog niet volledig gebruikt)
- **IMPORT.md documentatie** - Nieuwe doc met import workflow en regels

### Niet afgerond:
- [ ] `import_fouten` veld daadwerkelijk vullen in JudokaController (migration bestaat)
- [ ] Local testing: APP_URL in .env moet `http://127.0.0.1:8007` zijn voor reset button

### Openstaande bugs (uit vorige sessies):
- [ ] Vals-positieve gewichtsrange markering (oranje bij OK poules)
- [ ] Poule header kleur blijft oranje na fix

### Belangrijke context Zaaloverzicht:

**Probleem dat opgelost is:**
Bij variabele categorieën (max_kg_verschil > 0) hebben meerdere poules dezelfde `leeftijdsklasse|gewichtsklasse` combinatie:
- Jeugd|-24 = 7 poules
- Jeugd|-27 = 4 poules
- etc.

De oude code groepeerde per categorie → maar 1 chip voor 7 poules!
Nu: elke poule een eigen chip zodat ze apart geactiveerd kunnen worden.

**Flow toernooidag:**
1. **Wedstrijddag Poules**: per poule → klikken (blauw wordt groen ✓)
2. **Zaaloverzicht**: chip per poule verschijnt:
   - **Grijs** = niet doorgestuurd
   - **Wit** = doorgestuurd, klaar voor activatie
   - **Groen** = geactiveerd (wedstrijden gegenereerd)
3. Klik witte chip → `activeerPoule()` → wedstrijdschema genereren

**Routes:**
- `POST blok/activeer-poule` → genereert wedstrijdschema voor 1 poule
- `POST blok/reset-poule` → verwijdert wedstrijden van 1 poule

**Chip naam format:**
```
{leeftijdsklasse} {gewichtsklasse} #{poule_nummer}
Voorbeeld: "Jeugd -24 #5"
```

### Bestanden gewijzigd deze sessie:
- `app/Http/Controllers/BlokController.php`:
  - `getCategoryStatuses()` - herschreven, returned nu per-poule status met key `poule_{id}`
  - `activeerPoule()` - nieuw, genereert wedstrijden voor 1 poule
  - `resetPoule()` - nieuw, verwijdert wedstrijden van 1 poule
- `resources/views/pages/blok/zaaloverzicht.blade.php`:
  - `$blokPoulesList` i.p.v. `$blokCategories`
  - Chips tonen nu poule nummer (#) i.p.v. alleen categorie
- `routes/web.php` - routes `blok.activeer-poule` en `blok.reset-poule`

### Test instructies voor volgende sessie:
```bash
cd laravel && php artisan serve --port=8007
```
1. Open http://localhost:8007
2. Log in als organisator
3. Ga naar Zaaloverzicht (via Blokken)
4. Blok 1: alle poules moeten als grijze chips verschijnen
5. Ga naar Wedstrijddag Poules → klik → bij poules (blauw wordt groen)
6. Terug naar Zaaloverzicht → chips moeten nu wit zijn
7. Klik witte chip → moet groen worden (wedstrijden gegenereerd)

---

## Laatste Sessie: 22 januari 2026

### Wat is gedaan:
- **Docs reorganisatie** - Volledige audit en cleanup van documentatie structuur
- Verouderde HANDOVER.md bestanden verwijderd
- smallwork.md getrimd (670→238 regels, alleen laatste 3 sessies)
- Platformnaam gefixed: "WestFries Open" → "JudoToernooi" in docs
- PLANNING_DYNAMISCHE_INDELING.md verplaatst naar 2-FEATURES/CLASSIFICATIE.md
- README.md links en structuur geüpdatet
- CLAUDE.md Knowledge Base tabel geüpdatet

### Documentatie structuur na deze sessie:
```
docs/
├── 2-FEATURES/
│   ├── CLASSIFICATIE.md      ← NIEUW (voltooid algoritme)
│   ├── BETALINGEN.md
│   ├── BLOKVERDELING.md
│   └── ...
└── 4-PLANNING/               ← Alleen ongeïmplementeerde features
    ├── PLANNING_AUTHENTICATIE_SYSTEEM.md
    └── PLANNING_NOODPLAN.md
```

---

## Laatste Sessie: 23 januari 2026 (middag) - HANDOVER

### Wat is gedaan:
- **Portaal modus feature COMPLEET:**
  - Migration: `portaal_modus` veld (uit/mutaties/volledig)
  - Model helpers: `portaalMagInschrijven()`, `portaalMagWijzigen()`, `portaalIsUit()`
  - UI: Dropdown in Instellingen → Organisatie + Mollie hint
  - Controller checks in CoachPortalController
  - View: Info banners + knoppen per modus
- **Handmatige judoka invoer:** "+ Judoka toevoegen" modal op admin pagina

### Portaal modus:
| Modus | Nieuw | Wijzigen | Verwijderen |
|-------|-------|----------|-------------|
| **uit** | ❌ | ❌ | ❌ |
| **mutaties** | ❌ | ✅ | ❌ |
| **volledig** | ✅ | ✅ | ✅ |

### Te testen:
1. Instellingen → Organisatie → Portaal modus dropdown
2. Coach portal per modus (banner + knoppen)
3. Admin → Judoka's → "+ Judoka toevoegen"

### Deploy nodig:
```bash
php artisan migrate
```

### Commit: `2463ebf`

---

## Sessie: 23 januari 2026 (ochtend)

### Wat is gedaan:
- Categorie overlap detectie gebouwd (CategorieClassifier::detectOverlap)
- Waarschuwing banner voor overlappende categorieën in Instellingen
- Fix: gewichtsklasse null constraint error bij save
- Fix: metadata (_preset_type, _eigen_preset_id) in judokasPerKlasse overzicht
- Fix: band range berekening (BandHelper heeft omgekeerde volgorde)
- Niet-gecategoriseerd waarschuwing toegevoegd aan Poules pagina

### Belangrijke context:
- **BandHelper::BAND_VOLGORDE** is omgekeerd: wit=6, zwart=0 (hogere waarde = lagere band)
- **Gewichtsklassen config** bevat metadata keys met `_` prefix die gefilterd moeten worden
- **Categorie overlap** check: zelfde max_leeftijd + zelfde geslacht + overlappende band_filter
- **Niet-gecategoriseerd waarschuwing** moet zichtbaar zijn op Judoka's en Poules pagina's (niet dynamisch in Instellingen tijdens configureren)
