# Wedstrijdschema Systeem

Dit document beschrijft het wedstrijdschema systeem van JudoToernooi.

## Overzicht

Het wedstrijdschema bepaalt de volgorde van wedstrijden binnen een poule. Het systeem zorgt ervoor dat:
- Elke judoka tegen elke andere judoka vecht (round-robin)
- Judoka's voldoende rust krijgen tussen wedstrijden
- De ranking correct wordt berekend

## Poule Types

### 1. Voorronde Poules
Standaard poules waarin alle judoka's van een categorie worden ingedeeld.

### 2. Kruisfinale Poules
Finalepoules voor de beste judoka's uit de voorrondes:
- Plaats 1 (of 1 én 2) van elke voorronde gaat door
- Nieuw wedstrijdschema wordt gegenereerd
- Hoofdjury tafel wijst de mat aan

## Wedstrijdschema Matrix

Het schema wordt weergegeven als een matrix:

```
         │ Wed 1  │ Wed 2  │ Wed 3  │ Wed 4  │ Wed 5  │ Wed 6  │ Totaal │
         │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ Plts
─────────┼────────┼────────┼────────┼────────┼────────┼────────┼────────┼─────
Judoka 1 │ □   □  │ ██████ │ □   □  │ ██████ │ □   □  │ ██████ │  6  25 │  1
Judoka 2 │ □   □  │ □   □  │ ██████ │ ██████ │ ██████ │ □   □  │  4  20 │  2
Judoka 3 │ ██████ │ □   □  │ □   □  │ □   □  │ ██████ │ ██████ │  4  15 │  3
Judoka 4 │ ██████ │ ██████ │ ██████ │ □   □  │ □   □  │ □   □  │  2  10 │  4
```

- `□` = Wit vak, invulbaar (judoka speelt in deze wedstrijd)
- `██` = Grijs vak, geblokkeerd (judoka speelt niet in deze wedstrijd)

### Kolom Headers (UI)
Elke wedstrijdkolom heeft **twee regels** in de header:
1. **Wedstrijdnummer** (1, 2, 3...) — klikbaar voor beurt-aanduiding (groen/geel/blauw)
2. **Sub-labels "wp jp"** — kleine grijze tekst die aangeeft welk invoerveld WP is en welk JP

```
│  1   │  2   │  3   │
│ wp jp│ wp jp│ wp jp│
```

**Implementatie:** `_content.blade.php` — sub-labels als inline `<div>` met `font-size: 9px` onder het wedstrijdnummer.
De breedte van `wp` (w-5) en `jp` (w-7) komt overeen met de invoervelden eronder.

## Puntensysteem

### Winstpunten (WP)
| Resultaat | Punten |
|-----------|--------|
| Winst     | 2      |
| Verlies   | 0      |

### Judopunten (JP)
| Techniek   | Punten |
|------------|--------|
| Ippon      | 10     |
| Waza-ari   | 7      |
| Yuko       | 5      |
| Geen score | 0      |

## Ranking Bepaling

De plaats wordt bepaald in deze volgorde:

1. **Hoogste WP** - Meer gewonnen wedstrijden = hogere plaats
2. **Hoogste JP** - Bij gelijke WP: meer judopunten = hogere plaats
3. **Onderlinge wedstrijd** - Bij gelijke WP én JP: winnaar van hun directe wedstrijd

### Voorbeeld
```
Judoka A: 4 WP, 20 JP
Judoka B: 4 WP, 20 JP
→ Beiden gelijk in WP en JP
→ In hun onderlinge wedstrijd won A van B
→ Judoka A wordt hoger geplaatst
```

## Optimale Wedstrijdvolgorde

Om judoka's voldoende rust te geven, worden wedstrijden in een optimale volgorde gepland. Elke judoka krijgt minimaal één wedstrijd rust tussen zijn/haar wedstrijden.

### 2 Judoka's (Configureerbaar)

| Modus | Wedstrijden | Beschrijving |
|-------|-------------|--------------|
| Standaard | 2 | 1x tegen elkaar (heen + terug) |
| Best of Three | 3 | 3x tegen elkaar |

**Instelling:** Toernooi → Instellingen → "Best of Three bij 2 deelnemers"

```
Standaard:
Wed 1: 1 vs 2
Wed 2: 2 vs 1

Best of Three:
Wed 1: 1 vs 2
Wed 2: 2 vs 1
Wed 3: 1 vs 2
```

### 3 Judoka's (Dubbele Poule)
```
Wed 1: 1 vs 2    Wed 4: 1 vs 2  (herhaling)
Wed 2: 1 vs 3    Wed 5: 1 vs 3
Wed 3: 2 vs 3    Wed 6: 2 vs 3
```

### 4 Judoka's
```
Wed 1: 1 vs 2    (3 en 4 rusten)
Wed 2: 3 vs 4    (1 en 2 rusten)
Wed 3: 2 vs 3    (1 en 4 rusten)
Wed 4: 1 vs 4    (2 en 3 rusten)
Wed 5: 2 vs 4    (1 en 3 rusten)
Wed 6: 1 vs 3    (2 en 4 rusten)
```

### 5 Judoka's
```
Wed 1:  1 vs 2    Wed 6:  1 vs 3
Wed 2:  3 vs 4    Wed 7:  2 vs 4
Wed 3:  1 vs 5    Wed 8:  3 vs 5
Wed 4:  2 vs 3    Wed 9:  1 vs 4
Wed 5:  4 vs 5    Wed 10: 2 vs 5
```

### 6 Judoka's
```
Wed 1:  1 vs 2    Wed 6:  4 vs 6    Wed 11: 4 vs 5
Wed 2:  3 vs 4    Wed 7:  3 vs 5    Wed 12: 3 vs 6
Wed 3:  5 vs 6    Wed 8:  2 vs 4    Wed 13: 1 vs 4
Wed 4:  1 vs 3    Wed 9:  1 vs 6    Wed 14: 2 vs 6
Wed 5:  2 vs 5    Wed 10: 2 vs 3    Wed 15: 1 vs 5
```

### 7+ Judoka's
Voor 7 of meer judoka's wordt het "Circle Method" round-robin algoritme gebruikt. Dit garandeert een eerlijke verdeling waarbij elke judoka tegen iedereen vecht.

## Wedstrijddag Workflow

### 1. Voorronde
```
┌─────────────────────────────────────────────────────────────┐
│  VOORRONDE                                                  │
├─────────────────────────────────────────────────────────────┤
│  1. Categorie wordt geactiveerd in Zaaloverzicht            │
│  2. Wedstrijdschema wordt automatisch gegenereerd           │
│  3. Mat jury vult WP en JP in per wedstrijd                 │
│  4. Plaatsen worden automatisch berekend                    │
│  5. Bij "Klaar" → uitslagen naar Spreker interface          │
└─────────────────────────────────────────────────────────────┘
```

### 2. Kruisfinale (optioneel)
```
┌─────────────────────────────────────────────────────────────┐
│  KRUISFINALE                                                │
├─────────────────────────────────────────────────────────────┤
│  1. Beste judoka's (plaats 1 of 1+2) worden geselecteerd    │
│  2. Nieuwe kruisfinale poule wordt aangemaakt               │
│  3. Hoofdjury wijst mat aan                                 │
│  4. Nieuw wedstrijdschema wordt gegenereerd                 │
│  5. Zelfde proces als voorronde                             │
└─────────────────────────────────────────────────────────────┘
```

## Kruisfinale Configuratie

Het aantal plaatsen dat doorgaat naar de kruisfinale wordt ingesteld in de **Toernooi Instellingen**. Dit is een toernooi-brede instelling.

| Instelling | Beschrijving |
|------------|--------------|
| 1 plaats   | Alleen nummer 1 van elke voorronde |
| 2 plaatsen | Nummer 1 én 2 van elke voorronde |
| 3 plaatsen | Nummer 1, 2 én 3 van elke voorronde |

Deze instelling is te vinden bij: **Toernooi → Instellingen → Kruisfinale plaatsen**

## Rollen en Verantwoordelijkheden

### Mat Jury
- Vult WP en JP in per wedstrijd
- Klikt "Klaar" wanneer poule is afgerond
- Kan fouten corrigeren tot poule definitief is

### Hoofdjury Tafel
- Wijst matten toe aan kruisfinales
- Bewaakt de voortgang
- Beslist bij geschillen

### Spreker
- Ontvangt uitslagen automatisch na "Klaar"
- Roept winnaars om voor prijsuitreiking
- Ziet uitslagen in volgorde van binnenkomst (FIFO)

## Wedstrijd Selectie (Groen/Geel)

> **Uitgebreide documentatie:** Zie `MAT-WEDSTRIJD-SELECTIE.md`

De actieve wedstrijd (groen = speelt nu) en volgende wedstrijd (geel = klaar maken) worden opgeslagen op **mat niveau**, niet op poule niveau. Dit zorgt ervoor dat er per mat altijd maar 1 wedstrijd tegelijk kan spelen, ongeacht het aantal poules op die mat.

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
