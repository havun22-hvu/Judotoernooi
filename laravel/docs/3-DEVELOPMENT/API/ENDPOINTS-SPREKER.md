---
title: Endpoints: spreker & organisator
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Endpoints: spreker & organisator

> Onderdeel van [API-referentie](../API.md).

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

