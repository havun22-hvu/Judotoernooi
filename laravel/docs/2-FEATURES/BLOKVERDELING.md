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
- **Wijs matten toe** aan poules (gebalanceerde verdeling)
- **Weegkaarten worden nu beschikbaar** (QR-code + bloknaam + weging tijden)
  - Vereist: eerst "Valideer judoka's" uitgevoerd (QR-codes aangemaakt)
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
2. **"Naar zaaloverzicht"**: Per categorie doorsturen (knop wordt groen)
3. **Zaaloverzicht**: Witte chip klikken → mat toewijzen + wedstrijdschema genereren
4. **Mat Interface**: Wedstrijden afwerken

---

## Solver Algoritme

### Plaatsing Volgorde
1. **Grote leeftijden eerst** (volgorde uit preset config):
   - Categorieen worden geplaatst in volgorde van de preset
   - Eerste categorie start ALTIJD in blok 1
   - Elke volgende leeftijd sluit aan waar vorige eindigde

2. **Strikte aansluiting regels** per gewichtscategorie:
   - Zelfde blok (0) = 0 punten
   - Volgend blok (+1) = 10 punten
   - Vorig blok (-1) = 20 punten
   - 2 blokken later (+2) = 30 punten
   - Verder = 50+ punten (slecht)

3. **Kleinere categorieen als opvulling**:
   - Categorieen met minder judoka's (bijv. dames, specifieke bandgroepen)
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

## Variabele Categorieën (Dynamische Blokverdeling)

Bij toernooien met **variabele categorieën** (bijv. "Jeugd t/m 14j" met Δkg=3 en Δlft=1) werkt de blokverdeling anders dan bij vaste categorieën.

### Het Probleem

Bij variabele indeling krijg je poules zoals:
- 9-10j, 22-25kg
- 10-11j, 25-28kg
- 11-12j, 28-31kg
- 12-13j, 32-35kg

De leeftijdsranges **overlappen**: een 11-jarige kan in zowel "10-11j" als "11-12j" poules zitten (afhankelijk van gewicht).

Je kunt dus NIET clean zeggen "alle 11-jarigen in blok 2" - want sommige zitten in poule "10-11j" en andere in "11-12j".

### De Oplossing: Knip op Gewicht

De verdeling werkt als volgt:

1. **Sorteer alle poules** op: min_leeftijd → min_gewicht
2. **Vul blok** totdat max wedstrijden bereikt is
3. **Knip waar je bent** - dit kan midden in een leeftijdsgroep vallen
4. **Genereer leesbare labels** per blok

**Voorbeeld:** Max 133 wedstrijden per blok

```
Blok 2 (132 wedstrijden):
├── 9-10j alle gewichten      (30 wed)
├── 10-11j alle gewichten     (45 wed)
├── 11-12j t/m 28kg           (57 wed)  ← knip hier!

Blok 3 (128 wedstrijden):
├── 11-12j vanaf 28kg         (35 wed)  ← zwaardere 11-12j
├── 12-13j alle gewichten     (48 wed)
├── 13-14j alle gewichten     (45 wed)
```

### Communicatie naar Ouders

De bloklabels worden automatisch gegenereerd:

```
Blok 2: "Jeugd 9-12j t/m 28kg"    - weging 10:00-10:30
Blok 3: "Jeugd 11-14j vanaf 28kg" - weging 11:00-11:30
```

**Let op de overlap in leeftijd!** Dit is correct:
- 11-12j **licht** (t/m 28kg) → Blok 2
- 11-12j **zwaar** (vanaf 28kg) → Blok 3

### Voordeel: Overpoulen

Als een 11-jarige te zwaar is voor zijn poule en overpouled wordt naar een zwaardere groep, zit hij automatisch al in het juiste blok (de zwaardere groep zit in het latere blok).

### Algoritme Details

```
voor elke poule in gesorteerde volgorde:
    als (actueel + poule.wedstrijden) > max_per_blok:
        start nieuw blok
    voeg poule toe aan huidig blok
    actueel += poule.wedstrijden

genereer label per blok:
    min_leeftijd = MIN(poules.min_leeftijd)
    max_leeftijd = MAX(poules.max_leeftijd)
    min_gewicht = MIN(poules.min_gewicht)
    max_gewicht = MAX(poules.max_gewicht)

    als eerste blok met deze leeftijdsrange:
        label = "{min_lft}-{max_lft}j t/m {max_gewicht}kg"
    anders:
        label = "{min_lft}-{max_lft}j vanaf {min_gewicht}kg"
```

### UI Aanpassingen

Bij variabele categorieën:
- Geen drag & drop per categorie (categorieën bestaan niet)
- Slider voor "max wedstrijden per blok"
- Preview van waar de knip valt
- Automatisch gegenereerde bloklabels

### Chip Weergave (variabele categorieën)

Format: `{label 1 letter} {min_lft}j {min_kg}kg ({wedstrijden}w)`

Voorbeeld: `M 9j 22kg (10w)`

- **Label**: Eerste letter van leeftijdsklasse prefix (M, V, B voor Beginners, etc.)
- **Min leeftijd**: Jongste judoka in de poule
- **Min gewicht**: Lichtste judoka in de poule
- **Wedstrijden**: Aantal wedstrijden in de poule

Dit maakt de sortering visueel controleerbaar: van jong naar oud, van licht naar zwaar.

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
blok_label          - string nullable (auto-gegenereerd voor variabele cat.)
```
