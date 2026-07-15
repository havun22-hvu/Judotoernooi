---
title: Blokverdeling - UI en workflow
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blokverdeling - UI en workflow

> Onderdeel van [Blokverdeling](../BLOKVERDELING.md).


## Componenten

### 1. Sleepvak (niet verdeeld, bovenaan)
- Categorieën die nog niet in een blok zitten
- Gegroepeerd per leeftijdscategorie (inklapbaar)
- Per categorie chip: `{afkorting} {gewicht} kg ({wedstrijden})`
- Drag & drop naar blokken

### 2. Blokken
- Per blok een card met header:
  - Bloknummer
  - **Gewenst**: invoerveld (default = totaal/aantal_blokken)
  - **Actueel**: aantal wedstrijden in dit blok (real-time)
  - Afwijking badge (groen/geel/rood)
- Categorieën als chips met pin-icon rechts:
  - **Blauw + rode 📌**: geplaatst maar NIET vastgezet (klik 📌 om vast te zetten)
  - **Groen + groene ●**: vastgezet (klik ● om los te maken)
- Drop zone voor drag & drop
- **Sleepbaar tussen blokken onderling**

### 3. Overzicht Panel (rechts)
- Compacte tabel per leeftijd
- Per gewicht: bloknummer of `-` als niet verdeeld
- **●** indicator voor vastgezette categorieën
- **Real-time update** bij elke drag & drop of pin toggle

### 4. Variant Keuze Balk (onderaan, na Bereken)
- Compacte balk onder de blokken
- Knoppen #1 t/m #5 met scores (±afwijking / breaks)
- Geen grote groene melding bovenaan

---

## Knoppen

### (Her)bereken
1. Reset alle **niet-vastgezette** categorieën (terug naar sleepvak)
2. **Vastgezette categorieën (●) blijven staan** - moet eerst losgemaakt worden
3. Bereken restcapaciteit per blok (gewenst - actueel van vastgezette categorieën)
4. Genereer 5 varianten met verschillende strategieën
5. **Toon variant 1 direct in de blokken**
6. Toon variant-keuze balk onderaan om te bladeren tussen varianten

**Tip:** Om een vastgezette categorie opnieuw in te delen, klik eerst op ● om los te maken, daarna (Her)bereken.

### Variant Bladeren (#1, #2, etc.)
- Klikken op variant-knop toont die verdeling **direct in de blokken**
- Geen page reload - JavaScript update
- Vastgezette ● categorieën blijven altijd staan

### Naar Zaaloverzicht → (ook: "Verdeel over matten")
- Sla huidige blokverdeling op in database
- **Wijs matten toe** aan poules volgens sorteerlogica (zie hieronder)
- Ga naar zaaloverzicht (preview met matverdeling)
- Matten kunnen nog aangepast worden via drag & drop in zaaloverzicht

### Einde Voorbereiding
- Knop in Zaaloverzicht, pas klikken als matverdeling definitief is
- Valideert: alle judoka's hebben poule, blok en mat
- Herberekent coachkaarten
- **Weegkaarten worden nu beschikbaar** met juiste blok- en matinfo
  - Vereist: eerst "Valideer judoka's" uitgevoerd (QR-codes aangemaakt)

#### Mat-toewijzing Logica

**Per blok:**
1. Sorteer alle poules op **gewicht oplopend** (lichtste eerst)
2. Verdeel over matten met **load balancing**: poule gaat naar mat met minste wedstrijden
3. Resultaat: lichtste poules tendensen naar mat 1, zwaarste naar laatste mat
4. **Eliminatie poules**: `b_mat_id` wordt standaard gelijk aan `mat_id` (beide groepen op zelfde mat)

#### Eliminatie A/B Split (Zaaloverzicht)

Eliminatie poules hebben A-groep (hoofdboom) en B-groep (herkansing). De B-groep kan op een **aparte mat** worden gezet:

- **Zaaloverzicht**: Eliminatie poule met `b_mat_id ≠ mat_id` toont **2 chips**: `#N - A` en `#N - B`
- **Drag & drop**: Elk chip is apart sleepbaar naar een andere mat
- **API**: `verplaatsPoule` accepteert `groep` parameter (A of B) → update `mat_id` of `b_mat_id`
- **Mat interface**: Toont alleen de relevante groep (A of B) per mat

> **Zie:** `ELIMINATIE/README.md` → "B-groep op Aparte Mat" voor technische details

**Tussen blokken:**
- Blok 1 = jongste/lichtste categorieën
- Laatste blok = oudste/zwaarste categorieën
- (Dit wordt al geregeld door de blokverdeling zelf)

#### Afgeleide poules erven de mat (conversie op de wedstrijddag)

Wanneer op de wedstrijddag een **bestaande** poule wordt omgezet of opgesplitst,
erven de nieuwe poules zowel `blok_id` **als `mat_id`** van de bronpoule — de
categorie blijft dus op dezelfde mat staan. De matverdeling (`MatAssigner`) draait
namelijk eenmalig bij "Naar Zaaloverzicht"; poules die ná dat moment ontstaan
krijgen geen automatische mat en zouden anders op géén mat verschijnen.

Geldt voor alle afgeleide-poule flows:
- **Eliminatie → poules** (`WedstrijddagController::zetOmNaarPoules`): de nieuwe
  `voorronde`-poules én een eventuele `kruisfinale` erven `mat_id` van de
  oorspronkelijke eliminatie-poule.
- **Type wijzigen → kruisfinale** (`WedstrijddagController::wijzigType`): de
  kruisfinale erft `mat_id` van de poule waarvoor hij wordt aangemaakt.
- **Handmatig poule toevoegen** (`PouleController`): erft `mat_id` van een
  bestaande poule in dezelfde leeftijdsklasse (zelfde categorie = zelfde mat).

`b_mat_id` is hierbij niet nodig: voorronde/kruisfinale draaien op één mat (alleen
eliminatie-poules gebruiken de A/B-splitsing hierboven).

#### Poule Weergave Volgorde (Wedstrijddag)

**Binnen een blok:** Poules worden gesorteerd van **licht naar zwaar** (makkelijker te vinden voor ouders/coaches).

---

## Drag & Drop Gedrag

### Van Sleepvak naar Blok
- Categorie wordt **blauw met rode 📌** (NIET vastgezet)
- Wordt WEL meegenomen bij "Bereken"
- Klik op 📌 om vast te zetten

### Van Blok naar Blok
- Categorie blijft in huidige staat (vast of niet vast)
- Klik op pin-icon om te togglen

### Van Blok naar Sleepvak
- Categorie wordt paars (geen pin)
- Wordt WEL meegenomen bij "Bereken"

### Pin Toggle (klik op pin-icon)
- **📌 → ●**: Categorie wordt vastgezet (groen)
- **● → 📌**: Categorie wordt losgemaakt (blauw)

---

## Vastgezet (●) vs Niet-Vastgezet (📌)

| Status | Weergave | Gedrag bij "(Her)bereken" |
|--------|----------|---------------------------|
| In sleepvak | Paars, geen pin | Wordt verdeeld |
| In blok, niet vast | Blauw + rode 📌 | Wordt opnieuw verdeeld (terug naar sleepvak, dan herverdeeld) |
| In blok, vastgezet | Groen + groene ● | **Blijft staan** (klik ● om los te maken) |

---

## Workflow

### Voorbereiding (voor toernooi)
1. **Start**: Alle categorieën staan in sleepvak
2. **Optioneel**: Sleep specifieke categorieën naar gewenste blokken (krijgen 📍)
3. **Bereken**: Solver verdeelt rest optimaal, variant 1 direct zichtbaar
4. **Blader**: Bekijk varianten #1-#5, kies de beste
5. **Optioneel**: Pas handmatig aan met drag & drop
6. **Naar Zaaloverzicht**: Opslaan blokverdeling → preview zaaloverzicht

### Toernooidag
1. **Wedstrijddag Poules**: Overpoelen (afwezigen/te zware judoka's)
2. **Per poule "→" klikken**: Doorsturen naar zaaloverzicht (knop wordt ✓)
3. **Zaaloverzicht**: Witte chip klikken → wedstrijdschema genereren (chip wordt groen)
4. **Mat Interface**: Wedstrijden afwerken

---
