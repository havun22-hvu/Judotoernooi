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

## Laatste Sessie: 12 januari 2026 (avond)

### Wat is gedaan:
- **Grote refactoring: Config-based classificatie**
  - Migration: sorteer velden toegevoegd (sort_categorie, sort_gewicht, sort_band, categorie_key)
  - `classificeerJudoka()` methode: leest criteria uit preset config
  - `herberkenKlassen()` refactored: gebruikt nu config ipv enum
  - `groepeerJudokas()` refactored: sorteert op nieuwe velden ipv judoka_code
  - PouleController: dynamische volgorde uit preset config
  - Leeftijdsklasse enum gemarkeerd als deprecated
  - Obsolete code verwijderd (bepaalGewichtsklasseVoorLeeftijd, hardcoded arrays)

### Openstaande items:
- [ ] **TESTEN:** Poules genereren met custom categorie namen → check titels
- [ ] Testen: wijzig prioriteiten → check judoka codes herberekend
- [ ] Testen: versleep judoka → statistieken + ranges update
- [ ] Fase 3 dynamische indeling: varianten UI in poule-overzicht
- [ ] Fase 4 dynamische indeling: unit tests

### Belangrijke context voor volgende keer:
- **Classificatie:** Zie `PLANNING_DYNAMISCHE_INDELING.md` sectie "Classificatie Systeem"
- **Poule titels:** Categorie naam komt uit `$gewichtsklassenConfig[$configKey]['label']`
- **Gewicht fallback:** `getEffectiefGewicht()` in `DynamischeIndelingService`
