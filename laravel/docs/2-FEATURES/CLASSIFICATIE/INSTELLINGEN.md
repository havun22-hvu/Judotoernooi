---
title: Categorieen Instellen & Presets
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Categorieen Instellen & Presets

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## UI: Categorieën Instelling

### Preset Keuze

```
┌─────────────────────────────────────────────────────────────────┐
│ Categorieën Instelling                                          │
│                                                                 │
│ [○ Geen standaard] [○ JBN 2025] [● JBN 2026] [Preset ▼] [Save] │
└─────────────────────────────────────────────────────────────────┘
```

### Sorteer Prioriteit (altijd zichtbaar)

```
┌─────────────────────────────────────────────────────────────────┐
│ Sorteer prioriteit: (sleep om te wisselen)                      │
│ [1. Leeftijd] [2. Gewicht] [3. Band]                           │
└─────────────────────────────────────────────────────────────────┘
```

### Categorie Velden

| Veld | Type | Beschrijving |
|------|------|--------------|
| Naam | text | Label (bijv. "Mini's", "Jeugd") |
| In titel | checkbox | Toon label in poule titel |
| Max leeftijd | number | Leeftijdsgrens (exclusief) |
| Geslacht | select | Gemengd / M / V |
| Systeem | select | Poules / Kruisfinale / Eliminatie |
| Max kg verschil | number | 0 = vaste klassen, >0 = variabel |
| Max lft verschil | number | Max jaren verschil in poule |
| Band filter | select | Optioneel: t/m X of vanaf X |
| Gewichtsklassen | text | Alleen bij max_kg = 0 |

### Band Filter Opties

```
┌─────────────────────────────────────────────────────────────────┐
│ Band filter: [Alle banden ▼]                                    │
├─────────────────────────────────────────────────────────────────┤
│ • Alle banden        ← geen filter                              │
│ ─────────────────────                                           │
│ • t/m wit            ← alleen witte band                        │
│ • t/m geel           ← wit + geel                               │
│ • t/m oranje         ← wit + geel + oranje (= beginners)        │
│ • t/m groen          ← wit t/m groen                            │
│ ─────────────────────                                           │
│ • vanaf geel         ← geel en hoger                            │
│ • vanaf oranje       ← oranje en hoger                          │
│ • vanaf groen        ← groen en hoger (= gevorderden)           │
│ • vanaf blauw        ← blauw en hoger                           │
└─────────────────────────────────────────────────────────────────┘
```

**Belangrijk:** Band filter is een HARD criterium voor categoriseren, niet voor sorteren!

---

## Presets

### Opslag

| Preset | Locatie |
|--------|---------|
| JBN 2025 | Hardcoded: `Toernooi::getJbn2025Gewichtsklassen()` |
| JBN 2026 | Hardcoded: `Toernooi::getJbn2026Gewichtsklassen()` |
| Eigen presets | Database: `gewichtsklassen_presets` tabel |

### Preset opslaan gedrag

Na het opslaan van een preset:
1. **Preset geselecteerd**: De opgeslagen preset wordt automatisch geselecteerd in dropdown EN radio button
2. **Scroll positie behouden**: Pagina blijft op dezelfde scroll positie (niet naar top springen)
3. **Delete knop zichtbaar**: De verwijder knop verschijnt naast de dropdown

### JBN Leeftijdsklassen (referentie)

| Klasse | U-nummer | max_leeftijd | Leeftijden |
|--------|----------|--------------|------------|
| Mini's | U7/U8 | 6/7 | 5-6 / 6-7 jaar |
| Pupillen A | U9/U10 | 8/9 | 7-8 / 8-9 jaar |
| Pupillen B | U11/U12 | 10/11 | 9-10 / 10-11 jaar |
| Aspiranten | U13/U14 | 12/13 | 11-12 / 12-13 jaar |
| Cadetten | U15 | 14 | 13-14 jaar |
| Junioren | U18 | 17 | 15-17 jaar |
| Senioren | Sen | 99 | 18+ |

**Let op:**
- U7 = **max 6 jaar** (want: Under 7)
- `max_leeftijd` in config = hoogste leeftijd die in deze categorie past
- JBN gebruikt 2-jaar ranges binnen elke categorie

---

