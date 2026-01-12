# Planning: Multi-Tenancy

> **Status:** On Hold (besluit: 13 jan 2026)
> **Reden:** Subdomeinen niet nodig op dit moment, mogelijk later
> **Doel:** Wereldwijde SaaS-applicatie met tenant isolatie en meertaligheid

## Overzicht

De JudoToernooi applicatie wordt voorbereid voor gebruik als SaaS platform waar meerdere organisaties (tenants) elk hun eigen geïsoleerde omgeving hebben.

## Gekozen Architectuur

### Database per Tenant

```
┌─────────────────────────────────────────────────────────────────┐
│ Central Database (shared)                                        │
│ - tenants (id, domain, name, settings, locale, plan, status)    │
│ - users (global admins only)                                     │
│ - plans (subscription plans)                                     │
└─────────────────────────────────────────────────────────────────┘
        │
        ├──────────────────┬──────────────────┬──────────────────┐
        ▼                  ▼                  ▼                  ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ tenant_jbn_nl │  │ tenant_ijf    │  │ tenant_club_de│  │ tenant_...    │
│ - toernooien  │  │ - toernooien  │  │ - toernooien  │  │ - ...         │
│ - judokas     │  │ - judokas     │  │ - judokas     │  │               │
│ - poules      │  │ - poules      │  │ - poules      │  │               │
│ - clubs       │  │ - clubs       │  │ - clubs       │  │               │
│ - ...         │  │ - ...         │  │ - ...         │  │               │
└───────────────┘  └───────────────┘  └───────────────┘  └───────────────┘
```

### Waarom Database per Tenant?

| Voordeel | Uitleg |
|----------|--------|
| **Data isolatie** | 100% gegarandeerd, geen risico op cross-tenant leaks |
| **GDPR compliance** | Tenant verwijderen = DROP DATABASE |
| **Performance** | Kleine databases, snelle queries |
| **Backups** | Per tenant backup/restore mogelijk |
| **Schaalbaarheid** | Horizontaal: databases over servers verdelen |
| **Maatwerk** | Per tenant custom migraties mogelijk |

### Nadelen (acceptabel)

| Nadeel | Mitigatie |
|--------|-----------|
| Complexere migraties | Automated migration runner |
| Meer databases | Cloud DB management (managed MySQL) |
| Cross-tenant queries | Via central database of queue jobs |

## Tenant Identificatie

### Opties

1. **Subdomain** (aanbevolen): `jbn.judotournament.org`
2. **Path**: `judotournament.org/jbn/...`
3. **Header/Token**: API-based identificatie

### Gekozen: Subdomain

```
jbn.judotournament.org      → tenant: jbn_nl
ijf.judotournament.org      → tenant: ijf
clubname.judotournament.org → tenant: clubname
```

**Custom domains ook mogelijk:**
```
toernooi.judobond.nl → tenant: jbn_nl (via DNS CNAME)
```

## Package Keuze

### stancl/tenancy (gekozen)

- Mature, goed onderhouden
- Database per tenant out-of-the-box
- Subdomain identificatie
- Automatic tenant switching
- Queue tenant-awareness
- Storage per tenant

**Installatie:**
```bash
composer require stancl/tenancy
php artisan tenancy:install
```

## Database Schema

### Central Database

```sql
-- tenants tabel
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,  -- slug: 'jbn_nl', 'ijf'
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),

    -- Domains
    -- (via domains tabel, many-to-many)

    -- Settings
    locale VARCHAR(10) DEFAULT 'nl',
    timezone VARCHAR(50) DEFAULT 'Europe/Amsterdam',
    settings JSON,  -- tenant-specific settings

    -- Subscription
    plan_id BIGINT UNSIGNED,
    trial_ends_at TIMESTAMP NULL,

    -- Status
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- domains tabel (voor custom domains)
CREATE TABLE domains (
    id BIGINT PRIMARY KEY,
    domain VARCHAR(255) UNIQUE,
    tenant_id VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- plans tabel (subscription plans)
CREATE TABLE plans (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    price DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'EUR',
    interval ENUM('monthly', 'yearly'),

    -- Limits
    max_tournaments INT DEFAULT 10,
    max_judokas_per_tournament INT DEFAULT 500,
    max_organisators INT DEFAULT 5,

    features JSON,  -- ['multi-mat', 'eliminatie', 'betalingen', ...]

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Tenant Database

Bestaande tabellen blijven identiek:
- `toernooien`
- `judokas`
- `poules`
- `clubs`
- `organisators`
- `coaches`
- etc.

**Geen tenant_id kolommen nodig** - complete isolatie.

## Meertaligheid

### Package: spatie/laravel-translatable

Voor database content (toernooi namen, categorieën):

```php
// Model
use Spatie\Translatable\HasTranslations;

class Toernooi extends Model
{
    use HasTranslations;

    public $translatable = ['naam', 'locatie', 'omschrijving'];
}

// Gebruik
$toernooi->naam = 'Open Westfries Judo Toernooi';  // default locale
$toernooi->setTranslation('naam', 'en', 'Open Westfries Judo Tournament');
$toernooi->setTranslation('naam', 'de', 'Offenes Westfries Judo Turnier');
```

### Laravel Localization

Voor UI teksten (blade views, validatie):

```
/lang
  /nl
    messages.php
    validation.php
  /en
    messages.php
    validation.php
  /de
    messages.php
    validation.php
```

### Locale Detectie

1. Tenant default locale
2. User preference (cookie/session)
3. Browser Accept-Language header
4. Fallback: 'nl'

## Implementatie Stappen

### Fase 1: Basis Tenancy Setup

- [ ] `stancl/tenancy` installeren
- [ ] Tenancy config aanpassen
- [ ] Central database migraties maken
- [ ] Tenant database migraties aanpassen
- [ ] Route configuratie (tenant routes vs central routes)

### Fase 2: Tenant Management

- [ ] Tenant CRUD in admin panel
- [ ] Domain management
- [ ] Tenant provisioning (create database, run migrations)
- [ ] Tenant deletion (soft delete + later hard delete)

### Fase 3: Bestaande Code Aanpassen

- [ ] Middleware voor tenant context
- [ ] Auth guards per tenant
- [ ] Queue tenant-awareness
- [ ] Storage per tenant
- [ ] Cache per tenant

### Fase 4: Meertaligheid

- [ ] `spatie/laravel-translatable` installeren
- [ ] Translatable velden toevoegen aan models
- [ ] Language files aanmaken (nl, en, de, fr, es)
- [ ] Locale switcher UI
- [ ] Locale middleware

### Fase 5: Subscription/Billing

- [ ] Plans tabel vullen
- [ ] Feature gates (max tournaments, max judokas)
- [ ] Stripe/Mollie integratie voor subscriptions
- [ ] Trial period handling

## Route Structuur

```php
// Central routes (geen tenant context)
Route::domain('judotournament.org')->group(function () {
    Route::get('/', 'HomeController@index');  // Landing page
    Route::get('/pricing', 'PricingController@index');
    Route::get('/register', 'RegisterController@showForm');  // Tenant registration
});

// Admin routes (super admin)
Route::domain('admin.judotournament.org')->group(function () {
    Route::get('/tenants', 'Admin\TenantController@index');
    // ...
});

// Tenant routes (met tenant context)
Route::middleware(['tenant'])->group(function () {
    // Alle bestaande routes
    Route::get('/organisator/dashboard', ...);
    Route::get('/toernooi/{toernooi}', ...);
    // ...
});
```

## Config Wijzigingen

### config/tenancy.php

```php
return [
    'tenant_model' => \App\Models\Tenant::class,

    'identification_strategy' => 'domain',  // of 'path'

    'database' => [
        'prefix' => 'tenant_',
        'suffix' => '',
        'template' => null,  // of 'tenant_template' voor pre-seeded data
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'features' => [
        // Stancl\Tenancy\Features\TenantConfig::class,
        // Stancl\Tenancy\Features\CrossDomainRedirect::class,
    ],
];
```

## Migratie Bestaande Data

Bij migratie van huidige single-tenant naar multi-tenant:

1. Maak tenant voor huidige organisatie(s)
2. Kopieer data naar tenant database
3. Verificatie
4. Switch DNS naar nieuwe setup
5. Cleanup oude data

## Top 10 Judo Landen (voor taalprioriteit)

| Land | Judoka's (geschat) | Taal |
|------|-------------------|------|
| 1. Brazilië | ~2 miljoen (incl. BJJ) | pt |
| 2. Frankrijk | ~500.000 | fr |
| 3. Japan | ~160.000 | ja |
| 4. Duitsland | ~160.000 | de |
| 5. Rusland | ~150.000 | ru |
| 6. Zuid-Korea | ~100.000 | ko |
| 7. Mongolië | ~50.000 | mn |
| 8. Nederland | ~40.000 | nl |
| 9. VS | ~40.000 | en |
| 10. UK | ~30.000 | en |

**Aanbevolen taalvolgorde:** nl (basis), en (internationaal), fr, de, pt, es, ja

## Open Vragen

1. ~~**Welke talen prioriteit?**~~ → Zie top 10 judo landen
2. ~~**Subdomain vs path?**~~ → Subdomain (maar later)
3. **Pricing model?** Per toernooi, per judoka, flat rate?
4. **Wie beheert tenants?** Zelf-registratie of handmatig?

## Notities

- Huidige codebase is NIET tenant-aware
- Alle `toernooi_id` checks zijn voldoende binnen tenant context
- Mollie Connect werkt al per organisator, past goed bij multi-tenant
