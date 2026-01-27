# URL Structuur - JudoToernooi

> **Versie:** 2.0 (januari 2026)
> **Status:** In implementatie

---

## Overzicht

De URL structuur is opgebouwd rond twee principes:

1. **Organisator context** - URLs bevatten `{org}` (organisator slug) voor duidelijke eigenaarschap
2. **Auth indicator** - Het woord `toernooi` in de URL geeft aan dat login vereist is

---

## Authenticatie Types

| Type | Omschrijving | Voorbeeld |
|------|--------------|-----------|
| **Geen** | Vrij toegankelijk | Publieke toernooi pagina |
| **Organisator Login** | Email + wachtwoord | Dashboard, toernooi beheer |
| **PIN** | 4-cijferige code | Coach portal |
| **PIN + Device** | PIN + browser binding | Vrijwilligers interfaces |

---

## URL Schema

### 1. Homepage & Auth

Algemene pagina's zonder organisator context.

| URL | Doel | Auth |
|-----|------|------|
| `/` | Homepage | Geen |
| `/login` | Organisator login | Geen |
| `/registreren` | Nieuwe organisator | Geen |
| `/wachtwoord-vergeten` | Wachtwoord reset | Geen |
| `/wachtwoord-reset/{token}` | Nieuw wachtwoord instellen | Geen |

---

### 2. Organisator Beheer

**Auth: Organisator login vereist**

Alle routes onder `/{org}/...` (zonder `toernooi` erna) zijn organisator-niveau instellingen.

| URL | Doel |
|-----|------|
| `/{org}/dashboard` | Overzicht toernooien |
| `/{org}/clubs` | Club beheer (blijft bewaard tussen toernooien) |
| `/{org}/templates` | Toernooi templates |
| `/{org}/presets` | Gewichtsklassen presets |

**Voorbeeld:** `/judo-ceesveen/dashboard`

---

### 3. Sitebeheerder (Superadmin)

**Auth: Organisator login + sitebeheerder rol**

Alleen toegankelijk voor henkvu@gmail.com.

| URL | Doel |
|-----|------|
| `/admin` | Overzicht alle organisatoren en toernooien |

---

### 4. Toernooi Beheer

**Auth: Organisator login vereist**

> ⚠️ Let op: Het woord `toernooi` in de URL geeft aan dat login vereist is.

| URL | Doel |
|-----|------|
| `/{org}/toernooi/nieuw` | Nieuw toernooi aanmaken |
| `/{org}/toernooi/{toernooi}` | Toernooi startpagina (beheer) |
| `/{org}/toernooi/{toernooi}/edit` | Instellingen |
| `/{org}/toernooi/{toernooi}/judoka` | Judoka beheer |
| `/{org}/toernooi/{toernooi}/poule` | Poule beheer |
| `/{org}/toernooi/{toernooi}/club` | Club selectie voor dit toernooi |
| `/{org}/toernooi/{toernooi}/blok` | Blokken/tijdslots |
| `/{org}/toernooi/{toernooi}/weging` | Weging overzicht |
| `/{org}/toernooi/{toernooi}/mat` | Mat overzicht |
| `/{org}/toernooi/{toernooi}/noodplan` | Print/noodplan |
| `/{org}/toernooi/{toernooi}/upgrade` | Upgrade naar betaald plan |

**Voorbeeld:** `/judo-ceesveen/toernooi/open-2026/poule`

---

### 5. Publieke Pagina's

**Auth: Geen**

Vrij toegankelijk voor iedereen (bezoekers, ouders).

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}` | Publieke toernooi pagina (PWA) |
| `/weegkaart/{token}` | Weegkaart via QR code |
| `/coach-kaart/{qrCode}` | Coach kaart via QR code |

**Voorbeeld:** `/judo-ceesveen/open-2026`

> **Let op:** Geen `toernooi` in de URL = publiek toegankelijk

#### Verschil publiek vs beheer:

```
PUBLIEK:  /{org}/{toernooi}
          /judo-ceesveen/open-2026
                        ↑ direct na org-slug

BEHEER:   /{org}/toernooi/{toernooi}
          /judo-ceesveen/toernooi/open-2026
                        ↑ woord "toernooi" ertussen
```

---

### 6. Coach Portal

**Auth: PIN code**

Coaches krijgen een link met code, daarna PIN invoeren.

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}/school/{code}` | Login pagina |
| `/{org}/{toernooi}/school/{code}/judokas` | Judoka's beheren |
| `/{org}/{toernooi}/school/{code}/weegkaarten` | Weegkaarten bekijken |
| `/{org}/{toernooi}/school/{code}/coachkaarten` | Coach kaarten |
| `/{org}/{toernooi}/school/{code}/resultaten` | Resultaten |
| `/{org}/{toernooi}/school/{code}/afrekenen` | Betalen |

**Voorbeeld:** `/judo-ceesveen/open-2026/school/ABC123`

---

### 7. Vrijwilligers (Device Binding)

**Auth: PIN + Device binding**

Vrijwilligers krijgen een toegangslink, voeren PIN in, en hun browser wordt gekoppeld.

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}/toegang/{code}` | Toegang link + PIN invoer |
| `/{org}/{toernooi}/weging/{toegang}` | Weging interface |
| `/{org}/{toernooi}/mat/{toegang}` | Mat interface |
| `/{org}/{toernooi}/jury/{toegang}` | Jury interface |
| `/{org}/{toernooi}/spreker/{toegang}` | Spreker interface |
| `/{org}/{toernooi}/dojo/{toegang}` | Dojo scanner |

**Voorbeeld:** `/judo-ceesveen/open-2026/toegang/XYZ789`

---

### 8. QR Code URLs

Korte URLs voor QR codes (token = authenticatie).

| URL | Doel | Gegenereerd voor |
|-----|------|------------------|
| `/weegkaart/{token}` | Weegkaart tonen | Elke judoka |
| `/coach-kaart/{qrCode}` | Coach kaart + check-in | Elke coach |

> **Waarom kort?** Kortere URL = kleinere QR code = makkelijker scannen

---

### 9. Dojo Scanner API

**Auth: Geen (interne API voor dojo scanner interface)**

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}/dojo/clubs` | Lijst clubs met coach kaarten |
| `/{org}/{toernooi}/dojo/club/{club}` | Detail van club coach kaarten |

> **Gebruikt door:** Dojo scanner interface (vrijwilligers device)

---

### 10. Legacy Redirects

Oude URLs die doorverwijzen naar nieuwe structuur.

| Oude URL | Nieuwe URL |
|----------|------------|
| `/school/{code}` | `/{org}/{toernooi}/school/{code}` |
| `/toegang/{code}` | `/{org}/{toernooi}/toegang/{code}` |
| `/organisator/dashboard` | `/{org}/dashboard` |
| `/organisator/login` | `/login` |
| `/toernooi/{toernooi}` | `/{org}/toernooi/{toernooi}` |

---

## Visueel Overzicht

```
/                                          ← Homepage
├── /login                                 ← Organisator login
├── /registreren                           ← Registratie
├── /admin                                 ← Sitebeheerder
│
├── /weegkaart/{token}                     ← QR: Weegkaart
├── /coach-kaart/{qrCode}                  ← QR: Coach kaart
│
└── /{org}/                                ← Organisator context
    │
    ├── dashboard                          ← [LOGIN] Dashboard
    ├── clubs                              ← [LOGIN] Clubs beheer
    ├── templates                          ← [LOGIN] Templates
    ├── presets                            ← [LOGIN] Presets
    │
    ├── toernooi/                          ← [LOGIN] Toernooi beheer
    │   ├── nieuw                          ← Nieuw toernooi
    │   └── {toernooi}/
    │       ├── (root)                     ← Toernooi startpagina
    │       ├── edit                       ← Instellingen
    │       ├── judoka                     ← Judoka's
    │       ├── poule                      ← Poules
    │       ├── club                       ← Club selectie
    │       ├── blok                       ← Blokken
    │       ├── weging                     ← Weging
    │       ├── mat                        ← Matten
    │       └── noodplan                   ← Print
    │
    └── {toernooi}/                        ← [PUBLIEK] Publieke pagina's
        │
        ├── (root)                         ← Publieke pagina (PWA)
        │
        ├── school/{code}/                 ← [PIN] Coach portal
        │   ├── judokas
        │   ├── weegkaarten
        │   └── afrekenen
        │
        └── toegang/{code}                 ← [PIN+DEVICE] Vrijwilligers
            ├── weging/{toegang}
            ├── mat/{toegang}
            ├── jury/{toegang}
            ├── spreker/{toegang}
            └── dojo/{toegang}
```

---

## Route Middleware Samenvatting

| Route Pattern | Middleware | Auth Type |
|---------------|------------|-----------|
| `/`, `/login`, `/registreren` | `web` | Geen |
| `/admin` | `auth:organisator` + sitebeheerder check | Organisator + rol |
| `/{org}/dashboard`, `/{org}/clubs`, etc. | `auth:organisator` | Organisator |
| `/{org}/toernooi/{toernooi}/*` | `auth:organisator` | Organisator |
| `/{org}/{toernooi}` | `web` | Geen |
| `/{org}/{toernooi}/school/{code}/*` | `web` | PIN (in controller) |
| `/{org}/{toernooi}/toegang/{code}` | `web` | PIN (in controller) |
| `/{org}/{toernooi}/*/{toegang}` | `device.binding` | PIN + Device |
| `/weegkaart/{token}` | `web` | Token (in URL) |
| `/coach-kaart/{qrCode}` | `web` | QR code (in URL) |

---

## Implementatie Notities

### Route Volgorde (Belangrijk!)

Laravel matcht routes in volgorde. Specifiekere routes moeten VOOR algemenere routes:

```php
// EERST: Specifieke routes
Route::get('{org}/toernooi/{toernooi}', ...);     // beheer
Route::get('{org}/{toernooi}/school/{code}', ...); // coach portal

// LAATST: Algemene "catch-all" publieke route
Route::get('{org}/{toernooi}', ...);              // publiek
```

### Slug Constraints

Voorkom conflicten met reserved words:

```php
Route::get('{org}/{toernooi}', ...)
    ->where('org', '^(?!admin|login|registreren|weegkaart|coach-kaart).*$')
    ->where('toernooi', '^(?!dashboard|clubs|templates|presets|toernooi).*$');
```
