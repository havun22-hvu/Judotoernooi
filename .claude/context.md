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
- **Leeftijdsklassen:** Mini's, Pupillen A/B/C, U15, U18, U21, Senioren
- **Banden:** Wit → Zwart
- **Geslacht:** Per leeftijdsgroep instelbaar (Gemengd / Jongens / Meisjes)

### Indeling Modi (per leeftijdsgroep)

| Modus | Wanneer | Hoe |
|-------|---------|-----|
| **Vaste klassen** | max_kg_verschil = 0 | JBN gewichtsklassen |
| **Dynamisch** | max_kg_verschil > 0 | Groepen op basis van werkelijk gewicht |

> **Planning:** `laravel/docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md`

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

### Routes (TODO)

```
GET  /mollie/authorize/{toernooi}  → Start OAuth flow
GET  /mollie/callback              → OAuth callback
POST /mollie/disconnect/{toernooi} → Ontkoppelen
POST /mollie/webhook               → Payment updates (CSRF excluded!)
GET  /betaling/return              → Na betaling redirect
GET  /betaling/simulate/{id}       → Simulatie pagina (staging)
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

### Status (3 januari 2026)

- ✅ Migration aangemaakt
- ✅ MollieService met dual mode
- ✅ Toernooi model uitgebreid
- ✅ Config en .env.example
- ⏳ Routes en Controller
- ⏳ Views voor instellingen
- ⏳ Woensdag: Cees' Mollie koppelen

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

**Flow:**
1. Organisator maakt toegang aan (Instellingen → Organisatie)
2. Vrijwilliger krijgt URL + PIN
3. Eerste login: PIN invoeren → device wordt gebonden
4. Daarna: device herkend → direct toegang
5. Token verloren? → PIN opnieuw invoeren

**Database:** `device_toegangen` tabel
```
toernooi_id, naam, telefoon, email, rol, mat_nummer, code, pincode, device_token, device_info
```

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
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Gerelateerde HavunCore Docs

- `HavunCore/docs/kb/patterns/mollie-payments.md` - Mollie pattern
- `HavunCore/.claude/context.md` - Server credentials

---

## Laatste Sessie: 9 januari 2026

### Wat is gedaan:
- JBN 2025/2026 presets: Mini's t/m Pupillen nu **gemengd** (niet meer gescheiden m/v)
- JBN regel gedocumenteerd: gescheiden pas vanaf -15 jaar
- Default max_leeftijd_verschil: 2 → 1 jaar bij nieuwe categorie
- Start met lege categorieën (gebruiker kiest zelf preset of handmatig)
- Cleanup: overbodige `getJbn20XXGewichtsklassenGemengd()` methods verwijderd

### Openstaande items:
- [ ] Testen: wijzig prioriteiten → check judoka codes herberekend
- [ ] Testen: poules met gewicht prioriteit 1 → geen 20kg/26kg mix
- [ ] Testen: versleep judoka → statistieken update
- [ ] Testen: import CSV met ontbrekend geboortejaar → "Onvolledig" filter
- [ ] Testen: JBN preset laden → gemengde categorieën voor jeugd
- [ ] Fase 3 dynamische indeling: varianten UI in poule-overzicht
- [ ] Fase 4 dynamische indeling: unit tests

### Branch info:
- **Branch:** `feature/dynamische-indeling`
- **Commits:** 4 nieuwe (1863e39, f316ae4, 3e2b6cb)
- **Docs bijgewerkt:** JBN-REGLEMENT-2026.md (geslacht regel)
