# Gebruikershandleiding

## Inhoudsopgave

1. [Overzicht](#overzicht)
2. [Voorbereiding](#voorbereiding)
3. [Poule Indeling](#poule-indeling)
4. [Blok/Mat Verdeling](#blokmat-verdeling)
5. [Toernooidag](#toernooidag)
6. [Weging](#weging)
7. [Overpoelen](#overpoelen)
8. [Zaaloverzicht & Wedstrijdschema](#zaaloverzicht--wedstrijdschema)
9. [Mat Interface](#mat-interface)
10. [Spreker & Prijsuitreiking](#spreker--prijsuitreiking)

## Overzicht

Het WestFries Open JudoToernooi Management Systeem ondersteunt het complete proces van een judotoernooi in twee fasen:

### Fase 1: Voorbereiding (weken/maanden voor toernooi)
1. Toernooi aanmaken
2. Deelnemers importeren
3. Poules genereren
4. Blokkenverdeling
5. **Resultaat:** Weeglijst, weegkaarten, preview zaaloverzicht

### Fase 2: Toernooidag
1. Weging (gewicht registreren per judoka)
2. Einde weegtijd per blok
3. Overpoelen (te zware judoka's verplaatsen)
4. Poules in orde maken
5. Categorie naar zaaloverzicht sturen
6. Wedstrijdschema genereren
7. Wedstrijden op mat
8. Poule klaar → spreker voor prijsuitreiking
9. Eindoverzicht prijsuitreikingen

**Belangrijk:** Tijdens de voorbereiding bestaat er geen "aanwezig/afwezig" status. Alle aangemelde judoka's worden meegeteld. De aanwezigheidsstatus wordt pas relevant op de toernooidag.

## Voorbereiding

### Nieuw Toernooi Aanmaken

1. Ga naar **Dashboard** > **Nieuw Toernooi**
2. Vul de toernooi gegevens in:
   - Naam (bijv. "6e WestFries Open")
   - Datum
   - Aantal matten (standaard: 7)
   - Aantal tijdsblokken (standaard: 6)
3. Klik **Aanmaken**

### Deelnemers Importeren

1. Ga naar **Toernooi** > **Judoka's** > **Importeren**
2. Upload een CSV of Excel bestand met de volgende kolommen:
   - Naam (verplicht)
   - Geboortejaar (verplicht)
   - Geslacht (M/V)
   - Band (wit, geel, oranje, etc.)
   - Club
   - Gewicht (optioneel)
3. Controleer de preview en klik **Importeren**

Het systeem:
- Berekent automatisch de leeftijdsklasse
- Bepaalt de gewichtsklasse op basis van gewicht
- Genereert een unieke judoka-code
- Maakt een QR-code voor snelle identificatie

## Poule Indeling

### Automatische Poule Generatie

1. Ga naar **Toernooi** > **Poules** > **Genereer Poule-indeling**
2. Het systeem verdeelt judoka's automatisch op basis van:
   - Leeftijdsklasse
   - Gewichtsklasse
   - Geslacht (vanaf -15 jaar)
   - Band/niveau

### Poule Regels

- **Optimaal**: 5 judoka's per poule (10 wedstrijden)
- **Minimum**: 3 judoka's (6 wedstrijden - dubbele ronde)
- **Maximum**: 6 judoka's (15 wedstrijden)

### Handmatige Aanpassingen

1. Open een poule
2. Klik **Verplaats Judoka**
3. Selecteer de judoka en de doelpoule
4. Bevestig de verplaatsing

## Blok/Mat Verdeling

### Doel van de Blokverdeling

De blokkenpagina heeft twee hoofddoelen:

1. **Gelijkmatige verdeling** - Elk tijdsblok krijgt ongeveer evenveel wedstrijden
2. **Aansluiting gewichtscategorieën** - Opeenvolgende gewichtsklassen (-27, -30, -34, -38) in dezelfde of aansluitende blokken plaatsen

**Waarom aansluiting belangrijk is:**
Judoka's die te zwaar wegen worden overgepouled naar een zwaardere gewichtsklasse. Als die klasse in hetzelfde of volgend blok zit, hoeven ze niet lang te wachten.

**Aansluiting regels:**
| Overgang | Beoordeling |
|----------|-------------|
| Zelfde blok | Perfect |
| +1 blok (volgend) | Perfect |
| +2 blokken | Acceptabel |
| -1 blok (terug) | Slecht - blok is al geweest! |
| +3+ blokken | Slecht - te lang wachten |

### Interface Layout

De blokkenpagina bestaat uit twee delen:

**Links - Blokken:**
- Sleepvak met niet-verdeelde categorieën
- Blok 1-6 met toegewezen categorieën (chips)
- Per blok: actueel aantal wedstrijden en afwijking van gewenst

**Rechts - Overzicht Panel:**
- Per leeftijdsklasse alle gewichtscategorieën
- Bloknummer per categorie (voor beoordeling aansluiting)
- Kleurcodes: groen=vast, blauw=berekend, rood=niet verdeeld

### Varianten Berekenen

1. Klik **Bereken** - systeem genereert 5 varianten
2. Varianten verschijnen onder de blokken
3. Elke variant toont: `#1 ±14 / 3b`
   - ±14 = maximale afwijking van gemiddelde
   - 3b = aantal "breaks" (slechte overgangen: +2, -1, of +3+ blokken)
4. Klik op variant om preview te zien
5. **Overzicht panel update direct** - check aansluiting gewichten
6. Klik **Zet op Mat** om gekozen variant toe te passen

**Sliders voor prioriteit:**
- **Verdeling** (0-100):
  - 100% = alle blokken exact gelijk aantal wedstrijden
  - 0% = max ±25% afwijking van gemiddelde toegestaan
- **Aansluiting** (0-100):
  - 100% = alleen zelfde blok of +1 blok toegestaan
  - 0% = aansluiting wordt genegeerd
- Pas sliders aan en klik opnieuw op **Bereken** voor nieuwe varianten

### Handmatig Aanpassen

- **Drag & drop** - Sleep categorie naar ander blok
- **Vastzetten** - Klik 📌 om categorie vast te zetten (wordt niet door berekening verplaatst)
- Bij elke wijziging updaten:
  - Blok totalen en afwijkingen
  - Overzicht panel bloktoewijzingen

### Zaaloverzicht

Het Zaaloverzicht toont per blok en mat:
- Welke poules waar staan
- Aantal wedstrijden per mat
- Status van de weging

## Toernooidag

De toernooidag begint waar de voorbereiding eindigt. Nu worden judoka's daadwerkelijk gewogen en kunnen afwijkingen optreden.

### Dagflow per Blok

1. **Weging** - Judoka's wegen zich in
2. **Einde weegtijd** - Blok sluiten voor weging
3. **Overpoelen** - Te zware/lichte judoka's verplaatsen
4. **Poules controleren** - Afwezigen afhandelen
5. **Naar zaaloverzicht** - Categorie klaarzetten
6. **Wedstrijdschema genereren** - Per categorie
7. **Naar mat** - Wedstrijden kunnen beginnen

## Weging

### Weging Interface

1. Ga naar **Weging** > **Interface**
2. **Zoeken**: Typ naam of scan QR-code
3. **Registreren**: Vul gewicht in en bevestig

### Gewichtscontrole

Het systeem controleert automatisch:
- **Ondergrens**: Vorige gewichtsklasse + tolerantie
- **Bovengrens**: Eigen gewichtsklasse + tolerantie

Bij afwijking:
- Rode markering (doorgestreept gewicht)
- Judoka moet overgepouled worden naar andere gewichtsklasse

### Weging Sluiten

1. Ga naar **Blokken** > **Blok X**
2. Klik **Sluit Weging**
3. Bevestig

Na sluiten:
- Geen weging meer mogelijk voor dit blok
- Overpoelen kan beginnen

## Overpoelen

Na sluiten weegtijd moeten judoka's die buiten hun gewichtsklasse vallen worden verplaatst.

### Workflow

1. Ga naar **Wedstrijddag** pagina
2. Je ziet judoka's die buiten gewichtsklasse vallen (doorgestreept)
3. Sleep judoka naar **wachtruimte**
4. Of: markeer als afwezig
5. Statistieken (aantal judoka's, wedstrijden) updaten automatisch

### Jurytafel: Verdelen naar Poules

De jurytafel/organisator verdeelt judoka's uit de wachtruimte:

1. Bekijk judoka's in wachtruimte
2. Sleep naar juiste poule in zwaardere gewichtsklasse
3. Let op: max 6 judoka's per poule
4. Bij veel afwezigen: overweeg poules samenvoegen

**Tip:** Check de aansluiting - zwaardere klasse moet in zelfde/volgend blok zitten

## Zaaloverzicht & Wedstrijdschema

### Categorie naar Zaaloverzicht

Wanneer een gewichtscategorie klaar is (alle judoka's gewogen, overgepouled):

1. Klik **Naar Zaaloverzicht** bij de categorie
2. Controleer of poules goed over matten verdeeld zijn
3. Pas eventueel aan (sleep poule naar andere mat)

### Wedstrijdschema Genereren

Per categorie op zaaloverzicht:

1. Klik **Genereer Wedstrijdschema**
2. Systeem maakt wedstrijden aan per poule
3. Optimale volgorde: elke judoka krijgt rust tussen wedstrijden
4. Categorie is nu klaar voor de mat

## Mat Interface

### Wedstrijden Beheren

1. Ga naar **Matten** > **Interface**
2. Selecteer blok en mat
3. Per poule zie je:
   - Lijst van judoka's met club
   - Wedstrijdschema in optimale volgorde
   - Status (gespeeld/niet gespeeld)

### Uitslag Registreren

1. Klik op een wedstrijd
2. Selecteer winnaar (wit of blauw)
3. Kies uitslag type:
   - **Ippon** - Directe overwinning
   - **Waza-ari** - Halve overwinning
   - **Beslissing** - Op punten
   - **Gelijk** - Geen winnaar
4. Bevestig

### Stand Bijhouden

De poulestand wordt automatisch bijgewerkt:
- 10 punten per gewonnen wedstrijd
- 5 punten bij gelijkspel
- Rangschikking op punten, dan op gewonnen

### Poule Afronden

Wanneer alle wedstrijden in een poule gespeeld zijn:
1. Klik **Poule Klaar**
2. Poule wordt naar spreker gestuurd voor prijsuitreiking

## Spreker & Prijsuitreiking

### Spreker Interface

De spreker ziet poules die klaar zijn voor prijsuitreiking:

1. Ga naar **Spreker** > **Interface**
2. Wachtrij met afgeronde poules
3. Per poule:
   - Eindstand met 1e, 2e, 3e plaats
   - Judoka namen en clubs
   - Categorie informatie

### Prijsuitreiking Workflow

1. Roep judoka's op (1e, 2e, 3e)
2. Reik medailles uit
3. Markeer als **Uitgereikt**
4. Volgende poule verschijnt

### Eindoverzicht

Voor de organisator:
- Overzicht alle prijsuitreikingen
- Status per poule (uitgereikt/wachtend)
- Totaal aantal medailles
