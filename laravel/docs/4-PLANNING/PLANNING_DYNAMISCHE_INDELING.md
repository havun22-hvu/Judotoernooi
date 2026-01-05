# Planning: Dynamische Poule Indeling

> **Status:** Gepland voor 2026
> **Doel:** Flexibele indeling op basis van gewichtsverschil i.p.v. vaste gewichtsklassen

## Overzicht

Nieuw indelingssysteem waarbij de organisator per leeftijdsgroep kan kiezen tussen:
1. **Vaste gewichtsklassen** (huidige systeem, JBN normen)
2. **Dynamische indeling** (nieuw, op basis van max kg verschil)

## Nieuwe Velden per Leeftijdsgroep

| Veld | Type | Opties | Beschrijving |
|------|------|--------|--------------|
| `geslacht` | enum | gemengd / jongens / meisjes | Welke judoka's in deze groep |
| `max_kg_verschil` | decimal | 0-10 | 0 = vaste klassen, >0 = dynamisch |

## UI Voorbeeld

```
┌─────────────────────────────────────────────────────────────────┐
│ [JBN 2025]  [JBN 2026]  ← Presets: vult alles automatisch in   │
├─────────────────────────────────────────────────────────────────┤
│ Max: [8]  Naam: [Mini's]  Geslacht: [Gemengd ▼]                │
│ Systeem: [Poules ▼]  Max kg verschil: [3]                      │
│ (Gewichtsklassen niet nodig - dynamische indeling)             │
├─────────────────────────────────────────────────────────────────┤
│ Max: [15]  Naam: [Dames -15]  Geslacht: [Meisjes ▼]            │
│ Systeem: [Kruisfinale ▼]  Max kg verschil: [3]                 │
├─────────────────────────────────────────────────────────────────┤
│ Max: [99]  Naam: [Heren]  Geslacht: [Jongens ▼]                │
│ Systeem: [Poules ▼]  Max kg verschil: [0]                      │
│ Gewichtsklassen: -60, -66, -73, -81, +81                        │
└─────────────────────────────────────────────────────────────────┘
```

## Presets

```
┌─────────────────────────────────────────────────────────────────┐
│ [JBN 2025]  [JBN 2026]  [Eigen preset ▼]  [Opslaan als preset] │
└─────────────────────────────────────────────────────────────────┘
```

### Standaard Presets (JBN)
De knoppen **JBN 2025** en **JBN 2026** laden de officiële JBN indeling:
- Alle leeftijdsklassen met correcte leeftijdsgrenzen
- Vaste gewichtsklassen per categorie (max_kg_verschil = 0)
- Geslacht: Mini's & Pupillen gemengd, vanaf U15 gescheiden

### Eigen Presets
Organisator kan huidige configuratie opslaan als eigen preset:
- Klik **Opslaan als preset** → voer naam in
- Preset wordt opgeslagen bij de organisator (user)
- Later laden via dropdown **Eigen preset**

**Database:** `gewichtsklassen_presets` tabel
```
id, user_id, naam, configuratie (JSON), timestamps
unique: [user_id, naam]
```

### Na laden preset
Organisator kan altijd aanpassen:
- Geslacht per groep wijzigen
- Overschakelen naar dynamische indeling (max_kg_verschil > 0)
- Gewichtsklassen aanpassen

## Algoritme: Dynamische Indeling

```
═══════════════════════════════════════════════════════════════════
HIËRARCHIE (van groot naar klein)
═══════════════════════════════════════════════════════════════════

1. GESLACHT    - M/V apart (indien niet gemengd)
2. LEEFTIJD    - Organisator bepaalt groepen + namen (versleepbaar)
3. GROEPERING  - 1e sortering: bepaalt de KLASSEN (zie opties)
4. SORTERING   - 2e sortering: bepaalt VOLGORDE binnen de klasse

═══════════════════════════════════════════════════════════════════
DRIE OPTIES VOOR GROEPERING + SORTERING
═══════════════════════════════════════════════════════════════════

┌─────────────────────────────────────────────────────────────────┐
│ OPTIE 1: Vaste gewichtsklassen                                  │
├─────────────────────────────────────────────────────────────────┤
│ 1e: Groepering op vaste klassen (-30, -35, -40, etc.)           │
│ 2e: Binnen klasse sorteren op band                              │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ OPTIE 2: Dynamisch GEWICHT → BAND                               │
├─────────────────────────────────────────────────────────────────┤
│ 1e: Groepering op gewicht (breekpunten bij >X kg verschil)      │
│     → 30-33kg wordt 1 klasse, exact gewicht niet meer relevant  │
│ 2e: Binnen klasse sorteren op band                              │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ OPTIE 3: Dynamisch BAND → GEWICHT                               │
├─────────────────────────────────────────────────────────────────┤
│ 1e: Groepering op band (wit, geel, oranje, etc.)                │
│     → alle witte banden = 1 klasse                              │
│ 2e: Binnen klasse sorteren op gewicht (+ max kg check)          │
└─────────────────────────────────────────────────────────────────┘

═══════════════════════════════════════════════════════════════════
VOORBEELD: OPTIE 2 (GEWICHT → BAND)
═══════════════════════════════════════════════════════════════════

STAP 1: Sorteer op gewicht → bepaalt GRENZEN (breekpunten)

        28, 29, 30, 31, 32 │ 38, 39, 40
                           ↑
                      breekpunt (>3kg)

        Klasse A = 28-32kg (als 1 groep!)
        Klasse B = 38-40kg


STAP 2: Binnen Klasse A → sorteer ALLEEN op band

        Judoka's in Klasse A:
        - Piet: 30kg, oranje
        - Jan: 32kg, wit
        - Kees: 28kg, geel
        - Lisa: 31kg, wit

        Gesorteerd op BAND (exact gewicht niet meer relevant):
        1. Jan (32kg, wit)    ← wit eerst, ook al zwaarder
        2. Lisa (31kg, wit)
        3. Kees (28kg, geel)
        4. Piet (30kg, oranje)

        → Verdeel in poules (voorkeur: 5, 4, 6, 3)

═══════════════════════════════════════════════════════════════════
JUDOKACODE (weerspiegelt hiërarchie)
═══════════════════════════════════════════════════════════════════

[Leeftijd]-[Gewicht]-[Band]-[M/V]

Voorbeeld: 08-032-03-M = Leeftijd 8, 32kg, oranje band, Jongen
```

## Voorbeeld

**Input:** 8 judoka's, max 3 kg verschil
```
Gesorteerd: 28kg, 29kg, 30kg, 31kg, 32kg, 38kg, 39kg, 40kg
                                              ↑
                                         breekpunt (6kg > 3kg)

Groep 1: 28-32kg (5 judoka's) → 1 poule van 5
Groep 2: 38-40kg (3 judoka's) → 1 poule van 3
```

## Database Wijzigingen

### Migration: toernooien tabel

Bestaande `gewichtsklassen` JSON uitbreiden met nieuwe velden per categorie:

```php
// Huidige structuur
'minis' => [
    'max_leeftijd' => 8,
    'label' => "Mini's",
    'gewichten' => ['-20', '-23', '-26', '+26'],
]

// Nieuwe structuur
'minis' => [
    'max_leeftijd' => 8,
    'label' => "Mini's",
    'geslacht' => 'gemengd',           // nieuw: gemengd/jongens/meisjes
    'max_kg_verschil' => 3,            // nieuw: 0 = vaste klassen
    'gewichten' => [],                  // leeg bij dynamische indeling
]
```

## Implementatie Stappen

### Fase 1: Database & UI (5 jan 2026)
- [x] Gewichtsklassen JSON structuur uitbreiden
- [x] UI aanpassen: geslacht dropdown per categorie
- [x] UI aanpassen: max kg verschil input per categorie
- [x] Gewichtsklassen input verbergen als max_kg > 0
- [x] Controller updaten voor nieuwe velden
- [x] Eigen presets: migration + model
- [x] Eigen presets: controller endpoints (GET/POST/DELETE)
- [x] Eigen presets: UI dropdown + opslaan knop

### Fase 2: Indeling Algoritme
- [ ] Nieuwe service: `DynamischeIndelingService`
- [ ] Breekpunten algoritme implementeren
- [ ] Integreren met bestaande `PouleIndelingService`

### Fase 3: Testen
- [ ] Unit tests voor breekpunten algoritme
- [ ] Test met verschillende datasets
- [ ] Edge cases: grote gewichtsgaten, weinig judoka's

## Edge Cases

| Situatie | Oplossing |
|----------|-----------|
| Groep met 1-2 judoka's | Voeg toe aan dichtstbijzijnde groep |
| Alle judoka's binnen max kg | Eén grote groep, verdeel in poules |
| Geen judoka's in leeftijdsgroep | Skip |

## Notities

- Band-sortering is secundair: zorgt voor oplopende ervaring binnen gewichtsgroep
- Clubspreiding blijft werken zoals nu
- Wedstrijdsysteem (poules/kruisfinale/eliminatie) blijft per leeftijdsgroep
