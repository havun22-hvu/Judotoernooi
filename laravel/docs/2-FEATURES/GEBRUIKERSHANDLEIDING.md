# Gebruikershandleiding

## Inhoudsopgave

1. [Overzicht](#overzicht)
2. [Voorbereiding](#voorbereiding)
3. [Poule Indeling](#poule-indeling)
4. [Blok/Mat Verdeling](#blokmat-verdeling)
5. [Toernooidag](#toernooidag)
6. [Weging](#weging)
7. [Overpoelen (Wedstrijddag Poules)](#overpoelen-wedstrijddag-poules)
8. [Zaaloverzicht & Activatie](#zaaloverzicht--activatie)
9. [Mat Interface](#mat-interface)
10. [Spreker & Prijsuitreiking](#spreker--prijsuitreiking)
11. [Statistieken & Resultaten](#statistieken--resultaten)

## Overzicht

Het WestFries Open JudoToernooi Management Systeem ondersteunt het complete proces van een judotoernooi in twee fasen:

### Fase 1: Voorbereiding (weken/maanden voor toernooi)
1. Toernooi aanmaken
2. Inschrijving (tot sluitingsdatum):
   - **Handmatige import** - Organisator importeert CSV/Excel
   - **Coach portal** - Coaches voegen zelf judoka's toe
3. **Einde inschrijving** → "Valideer judoka's" → QR-codes aangemaakt (definitief)
4. Poules genereren
5. Blokverdeling → poules krijgen blok toegewezen
6. **"Verdeel over matten"** → automatische verdeling (organisator kan nog schuiven)
7. **Zaaloverzicht** → controleer verdeling, pas aan indien nodig
8. **"Maak weegkaarten"** → voorbereiding geseald, weegkaarten tonen blok + mat
9. **Resultaat:** Weeglijst, weegkaarten (blok+mat), coachkaarten klaar

### Fase 2: Toernooidag
1. Weging (gewicht registreren per judoka)
2. Einde weegtijd per blok
3. **Wedstrijddag Poules** → overpoelen (te zware/afwezige judoka's)
4. Per categorie: **"Naar zaaloverzicht"** klikken (knop wordt groen)
5. **Zaaloverzicht** → witte chip klikken → wedstrijdschema genereren (chip wordt groen)
6. Wedstrijden op mat
7. Poule klaar → spreker voor prijsuitreiking

**Belangrijk:**
- Matten worden automatisch verdeeld, maar organisator kan schuiven vóór "Maak weegkaarten"
- Weegkaarten tonen pas mat nummer NA "Maak weegkaarten" (voorbereiding geseald)
- Wedstrijdschema's worden PAS gegenereerd bij activatie op toernooidag (chip klikken)

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

**Let op:**
- QR-codes worden pas aangemaakt bij "Valideer judoka's" (na einde inschrijving)
- Weegkaarten tonen blokinfo NA "Verdeel over matten"
- Weegkaarten tonen mat nummer pas NA "Maak weegkaarten" (voorbereiding geseald)

## Valideer Judoka's (Einde Inschrijving)

Na de sluitingsdatum van de inschrijving:

1. Ga naar **Toernooi** > **Judoka's** > **Valideer**
2. Systeem controleert alle judoka's op:
   - Volledige gegevens (naam, geboortejaar, geslacht, band, gewicht)
   - Correcte gewichtsklasse
3. **QR-codes worden nu aangemaakt** (definitief)
   - Gebaseerd op definitieve naam, band, gewicht
   - `judoka_code` = LLGGBGVV (Leeftijd-Gewicht-Band-Geslacht-Volgnummer)
4. Na validatie kunnen gegevens niet meer door coaches gewijzigd worden

**Belangrijk:** Tot dit moment kunnen coaches nog wijzigingen doorvoeren. Na validatie zijn de gegevens definitief.

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

**Balans slider:**
```
Verdeling blokken |--------------------| Aansluiting gewichten
```
- **Links (0)**: Focus op gelijke verdeling wedstrijden over blokken
- **Rechts (100)**: Focus op aansluiting gewichtscategorieën (voor overpoelen)
- **Midden (50)**: Gebalanceerd
- Pas slider aan en klik opnieuw op **Bereken** voor nieuwe varianten

### Handmatig Aanpassen

- **Drag & drop** - Sleep categorie naar ander blok
- **Vastzetten** - Klik 📌 om categorie vast te zetten (wordt niet door berekening verplaatst)
- Bij elke wijziging updaten:
  - Blok totalen en afwijkingen
  - Overzicht panel bloktoewijzingen

### Zaaloverzicht & Mat Toewijzing

Na "Verdeel over matten" gaat de organisator naar het **Zaaloverzicht**:

1. **Automatische verdeling** - Poules zijn automatisch over matten verdeeld
2. **Aanpassen** - Sleep poules naar andere mat indien gewenst
3. **Balanceren** - Check dat elke mat ongeveer evenveel wedstrijden heeft
4. **Controleren** - Bekijk de verdeling voor elk blok

**Let op:** Na "Verdeel over matten" zijn poules al toegewezen aan matten. De organisator kan nog schuiven vóór "Maak weegkaarten".

### Voorbereiding Afronden ("Maak weegkaarten")

Wanneer alle poules op de juiste matten staan:

1. Klik **"Maak weegkaarten"** knop in het Zaaloverzicht
2. Systeem genereert weegkaarten en coachkaarten met definitieve info
3. **Voorbereiding is nu "geseald"** - geen wijzigingen meer mogelijk
4. Knop verdwijnt na uitvoering

**Weegkaart toont (na "Maak weegkaarten"):**
- Naam en club
- QR-code (voor scanner bij weging)
- **Blok nummer** + weegtijden
- **Mat nummer** (definitief!)
- Gewichtsklasse, geboortejaar, band, geslacht

**Vereisten voor weegkaarten:**
1. "Valideer judoka's" uitgevoerd → QR-codes aangemaakt
2. Blokverdeling gedaan → blokken toegewezen
3. "Verdeel over matten" gedaan → matten toegewezen (automatisch, kan aangepast)
4. "Maak weegkaarten" geklikt → voorbereiding geseald, mat info op weegkaart

### Zaaloverzicht

Het Zaaloverzicht toont per blok en mat:
- Welke poules waar staan (automatisch verdeeld, kan aangepast worden)
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

### Weging Interface (Admin/Hoofdjury)

1. Ga naar **Weging** > **Weging Interface**
2. Zie live overzicht van alle judoka's met weegstatus
3. Zoek op naam of club
4. Filter per blok of status (gewogen/niet gewogen)
5. Tabel toont: Naam, Club, Leeftijd, Gewicht, Blok, Gewogen, Tijd

### Weging Scanner (Vrijwilliger PWA)

1. Open de toegangs-URL + PIN (via Instellingen → Organisatie)
2. **Zoeken**: Typ naam of scan QR-code
3. **Registreren**: Vul gewicht in via numpad en bevestig

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

## Overpoelen (Wedstrijddag Poules)

Na sluiten weegtijd moeten judoka's die buiten hun gewichtsklasse vallen worden verplaatst.

### Pagina: Wedstrijddag Poules

1. Ga naar **Wedstrijddag Poules** pagina
2. Per blok zie je alle categorieën met hun poules
3. **Doorgestreepte judoka's** = afwezig OF buiten gewichtsklasse
4. **Wachtruimte** (rechts) = judoka's die overgepouled moeten worden

### Workflow Overpoelen

1. Bekijk doorgestreepte judoka's in poules
2. Klik **−** knop om uit poule te verwijderen (afwezigen)
3. Sleep judoka's uit wachtruimte naar juiste poule
4. Let op: max 6 judoka's per poule
5. Statistieken updaten automatisch

### Naar Zaaloverzicht Sturen

Wanneer een categorie klaar is (overgepouled):

1. Klik **"Naar zaaloverzicht"** knop bij de categorie
2. Knop wordt **groen** met "✓ Doorgestuurd"
3. In zaaloverzicht verschijnt de categorie als **witte chip**

**Knop kleuren:**
| Kleur | Status |
|-------|--------|
| Blauw | Nog niet doorgestuurd |
| Groen "✓ Doorgestuurd" | Klaar voor zaaloverzicht |

## Zaaloverzicht & Activatie

### Chip Kleuren in Zaaloverzicht

Per blok zie je chips voor elke categorie:

| Chip kleur | Betekenis | Actie |
|------------|-----------|-------|
| **Grijs** | Niet doorgestuurd | Ga naar Wedstrijddag Poules |
| **Wit** | Doorgestuurd, klaar voor activatie | Klik om te activeren |
| **Groen** ✓ | Geactiveerd (wedstrijden op mat) | Klik voor mat interface |

### Categorie Activeren

Klik op een **witte chip** om te activeren:

1. Wedstrijdschema wordt gegenereerd per poule (matten zijn al toegewezen in voorbereiding)
2. Alleen actieve judoka's komen in schema (niet doorgestreepte!)
3. Chip wordt **groen** met ✓
4. Categorie is nu klaar voor de mat

**Let op:** Doorgestreepte judoka's (afwezig/verkeerd gewicht) worden NIET meegenomen in het wedstrijdschema!

### Poules Verplaatsen

Na activatie kun je poules nog verplaatsen tussen matten:
1. Sleep poule naar andere mat
2. Wedstrijden tellen worden automatisch bijgewerkt

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

## Statistieken & Resultaten

Na afloop van het toernooi zijn resultaten beschikbaar voor verschillende doelgroepen:

### Organisator
- Totaal aantal deelnemers, wedstrijden, poules
- **Gemiddelde wedstrijdduur per leeftijdsklasse en bandkleur**
  - Jongere/lagere band: vaak volle 2 min (kunnen nog geen ippon maken)
  - H-15/D-15: 3 min speeltijd maar vaak sneller klaar (ippon binnen 10 sec)
  - Belangrijk voor planning wedstrijden per blok/mat
- Overzicht medailles per club
- Export mogelijkheden

### Judocoaches (Coach Portal)
- Resultaten eigen judoka's
- Uitslagen en plaatsingen
- Wedstrijdhistorie

### Openbare Website
- Live uitslagen tijdens toernooi
- Eindstanden per categorie
- Medaillespiegel per club
