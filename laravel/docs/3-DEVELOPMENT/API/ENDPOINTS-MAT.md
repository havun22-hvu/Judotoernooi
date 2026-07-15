---
title: Endpoints: mat-interface
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Endpoints: mat-interface

> Onderdeel van [API-referentie](../API.md).

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

