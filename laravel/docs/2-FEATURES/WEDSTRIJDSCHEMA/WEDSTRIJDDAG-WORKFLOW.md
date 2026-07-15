---
title: Wedstrijddag-workflow, kruisfinale en rollen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Wedstrijddag-workflow, kruisfinale en rollen

> Onderdeel van [Wedstrijdschema Systeem](../WEDSTRIJDSCHEMA.md).

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

