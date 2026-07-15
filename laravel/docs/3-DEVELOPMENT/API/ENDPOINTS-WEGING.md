---
title: Endpoints: weging
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Endpoints: weging

> Onderdeel van [API-referentie](../API.md).

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

