# Blokverdeling - Functionele Specificatie

## Doel
Verdeel alle categorieën (leeftijd + gewicht combinaties) over de beschikbare blokken, zodat:
1. Elk blok ongeveer evenveel wedstrijden heeft
2. Aansluitende gewichtscategorieën zoveel mogelijk in hetzelfde of opvolgende blok zitten

---

## Pagina Layout

```
+------------------------------------------------------------------+
| HEADER                                                            |
| Blokverdeling    6 blokken | 821 wed. | gem 137/blok              |
|                              [Bereken] [Opnieuw] [Naar Zaaloverzicht →] |
+------------------------------------------------------------------+

+------------------+----------------------------------------+----------+
| SLEEPVAK         | BLOKKEN                                | OVERZICHT|
| (niet verdeeld)  |                                        |          |
+------------------+----------------------------------------+----------+
| ▼ Mini's (45)    | BLOK 1         Gewenst: [137] Act: 132 |          |
|   -18 (6)        | [Mini -18][Mini -21][📍A-pup -24]...   | Mini's   |
|   -21 (8)        |                                        |  -18  1  |
|   -24 (10)       +----------------------------------------+  -21  1  |
|                  | BLOK 2         Gewenst: [137] Act: 151 |  -24  2  |
| ▼ A-pupillen     | [B-pup -30][B-pup -34]...              |          |
|   -21 (5)        |                                        | A-pup    |
|   -24 (12)       +----------------------------------------+  -21  -  |
|   ...            | ...                                    |  -24  -  |
+------------------+----------------------------------------+----------+

+------------------------------------------------------------------+
| Varianten: [#1 ±14/7] [#2 ±27/9] [#3] [#4] [#5]  [✕ Annuleer]   |
+------------------------------------------------------------------+
```

---

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

### Bereken
1. Verzamel alle **niet-vastgezette** categorieën (in sleepvak OF al in blokken maar zonder 📍)
2. Bereken restcapaciteit per blok (gewenst - actueel van vastgezette 📍 categorieën)
3. Genereer 5 varianten met verschillende strategieën
4. **Toon variant 1 direct in de blokken**
5. Toon variant-keuze balk onderaan om te bladeren tussen varianten

### Variant Bladeren (#1, #2, etc.)
- Klikken op variant-knop toont die verdeling **direct in de blokken**
- Geen page reload - JavaScript update
- Vastgezette 📍 categorieën blijven altijd staan

### Opnieuw
- **Reset ALLES** - alle categorieën terug naar sleepvak
- Ook vastgezette 📍 categorieën worden gereset
- Schone lei om opnieuw te beginnen

### Naar Zaaloverzicht →
- Sla huidige blokverdeling op in database
- **Wijs matten toe** aan poules (gebalanceerde verdeling)
- Ga naar zaaloverzicht (preview met matverdeling)
- Matten kunnen na overpoelen nog aangepast worden

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

| Status | Weergave | Gedrag bij "Bereken" | Gedrag bij "Opnieuw" |
|--------|----------|---------------------|---------------------|
| In sleepvak | Paars, geen pin | Wordt verdeeld | Blijft in sleepvak |
| In blok, niet vast | Blauw + rode 📌 | Wordt opnieuw verdeeld | Terug naar sleepvak |
| In blok, vastgezet | Groen + groene ● | **Blijft staan** | Terug naar sleepvak |

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
2. **"Naar zaaloverzicht"**: Per categorie doorsturen (knop wordt groen)
3. **Zaaloverzicht**: Witte chip klikken → mat toewijzen + wedstrijdschema genereren
4. **Mat Interface**: Wedstrijden afwerken

---

## Solver Algoritme

### Plaatsing Volgorde
1. **Grote leeftijden eerst** (in deze volgorde):
   - Mini's → A-pupillen → B-pupillen → Heren -15 → Heren -18 → Heren
   - Mini's start ALTIJD in blok 1
   - Elke volgende leeftijd sluit aan waar vorige eindigde

2. **Strikte aansluiting regels** per gewichtscategorie:
   - Zelfde blok (0) = 0 punten
   - Volgend blok (+1) = 10 punten
   - Vorig blok (-1) = 20 punten
   - 2 blokken later (+2) = 30 punten
   - Verder = 50+ punten (slecht)

3. **Kleine leeftijden als opvulling**:
   - Dames -15, Dames -18, Dames
   - Geplaatst in blokken met meeste ruimte

4. **Penalty aflopende leeftijdsklasse**:
   - Als laatste gewicht in lager blok zit dan eerste = +200 punten

### Scoring Formule
```
Verdeling Score = Σ absolute % afwijkingen per blok
Aansluiting Score = Σ punten per overgang (zie boven)
Totaal Score = (slider_X% × Verdeling) + (slider_Y% × Aansluiting)
```
**Lager = beter**

### Variatie Generatie
- 3 seconden rekentijd (10.000-30.000 berekeningen)
- 960.000+ mogelijke combinaties door:
  - 6 aansluiting strategieën
  - 100 random factors
  - 10 sorteer strategieën
  - 8 leeftijd shuffle opties
  - Slider gewicht variatie (±10%)
- Top 5 unieke varianten getoond

### Live Score Update
- Variant knop update direct bij handmatig verslepen
- Zelfde formule als backend berekening
- Slider beïnvloedt weging

### Auto-Apply
- Na Bereken/Herbereken wordt variant #1 automatisch toegepast
- Chips tonen direct de nieuwe posities

---

## Database

### Poules tabel
```
blok_id         - FK naar blokken (null = niet verdeeld)
blok_vast       - boolean (true = handmatig vastgezet met 📍)
```

### Blokken tabel
```
gewenst_wedstrijden - integer nullable (null = auto-berekend)
```
