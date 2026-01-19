# Context - JudoToernooi

> Technische details en specificaties

## Project Overzicht

| Aspect | Waarde |
|--------|--------|
| **Naam** | WestFries Open JudoToernooi |
| **Type** | Laravel 11 + Blade + Alpine.js + Tailwind |
| **Eigenaar** | Judoschool Cees Veen |
| **Status** | Production (live) |

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

> **Volledige docs:** `laravel/docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md`

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

## Laatste Sessie: 19 januari 2026 (middag)

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

### Eliminatie Systeem - Regels:
- **Eliminatie alleen bij VASTE categorieën**: `max_kg_verschil = 0` EN `max_leeftijd_verschil = 0`
- Bij variabele categorieën (dynamische gewichten): GEEN eliminatie mogelijk
- `eliminatie_gewichtsklassen` veld is DEPRECATED - niet gebruiken
- **Dropdown in poule titel**: om achteraf eliminatie → poules te wijzigen (bij te weinig judoka's)

### Belangrijke bestanden:
- `resources/views/pages/toernooi/edit.blade.php` → `updateJsonInput()` functie (regel ~840)
- `app/Http/Controllers/ToernooiController.php` → `update()` method
- Presets: `collectConfiguratie()` functie in edit.blade.php
