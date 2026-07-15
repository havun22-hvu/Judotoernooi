---
title: Endpoints: toernooi & judoka
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Endpoints: toernooi & judoka

> Onderdeel van [API-referentie](../API.md).

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

