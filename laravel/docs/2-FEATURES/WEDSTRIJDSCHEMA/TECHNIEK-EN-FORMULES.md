---
title: Technische implementatie en formules
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Technische implementatie en formules

> Onderdeel van [Wedstrijdschema Systeem](../WEDSTRIJDSCHEMA.md).

## Technische Implementatie

### Database Tabellen
- `matten` - Mat informatie + actieve/volgende wedstrijd IDs
- `poules` - Poule informatie inclusief type (voorronde/kruisfinale)
- `wedstrijden` - Individuele wedstrijden met scores
- `poule_judoka` - Koppeltabel met eindstanden

### Relevante Bestanden
- `app/Models/Poule.php` - Wedstrijdvolgorde generatie
- `app/Services/WedstrijdSchemaService.php` - Schema beheer
- `app/Http/Controllers/MatController.php` - Mat interface
- `resources/views/pages/mat/interface.blade.php` - UI

### API Endpoints
| Method | Endpoint | Beschrijving |
|--------|----------|--------------|
| POST | `/mat/wedstrijden` | Haal wedstrijden op voor mat/blok |
| POST | `/mat/uitslag` | Registreer wedstrijduitslag |
| POST | `/mat/poule-klaar` | Markeer poule als klaar voor spreker |

## Formules

### Aantal Wedstrijden (Enkele Poule)
```
wedstrijden = n × (n - 1) / 2

Waarbij n = aantal judoka's
```

### Aantal Wedstrijden (Dubbele Poule)
```
wedstrijden = n × (n - 1)

Toegepast bij 2 of 3 judoka's
```

### Voorbeelden
| Judoka's | Type | Wedstrijden |
|----------|------|-------------|
| 2 | Standaard | 2 |
| 2 | Best of Three | 3 |
| 3 | Dubbel | 6 |
| 4 | Enkel | 6 |
| 5 | Enkel | 10 |
| 6 | Enkel | 15 |
| 7 | Enkel | 21 |
| 8 | Enkel | 28 |
