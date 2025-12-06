# Gebruikershandleiding

## Inhoudsopgave

1. [Overzicht](#overzicht)
2. [Voorbereiding](#voorbereiding)
3. [Poule Indeling](#poule-indeling)
4. [Blok/Mat Verdeling](#blokmat-verdeling)
5. [Toernooidag](#toernooidag)
6. [Weging](#weging)
7. [Mat Interface](#mat-interface)

## Overzicht

Het WestFries Open JudoToernooi Management Systeem ondersteunt het complete proces van een judotoernooi:

1. **Voorbereiding**: Toernooi aanmaken, deelnemers importeren
2. **Indeling**: Poules genereren, verdelen over blokken en matten
3. **Toernooidag**: Weging, wedstrijden bijhouden, uitslagen registreren

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

### Automatische Verdeling

1. Ga naar **Toernooi** > **Blokken** > **Genereer Verdeling**
2. Het systeem:
   - Verdeelt poules gelijkmatig over tijdsblokken
   - Wijst matten toe voor gelijke belasting
   - Houdt dezelfde leeftijdsklasse bij elkaar

### Zaaloverzicht

Het Zaaloverzicht toont per blok en mat:
- Welke poules waar staan
- Aantal wedstrijden per mat
- Status van de weging

## Toernooidag

### Workflow

1. **Weging openen** - Judoka's melden zich
2. **Gewicht registreren** - Via interface of QR scan
3. **Weging sluiten per blok** - Wanneer klaar
4. **Wedstrijdschema's genereren** - Na sluiten weging
5. **Wedstrijden afwerken** - Uitslagen registreren

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
- Rode markering
- Suggestie voor alternatieve poule
- Mogelijkheid tot verplaatsing

### Weging Sluiten

1. Ga naar **Blokken** > **Blok X**
2. Klik **Sluit Weging**
3. Bevestig - dit is onomkeerbaar

Na sluiten:
- Wedstrijdschema's kunnen worden gegenereerd
- Geen weging meer mogelijk voor dit blok

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
