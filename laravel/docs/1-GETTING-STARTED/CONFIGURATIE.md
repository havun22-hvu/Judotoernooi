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

## Leeftijdsklassen

De standaard leeftijdsklassen:

| Klasse | Max Leeftijd | Gewichtsklassen |
|--------|--------------|-----------------|
| Mini's | < 8 jaar | -20, -23, -26, -29, +29 kg |
| A-pupillen | < 10 jaar | -24, -27, -30, -34, -38, +38 kg |
| B-pupillen | < 12 jaar | -27, -30, -34, -38, -42, -46, -50, +50 kg |
| Dames -15 | < 15 jaar | -36, -40, -44, -48, -52, -57, -63, +63 kg |
| Heren -15 | < 15 jaar | -34, -38, -42, -46, -50, -55, -60, -66, +66 kg |

## Band Combinaties

Per leeftijdsklasse bepalen de band combinaties welke judoka's samen in een poule kunnen:

- **Mini's**: Alle banden samen
- **A-pupillen**: Witte banden apart, geel en hoger samen
- **B-pupillen**: Wit en geel samen, oranje en hoger samen
- **-15 jaar**: Wit t/m groen samen, blauw en bruin samen

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
