# API Documentatie

> **Versie:** 1.0
> **Base URL:** `https://judotournament.org/api/v1/`
> **Content-Type:** `application/json`

## Inhoudsopgave

- [Authenticatie](#authenticatie)
- [Request/Response Headers](#requestresponse-headers)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
  - [Toernooi](#toernooi)
  - [Judoka](#judoka)
  - [Weging](#weging)
  - [Mat Interface](#mat-interface)
  - [Spreker Interface](#spreker-interface)
  - [Organisator (Auth Required)](#organisator-routes-auth-required)
- [Error Handling](#error-handling)
- [Voorbeelden (cURL)](#voorbeelden-curl)

---

## Authenticatie

### Publieke Endpoints
De volgende endpoints zijn publiek toegankelijk (geen authenticatie vereist):
- `/api/v1/toernooi/*` - Toernooi informatie
- `/{organisator}/{toernooi}/*` - Publieke toernooi views

### Organisator Endpoints
Routes onder `/organisator/*` vereisen sessie-authenticatie via Laravel.

```bash
# Login verkrijgen
POST /organisator/login
Content-Type: application/x-www-form-urlencoded

email=user@example.com&password=secret&_token=CSRF_TOKEN
```

### CSRF Protection
Alle POST/PUT/DELETE requests naar web routes vereisen een CSRF token.

**Uitgezonderd van CSRF:**
- `/{organisator}/{toernooi}/favorieten` - Publieke favorites API
- `/{organisator}/{toernooi}/scan-qr` - Publieke QR scan
- `/mollie/webhook` - Mollie webhook callbacks

---

## Request/Response Headers

### Request Headers
| Header | Waarde | Verplicht |
|--------|--------|-----------|
| `Content-Type` | `application/json` | Ja (voor JSON body) |
| `Accept` | `application/json` | Aanbevolen |
| `X-Requested-With` | `XMLHttpRequest` | Voor AJAX requests |

### Response Headers
| Header | Beschrijving |
|--------|--------------|
| `X-Frame-Options` | `SAMEORIGIN` - Clickjacking bescherming |
| `X-Content-Type-Options` | `nosniff` - MIME sniffing bescherming |
| `X-XSS-Protection` | `1; mode=block` - XSS filter |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | CSP headers (productie) |
| `Strict-Transport-Security` | HSTS (productie, HTTPS) |

---

## Rate Limiting

| Endpoint Type | Limiet | Window |
|---------------|--------|--------|
| API requests | 60 requests | per minuut |
| Login attempts | 5 attempts | per minuut |
| QR scan | 30 requests | per minuut |

Bij overschrijding: HTTP `429 Too Many Requests`

```json
{
  "message": "Too Many Attempts.",
  "retry_after": 45
}
```

---

## Endpoints

### Toernooi

#### GET `/api/v1/toernooi/actief`

Haalt het actieve toernooi op.

**Response:** `200 OK`
```json
{
  "success": true,
  "toernooi": {
    "id": 1,
    "naam": "6e WestFries Open",
    "datum": "2025-10-25",
    "organisatie": "Judoschool Cees Veen"
  }
}
```

#### GET `/api/v1/toernooi/{id}/statistieken`

Haalt statistieken op voor een toernooi.

**Parameters:**
| Parameter | Type | Beschrijving |
|-----------|------|--------------|
| `id` | integer | Toernooi ID |

**Response:** `200 OK`
```json
{
  "success": true,
  "statistieken": {
    "totaal_judokas": 150,
    "totaal_poules": 35,
    "totaal_wedstrijden": 280,
    "aanwezig": 120,
    "afwezig": 5,
    "onbekend": 25,
    "gewogen": 118,
    "per_leeftijdsklasse": {
      "Mini's": 25,
      "A-pupillen": 40,
      "B-pupillen": 35
    },
    "per_blok": {
      "1": { "poules": 6, "wedstrijden": 48, "weging_gesloten": true },
      "2": { "poules": 6, "wedstrijden": 45, "weging_gesloten": false }
    }
  }
}
```

#### GET `/api/v1/toernooi/{id}/blokken`

Haalt alle blokken op voor een toernooi.

**Response:** `200 OK`
```json
{
  "success": true,
  "blokken": [
    { "id": 1, "nummer": 1, "naam": "Blok 1", "weging_gesloten": true },
    { "id": 2, "nummer": 2, "naam": "Blok 2", "weging_gesloten": false }
  ]
}
```

#### GET `/api/v1/toernooi/{id}/matten`

Haalt alle matten op voor een toernooi.

**Response:** `200 OK`
```json
{
  "success": true,
  "matten": [
    { "id": 1, "nummer": 1, "naam": "Mat 1" },
    { "id": 2, "nummer": 2, "naam": "Mat 2" }
  ]
}
```

---

### Judoka

#### POST `/toernooi/{id}/judoka/zoek`

Zoekt judoka's op naam.

**Query Parameters:**
| Parameter | Type | Beschrijving |
|-----------|------|--------------|
| `q` | string | Zoekterm (min. 2 karakters) |

**Response:** `200 OK`
```json
[
  {
    "id": 42,
    "naam": "Jan de Vries",
    "club": "Judo Club Noord",
    "leeftijdsklasse": "B-pupillen",
    "gewichtsklasse": "-34",
    "aanwezig": true
  }
]
```

---

### Weging

#### POST `/toernooi/{id}/weging/{judoka_id}/registreer`

Registreert het gewicht van een judoka.

**Request Body:**
```json
{
  "gewicht": 33.5
}
```

**Validatie:**
| Veld | Regels |
|------|--------|
| `gewicht` | required, numeric, min:10, max:200 |

**Response:** `200 OK`
```json
{
  "success": true,
  "binnen_klasse": true,
  "alternatieve_poule": null,
  "opmerking": null
}
```

**Response (te zwaar):** `200 OK`
```json
{
  "success": true,
  "binnen_klasse": false,
  "alternatieve_poule": {
    "id": 12,
    "naam": "B-pupillen -38 kg"
  },
  "opmerking": "Judoka is 1.2 kg te zwaar voor oorspronkelijke klasse"
}
```

#### POST `/toernooi/{id}/weging/scan-qr`

Scant een QR code om een judoka op te halen.

**Request Body:**
```json
{
  "qr_code": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "judoka": {
    "id": 42,
    "naam": "Jan de Vries",
    "club": "Judo Club Noord",
    "leeftijdsklasse": "B-pupillen",
    "gewichtsklasse": "-34",
    "blok": 2,
    "mat": 3,
    "aanwezig": false,
    "gewicht_gewogen": null
  }
}
```

**Response (niet gevonden):** `404 Not Found`
```json
{
  "success": false,
  "message": "QR code niet gevonden"
}
```

---

### Mat Interface

#### POST `/toernooi/{id}/mat/wedstrijden`

Haalt wedstrijden op voor een specifieke mat en blok.

**Request Body:**
```json
{
  "blok_id": 1,
  "mat_id": 2
}
```

**Response:** `200 OK`
```json
[
  {
    "poule_id": 5,
    "titel": "B-pupillen -34 kg Poule 5",
    "judokas": [
      { "id": 42, "naam": "Jan de Vries", "club": "Judo Club Noord", "band": "oranje" }
    ],
    "wedstrijden": [
      {
        "id": 101,
        "volgorde": 1,
        "wit": { "id": 42, "naam": "Jan de Vries" },
        "blauw": { "id": 43, "naam": "Piet Jansen" },
        "is_gespeeld": false,
        "winnaar_id": null
      }
    ]
  }
]
```

#### POST `/toernooi/{id}/mat/uitslag`

Registreert de uitslag van een wedstrijd.

**Request Body:**
```json
{
  "wedstrijd_id": 101,
  "winnaar_id": 42,
  "score_wit": "Ippon",
  "score_blauw": "",
  "uitslag_type": "ippon"
}
```

**Validatie:**
| Veld | Regels |
|------|--------|
| `wedstrijd_id` | required, exists:wedstrijden,id |
| `winnaar_id` | required, exists:judokas,id |
| `score_wit` | nullable, string |
| `score_blauw` | nullable, string |
| `uitslag_type` | required, in:ippon,wazari,beslissing,hantei |

**Response:** `200 OK`
```json
{
  "success": true
}
```

#### POST `/toernooi/{id}/mat/poule-klaar`

Markeert een poule als klaar voor de spreker.

**Request Body:**
```json
{
  "poule_id": 5
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Poule 5 is klaar voor de spreker"
}
```

---

### Spreker Interface

#### POST `/toernooi/{id}/spreker/afgeroepen`

Markeert een poule als afgeroepen (prijzen uitgereikt).

**Request Body:**
```json
{
  "poule_id": 5
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Poule 5 afgeroepen"
}
```

---

### Organisator Routes (Auth Required)

> ⚠️ Deze routes vereisen authenticatie als organisator.

#### GET `/organisator/presets`

Haalt alle gewichtsklassen presets op.

**Response:** `200 OK`
```json
[
  {
    "id": 1,
    "naam": "Standaard IJF",
    "configuratie": {
      "minis": {
        "label": "Mini's",
        "max_leeftijd": 8,
        "geslacht": "gemengd",
        "gewichten": ["-24", "-27", "-30", "-34", "+34"]
      }
    }
  }
]
```

#### POST `/organisator/presets`

Slaat een nieuwe preset op.

**Request Body:**
```json
{
  "naam": "Mijn preset",
  "configuratie": {
    "minis": {
      "label": "Mini's",
      "max_leeftijd": 8,
      "geslacht": "gemengd",
      "gewichten": ["-24", "-27", "-30"]
    }
  }
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "preset": { "id": 1, "naam": "Mijn preset" },
  "message": "Preset opgeslagen"
}
```

#### DELETE `/organisator/presets/{id}`

Verwijdert een preset.

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Preset verwijderd"
}
```

---

## Error Handling

### HTTP Status Codes

| Code | Beschrijving |
|------|--------------|
| `200` | OK - Request succesvol |
| `201` | Created - Resource aangemaakt |
| `400` | Bad Request - Validatie fout |
| `401` | Unauthorized - Niet ingelogd |
| `403` | Forbidden - Geen toegang |
| `404` | Not Found - Resource niet gevonden |
| `419` | Page Expired - CSRF token verlopen |
| `422` | Unprocessable Entity - Validatie errors |
| `429` | Too Many Requests - Rate limit bereikt |
| `500` | Internal Server Error - Server fout |

### Error Response Format

```json
{
  "success": false,
  "message": "Beschrijving van de fout",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "gewicht": ["Het gewicht veld is verplicht."]
  }
}
```

### Custom Error Codes

| Code | Beschrijving |
|------|--------------|
| `VALIDATION_ERROR` | Input validatie gefaald |
| `MOLLIE_ERROR` | Betaling fout |
| `IMPORT_ERROR` | Import fout |
| `EXTERNAL_SERVICE_ERROR` | Externe service niet beschikbaar |

---

## Voorbeelden (cURL)

### Toernooi statistieken ophalen

```bash
curl -X GET "https://judotournament.org/api/v1/toernooi/1/statistieken" \
  -H "Accept: application/json"
```

### Judoka zoeken

```bash
curl -X POST "https://judotournament.org/toernooi/1/judoka/zoek?q=Jan" \
  -H "Accept: application/json" \
  -H "X-Requested-With: XMLHttpRequest"
```

### Gewicht registreren

```bash
curl -X POST "https://judotournament.org/toernooi/1/weging/42/registreer" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{"gewicht": 33.5}'
```

### Uitslag registreren

```bash
curl -X POST "https://judotournament.org/toernooi/1/mat/uitslag" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{
    "wedstrijd_id": 101,
    "winnaar_id": 42,
    "score_wit": "Ippon",
    "score_blauw": "",
    "uitslag_type": "ippon"
  }'
```

---

## WebSocket Events (Reverb)

De applicatie gebruikt Laravel Reverb voor real-time updates.

### Kanalen

| Kanaal | Beschrijving |
|--------|--------------|
| `toernooi.{id}` | Toernooi-brede events |
| `mat.{id}` | Mat-specifieke updates |
| `spreker.{id}` | Spreker interface updates |

### Events

| Event | Payload |
|-------|---------|
| `WedstrijdGespeeld` | `{ wedstrijd_id, winnaar_id, poule_id }` |
| `PouleKlaar` | `{ poule_id, mat_id }` |
| `PouleAfgeroepen` | `{ poule_id }` |
| `WegingGeregistreerd` | `{ judoka_id, gewicht }` |

---

*Laatste update: Februari 2026*
