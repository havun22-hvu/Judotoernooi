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

Het JudoToernooi Management Systeem ondersteunt het complete proces van een judotoernooi in twee fasen:

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
8. **Resultaat:** Weeglijst, weegkaarten (blok+mat), coachkaarten klaar (dynamisch, altijd actueel)

### Fase 2: Toernooidag
1. Weging (gewicht registreren per judoka)
2. Einde weegtijd per blok
3. **Wedstrijddag Poules** → overpoelen (te zware/afwezige judoka's)
4. Per poule: **"→"** knop klikken (knop wordt groen ✓)
5. **Zaaloverzicht** → witte chip klikken → wedstrijdschema genereren (chip wordt groen)
6. Wedstrijden op mat
7. Poule klaar → spreker voor prijsuitreiking

**Belangrijk:**
- Matten worden automatisch verdeeld, maar organisator kan altijd schuiven in Zaaloverzicht
- Weegkaarten zijn dynamisch en tonen altijd actuele blok + mat info
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

**Stap 1: Bestand uploaden**
1. Ga naar **Toernooi** > **Judoka's** > **Importeren**
2. Upload een CSV of Excel bestand (ondersteund: .csv, .xlsx, .xls)

**Stap 2: Kolom toewijzing (drag-and-drop)**

Na upload verschijnt een preview scherm met twee panelen:

| Links: Database velden | Rechts: CSV kolommen uit bestand |
|------------------------|----------------------------------|
| Naam * | **Naam** → Saar Peters, Noah... |
| Club | **Club** → Sportclub X, Judo Y... |
| Geboortejaar * | **Geboortejaar** → 2015, 2016... |
| Geslacht (M/V) * | **Geslacht** → M, V, M... |
| Gewicht (kg) | **Gewicht** → 32.5, 28.0... |
| Band | **Band** → Wit, Geel... |
| Gewichtsklasse | |

**Hoe het werkt:**
- Het systeem detecteert automatisch kolommen op basis van de kolomnaam
- Elke CSV kolom toont **voorbeelddata** zodat je kunt controleren of de inhoud klopt
- **Sleep** een CSV kolom naar het juiste database veld als de automatische detectie fout is
- Klik **✕** om een koppeling te verwijderen en opnieuw te slepen
- De preview tabel onderaan toont welke kolommen gekoppeld zijn (groen gemarkeerd)

**Stap 3: Importeren**
- Controleer of alle verplichte velden (*) gekoppeld zijn
- Klik **Importeren** om de data te verwerken

**Het systeem:**
- Berekent automatisch de leeftijdsklasse (indien geboortejaar bekend)
- Bepaalt de gewichtsklasse op basis van gewicht
- Als alleen gewichtsklasse is opgegeven (bv. "-34"), wordt gewicht afgeleid (34 kg)
- **Onvolledige judoka's** (ontbrekend geboortejaar/geslacht) worden geïmporteerd en gemarkeerd
- Filter op onvolledige judoka's via de "Onvolledig" knop in de deelnemerslijst
- Import melding toont aantal geïmporteerd + aantal duplicaten bijgewerkt

**Let op:**
- QR-codes worden pas aangemaakt bij "Valideer judoka's" (na einde inschrijving)
- Weegkaarten zijn dynamisch en tonen altijd actuele blok + mat info (zodra toegewezen)

### Judoka Verwijderen

Individuele judoka's kunnen verwijderd worden uit het overzicht:

1. Ga naar **Toernooi** > **Judoka's**
2. Klik op de **×** knop rechts in de rij
3. Bevestig de verwijdering in de popup

**Let op:**
- Verwijderen is definitief en kan niet ongedaan worden gemaakt
- Verwijder geen judoka's die al in poules zijn ingedeeld (verwijder ze eerst uit de poule)

### Judoka Codes

Elke judoka krijgt een unieke code (bv. `U1234M01`) voor poule-indeling. Deze code wordt berekend op basis van:
- Leeftijdsklasse
- Gewichtsklasse
- Band
- Geslacht

**Wanneer worden codes (her)berekend?**
- Na elke **import** - automatisch
- Bij klikken op **"Valideren"** knop
- Bij wijziging **prioriteit instellingen** (drag & drop volgorde) - automatisch bij opslaan

### Budoclub vs Wedstrijdcoach

Het systeem maakt onderscheid tussen twee concepten:

| Concept | Doel | Wanneer aangemaakt |
|---------|------|-------------------|
| **Budoclub** | Inschrijven judoka's via portal | Bij toevoegen club door organisator |
| **Wedstrijdcoach** | Begeleiding judoka's op wedstrijddag | Bij aanmelden 1e judoka |

**Budoclub:**
- Krijgt portal URL + PIN voor inschrijving
- Kan judoka's aanmelden/wijzigen tot deadline
- Elke bekende club krijgt uitnodiging

**Wedstrijdcoach:**
- Fysieke begeleider op de wedstrijddag
- Krijgt coachkaart (toegang dojo)
- Minimaal 1 per club met judoka's
- Definitief aantal berekend bij "Einde Voorbereiding"

### Inschrijfflow

**Workflow voor een toernooi:**

1. **Organisator maakt toernooi aan** met inschrijfdeadline
2. **Organisator voegt budoclubs toe** (via Clubs pagina)
   - Per club wordt automatisch een portal URL + PIN aangemaakt
3. **Organisator deelt URL + PIN** met elke budoclub (email, WhatsApp, etc.)
4. **Budoclubs schrijven judoka's in** via hun portal (tot deadline)
   - Bij 1e judoka: automatisch 1 wedstrijdcoach aangemaakt
5. **Na deadline**: Organisator valideert, poules en blokken worden gemaakt
6. **Einde Voorbereiding**: Definitief aantal coachkaarten berekend per club

### Budoclubs Beheren

Ga naar **Toernooi** > **Clubs** om budoclubs te beheren:

**Club toevoegen:**
1. Vul clubnaam (+ optioneel plaats, email, telefoon, website) in
2. Klik **Toevoegen**
3. Automatisch wordt aangemaakt:
   - Portal URL (bijv. `/school/ABC123`)
   - PIN code (5 cijfers)

**URL en PIN bekijken/delen:**

Per club toont de tabel de portal toegang:
- **URL** → kopieer knop voor portal link
- **PIN** → kopieer knop voor pincode
- Deel via email, WhatsApp, SMS of mondeling

**Uitnodiging per email:**
- **Alle Uitnodigen** - Stuurt email naar alle clubs met email adres
- **Email** knop per club - Stuurt individuele email

### Coachkaarten (Wedstrijdcoaches)

Wedstrijdcoaches krijgen toegangskaarten voor de dojo. Het aantal kaarten is gebaseerd op het **grootste blok** van de club (niet het totaal aantal judoka's).

**Waarom per blok?**
Een coach hoeft alleen aanwezig te zijn wanneer zijn judoka's wedstrijden hebben. Als een club 15 judoka's heeft verdeeld over 3 blokken (8, 4, 3), dan zijn er maximaal 8 judoka's tegelijk actief.

**Berekening aantal coachkaarten:**

De instelling `judokas_per_coach` (default: 5) bepaalt hoeveel kaarten een club krijgt:

| Max judoka's in één blok | Kaarten (bij 5 per coach) |
|--------------------------|---------------------------|
| 1-5 | 1 kaart |
| 6-10 | 2 kaarten |
| 11-15 | 3 kaarten |
| 16-20 | 4 kaarten |

**Formule:** `ceil(max_judokas_per_blok / judokas_per_coach)`

**Voorbeeld:**
- Club A: 15 judoka's totaal
- Blok 1: 8 judoka's, Blok 2: 4 judoka's, Blok 3: 3 judoka's
- Grootste blok = 8 → `ceil(8/5) = 2` coachkaarten

**Let op:** Als blokken nog niet zijn toegewezen, wordt het totaal aantal judoka's gebruikt als fallback.

**Coachkaart activatie:**
1. Coach scant QR-code of opent link
2. Vult naam in en maakt pasfoto
3. Kaart wordt gekoppeld aan dit device (device binding)
4. QR-code is alleen zichtbaar op het geactiveerde device

**Instelling wijzigen:**
- Ga naar **Toernooi** > **Bewerken** > **Coach instellingen**
- Pas `judokas_per_coach` aan (bijv. 10 voor grotere groepen)

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

### Gewichtsklassen Instellingen

Bij **Toernooi Bewerken** > **Gewichtsklassen** kun je kiezen:

**Met vaste gewichtsklassen:**
- Gebruik JBN 2025 of JBN 2026 preset
- Of eigen gewichtsklassen per categorie
- Geslacht instelbaar per categorie (Gemengd/M/V)

**Zonder vaste gewichtsklassen (dynamisch):**
- Drag & drop sorteer prioriteiten: Leeftijd, Gewicht, Band
- Klik (i) icoon voor uitleg over sorteer volgorde
- Stel max kg verschil in per categorie
- Stel max leeftijd verschil in per categorie

**Presets opslaan:** Sla je configuratie op als eigen preset voor later gebruik.

**Sortering bij laden eigen preset:** Categorieën worden automatisch gesorteerd:
1. Leeftijd (jong → oud)
2. Gewicht (licht → zwaar)
3. Band (laag → hoog)

Resultaat: bovenaan de lichtste mini's met witte band, onderaan de zwaarste senioren.

### Hoe Werkt de Poule Indeling?

Het systeem verdeelt judoka's in 4 stappen:

**Stap 1: CATEGORISEREN (welke groep?)**
- Per judoka wordt gekeken welke categorie past
- Categorieen worden doorlopen van jong → oud
- Eerste match waar judoka aan ALLE criteria voldoet = zijn categorie
- **Harde criteria:** max_leeftijd, geslacht, band_filter, gewichtsklasse

**Stap 2: GROEPEREN**
- Alle judoka's in dezelfde categorie = 1 groep
- Dit zijn de kandidaten voor poules binnen deze categorie

**Stap 3: SORTEREN (binnen de groep)**
- Pas NADAT judoka in categorie is geplaatst
- Sorteer volgens de ingestelde prioriteit (leeftijd/gewicht/band)
- Bepaalt alleen de volgorde, niet de groepsindeling

**Stap 4: POULES MAKEN**
- Gesorteerde groep verdelen in poules
- Voorkeur: [5, 4, 6, 3] (standaard)
- Voorbeeld: 20 judoka's → 4 poules van 5

**Belangrijk onderscheid:**
- **Categoriseren** = welke groep (HARD, alle criteria moeten matchen)
- **Sorteren** = welke volgorde binnen de groep (ZACHT, alleen volgorde)

De harde limieten (leeftijd/gewicht/geslacht/band) worden NOOIT overschreden.

### Automatische Poule Generatie

1. Ga naar **Toernooi** > **Poules** > **Genereer Poule-indeling**
2. Het systeem verdeelt judoka's automatisch op basis van:
   - Leeftijdsgroep (max 2 jaar verschil - veiligheid)
   - Gewicht (vaste klassen of max kg verschil)
   - Geslacht (per categorie instelbaar)
   - Band/niveau (sortering binnen groep)

### Poule Titels

Poule titels worden automatisch samengesteld uit:
- **Categorie naam** (uit Instellingen → Categorieën → Naam veld)
- Geslacht (M/V, alleen bij niet-gemengde categorieën)
- Leeftijd range (berekend uit judoka's)
- Gewicht range (berekend uit judoka's)

**Voorbeeld:** "Jeugd 9-10j 30-33kg"

**Let op:** Wijzig de categorie naam in Instellingen VOORDAT je poules genereert.

### Poule Regels

- **Optimaal**: 5 judoka's per poule (10 wedstrijden)
- **Minimum**: 3 judoka's (6 wedstrijden - dubbele ronde)
- **Maximum**: 6 judoka's (15 wedstrijden)

### Handmatige Aanpassingen

**Drag & drop**: Sleep judoka's direct tussen poules op de Poules pagina.

**Bij verplaatsen worden automatisch bijgewerkt:**
- Aantal judoka's en wedstrijden per poule
- Totaal statistieken bovenaan de pagina (wedstrijden, judoka's, problemen)
- Min-max leeftijd per poule
- Min-max gewicht per poule
- Poule titel (bij variabele categorie)

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

**Let op:** Poules kunnen altijd verplaatst worden naar andere matten via drag & drop in Zaaloverzicht.

### Weegkaarten

Weegkaarten zijn **dynamisch** en tonen altijd de actuele stand:
- Naam en club
- QR-code (voor scanner bij weging)
- **Blok nummer** + weegtijden (zodra toegewezen)
- **Mat nummer** (zodra toegewezen)
- Gewichtsklasse, geboortejaar, band, geslacht

**Vereisten voor weegkaarten:**
1. "Valideer judoka's" uitgevoerd → QR-codes aangemaakt
2. Blokverdeling gedaan → blokken toegewezen
3. "Verdeel over matten" gedaan → matten toegewezen

**Belangrijk:** Weegkaarten worden NIET als bestanden opgeslagen. Ze worden live gegenereerd bij openen. Wijzigingen in blok/mat zijn direct zichtbaar.

### Zaaloverzicht

Het Zaaloverzicht toont per blok en mat:
- Welke poules waar staan (automatisch verdeeld, kan aangepast worden)
- Aantal wedstrijden per mat
- Status van de weging

Sleep poules naar andere matten om te verplaatsen.

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

### Gewichtsvelden: Ingeschreven vs Gewogen

Het systeem houdt **twee aparte gewichten** bij per judoka:

| Veld | Wanneer | Verandert |
|------|---------|-----------|
| `gewicht` | Ingeschreven gewicht (voorbereiding) | Alleen bij import/handmatige wijziging |
| `gewicht_gewogen` | Gemeten gewicht (wedstrijddag) | Bij elke weging |

**Waar wordt welk gewicht getoond?**

| Pagina | Toont | Reden |
|--------|-------|-------|
| **Poules** (voorbereiding) | `gewicht` (ingeschreven) | Voorbereiding = plan gebaseerd op inschrijving |
| **Blokverdeling** | `gewicht` (ingeschreven) | Idem |
| **Zaaloverzicht** | `gewicht` (ingeschreven) | Idem |
| **Weeglijst** | `gewicht` (ingeschreven) | Idem |
| **Wedstrijddag Poules** | `gewicht_gewogen` of `gewicht` | Toont actueel gewogen gewicht |
| **Wachtruimte** | `gewicht_gewogen` | Judoka's die overgepouled moeten worden |

**Belangrijk:** De voorbereidingsviews veranderen NOOIT op basis van weging. Alleen de **Wedstrijddag Poules** pagina toont mutaties.

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
- Judoka wordt automatisch uit poule gehaald (niet meer zichtbaar in poule)
- Info via **ⓘ icoon** in poule header (toont naam + reden)
- Judoka verschijnt in **wachtruimte** van de nieuwe gewichtsklasse voor overpoelen

### Weging Sluiten

1. Ga naar **Blokken** > **Blok X**
2. Klik **Sluit Weging**
3. Bevestig

Na sluiten:
- Geen weging meer mogelijk voor dit blok
- Overpoelen kan beginnen

### Automatische Aanwezigheidsbepaling

De aanwezigheid wordt **automatisch** bepaald op basis van weging:

| Situatie | Aanwezigheid |
|----------|--------------|
| Judoka is gewogen (`gewicht_gewogen` is ingevuld) | **Aanwezig** |
| Judoka is niet gewogen, weegtijd nog open | Onbekend |
| Judoka is niet gewogen, weegtijd gesloten | **Afwezig** |

**Belangrijk:**
- Gewogen = per definitie aanwezig (je kunt niet wegen zonder er te zijn)
- Na sluiting weegtijd worden niet-gewogen judoka's automatisch als afwezig beschouwd
- De wachtruimte toont alleen **aanwezige** judoka's die overgepouled moeten worden

## Overpoelen (Wedstrijddag Poules)

Na sluiten weegtijd moeten judoka's die buiten hun gewichtsklasse vallen worden verplaatst.

### Pagina: Wedstrijddag Poules

1. Ga naar **Wedstrijddag Poules** pagina
2. Per blok zie je alle categorieën met hun poules (inclusief lege poules voor overpoelen)
3. **ⓘ icoon** in poule header = klik voor info over afwezige/verwijderde judoka's
4. **Wachtruimte** (rechts) = gewogen judoka's die buiten hun gewichtsklasse vallen

### Workflow Overpoelen

1. Bekijk ⓘ icoon bij poules voor info over afwezige judoka's
2. Afwezige judoka's zijn al automatisch uit de poule (niet getoond)
3. Sleep judoka's uit wachtruimte naar juiste poule
4. Let op: max 6 judoka's per poule
5. Statistieken updaten automatisch

### Naar Zaaloverzicht Sturen

Wanneer een poule klaar is (overgepouled):

1. Klik **"→"** knop bij de poule (in de poule header)
2. Knop wordt **"✓"** (groen)
3. In zaaloverzicht verschijnt de categorie chip als **wit** zodra minstens 1 poule doorgestuurd is

**Knop kleuren per poule:**
| Knop | Status |
|------|--------|
| **→** (blauw) | Nog niet doorgestuurd |
| **✓** (groen) | Doorgestuurd naar zaaloverzicht |

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
2. Alleen actieve judoka's komen in schema (afwezigen worden automatisch overgeslagen)
3. Chip wordt **groen** met ✓
4. Categorie is nu klaar voor de mat

**Let op:** Afwezige judoka's (niet gewogen na sluiting weegtijd) en judoka's buiten gewichtsklasse worden NIET meegenomen in het wedstrijdschema!

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
