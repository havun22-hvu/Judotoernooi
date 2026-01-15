# Configuratie

## Environment Variabelen

### Applicatie

| Variabele | Beschrijving | Standaard |
|-----------|--------------|-----------|
| `APP_NAME` | Naam van de applicatie | WestFries Open JudoToernooi |
| `APP_ENV` | Environment (local/staging/production) | local |
| `APP_DEBUG` | Debug modus aan/uit | true |
| `APP_URL` | Basis URL van de applicatie | http://localhost |
| `APP_TIMEZONE` | Tijdzone | Europe/Amsterdam |

### Database

| Variabele | Beschrijving | Standaard |
|-----------|--------------|-----------|
| `DB_CONNECTION` | Database driver | mysql |
| `DB_HOST` | Database host | 127.0.0.1 |
| `DB_PORT` | Database poort | 3306 |
| `DB_DATABASE` | Database naam | judo_toernooi |
| `DB_USERNAME` | Database gebruikersnaam | root |
| `DB_PASSWORD` | Database wachtwoord | - |

### Toernooi Configuratie

| Variabele | Beschrijving | Standaard |
|-----------|--------------|-----------|
| `ADMIN_PASSWORD` | Admin wachtwoord | WestFries2026 |
| `TOERNOOI_MIN_JUDOKAS_POULE` | Minimum judoka's per poule | 3 |
| `TOERNOOI_OPTIMAL_JUDOKAS_POULE` | Optimaal aantal per poule | 5 |
| `TOERNOOI_MAX_JUDOKAS_POULE` | Maximum judoka's per poule | 6 |
| `TOERNOOI_GEWICHT_TOLERANTIE` | Gewichtstolerantie in kg | 0.5 |

## Leeftijdsklassen (Dynamisch)

Leeftijdsklassen worden **per toernooi** geconfigureerd via Instellingen → Categorieën. Er zijn geen hardcoded categorieën meer.

### Hoe classificatie werkt

1. **Toernooi config is leidend** - `Toernooi::bepaalLeeftijdsklasse()` leest uit de config
2. **Sortering op max_leeftijd** - Categorieën sorteren van jong naar oud
3. **Matching volgorde**: max_leeftijd → geslacht → band_filter

### Voorbeeld configuratie

| Label | Max Leeftijd | Geslacht | Band Filter |
|-------|--------------|----------|-------------|
| Mini's | 6 | Gemengd | - |
| Jeugd | 12 | Gemengd | - |
| Dames | 90 | V | - |
| Heren | 90 | M | - |

### Hercategorisatie

Na wijzigen van categorieën: **Judoka's → Valideer judoka's** om alle judoka's opnieuw te classificeren.

### Technische details

- `Toernooi::bepaalLeeftijdsklasse(int $leeftijd, string $geslacht, ?string $band)` - bepaalt categorie
- `Toernooi::getLeeftijdsklasseSortValue(string $klasse)` - sorteerwaarde voor weergave
- Oude `Leeftijdsklasse` enum is deprecated en wordt niet meer gebruikt

## Band Combinaties

Per leeftijdsklasse kan een `band_filter` worden ingesteld:

| Filter | Betekenis |
|--------|-----------|
| `tm_geel` | Wit en geel |
| `tm_oranje` | Wit t/m oranje |
| `vanaf_oranje` | Oranje en hoger |
| `vanaf_groen` | Groen en hoger |
| (leeg) | Alle banden samen |

## Cache Configuratie

Voor productie wordt aangeraden om Redis te gebruiken:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## Email Configuratie

Voor het versturen van judoka pasjes per email:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=uw_username
MAIL_PASSWORD=uw_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=toernooi@judoschoolceesveen.nl
MAIL_FROM_NAME="WestFries Open JudoToernooi"
```
