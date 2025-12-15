# API Documentatie

## Overzicht

De REST API is beschikbaar onder `/api/v1/`. Alle responses zijn in JSON formaat.

## Authenticatie

Momenteel is de API publiek toegankelijk. Toekomstige versies zullen authenticatie vereisen.

## Endpoints

### Toernooi

#### GET `/api/v1/toernooi/actief`

Haalt het actieve toernooi op.

**Response:**

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

**Response:**

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
      "B-pupillen": 35,
      "Dames -15": 20,
      "Heren -15": 30
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

**Response:**

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

**Response:**

```json
{
  "success": true,
  "matten": [
    { "id": 1, "nummer": 1, "naam": "Mat 1" },
    { "id": 2, "nummer": 2, "naam": "Mat 2" }
  ]
}
```

## Web Routes (AJAX)

De volgende routes kunnen via AJAX worden aangeroepen:

### Judoka Zoeken

**POST** `/toernooi/{id}/judoka/zoek?q={zoekterm}`

Zoekt judoka's op naam.

**Response:**

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

### Weging Registreren

**POST** `/toernooi/{id}/weging/{judoka_id}/registreer`

**Body:**

```json
{
  "gewicht": 33.5
}
```

**Response:**

```json
{
  "success": true,
  "binnen_klasse": true,
  "alternatieve_poule": null,
  "opmerking": null
}
```

### QR Code Scannen

**POST** `/toernooi/{id}/weging/scan-qr`

**Body:**

```json
{
  "qr_code": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response:**

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

### Wedstrijden Ophalen

**POST** `/toernooi/{id}/mat/wedstrijden`

**Body:**

```json
{
  "blok_id": 1,
  "mat_id": 2
}
```

**Response:**

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

### Uitslag Registreren

**POST** `/toernooi/{id}/mat/uitslag`

**Body:**

```json
{
  "wedstrijd_id": 101,
  "winnaar_id": 42,
  "score_wit": "Ippon",
  "score_blauw": "",
  "uitslag_type": "ippon"
}
```

**Response:**

```json
{
  "success": true
}
```

### Poule Klaar voor Spreker

**POST** `/toernooi/{id}/mat/poule-klaar`

Markeert een poule als klaar voor de spreker (alle wedstrijden gespeeld).

**Body:**

```json
{
  "poule_id": 5
}
```

**Response:**

```json
{
  "success": true,
  "message": "Poule 5 is klaar voor de spreker"
}
```

### Poule Afgeroepen (Prijzen Uitgereikt)

**POST** `/toernooi/{id}/spreker/afgeroepen`

Markeert een poule als afgeroepen (prijzen zijn uitgereikt, naar archief).

**Body:**

```json
{
  "poule_id": 5
}
```

**Response:**

```json
{
  "success": true,
  "message": "Poule 5 afgeroepen"
}
```

## Error Responses

Bij fouten wordt een JSON response met de volgende structuur geretourneerd:

```json
{
  "success": false,
  "message": "Beschrijving van de fout",
  "errors": {
    "veld": ["Validatie fout beschrijving"]
  }
}
```

HTTP status codes:
- `200` - Success
- `400` - Bad Request (validatie fout)
- `404` - Not Found
- `500` - Server Error
