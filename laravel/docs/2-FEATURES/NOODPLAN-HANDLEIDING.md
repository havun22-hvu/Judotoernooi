# Noodplan Handleiding voor Organisatoren

> **Voor wie:** Organisatoren, hoofdjury, toernooidirecteuren
> **Doel:** Stap-voor-stap gids voor een fail-safe toernooi

---

## Noodplan in de App

De noodplan configuratie vind je in de app onder:
**Toernooi → Instellingen → Tab 3 (Noodplan)**

Hier kun je:
- **Netwerk status** live bekijken (lokaal netwerk + internet latency)
- **Scenario kiezen** (A: goed bereik / B: slecht bereik / C: geen internet)
- **Netwerk gegevens** configureren (router SSID, hotspot, server IP's)
- **Noodbackup downloaden** (avond voor het toernooi)
- **Poule-indeling downloaden** (Excel voor printen)

---

## Waarom een Noodplan?

Bij een groot toernooi (200+ deelnemers, 500+ bezoekers) kunnen dingen misgaan:
- Wifi valt uit
- Internet weg
- Laptop crasht
- Stroom uitval

Met dit noodplan blijft je toernooi **altijd** doorlopen.

---

## Scenario's — Welke setup past bij jouw sporthal?

### Altijd (bij elk scenario)
- **Laptops** voor hoofd-apps (mat interface, hoofdjury) — muis is preciezer
- **Tablets** voor vrijwilligers (wegen, dojo check-in, spreker)
- **Papieren backup** — schrijf uitslagen mee per mat
- **Hotspot** van tevoren klaarzetten op telefoon (naam + wachtwoord noteren)

### Scenario A: Goed internet bereik
| | |
|---|---|
| **Netwerk** | Sporthal WiFi |
| **Server** | judotournament.org (online) |
| **Publieke PWA** | Ja (live scores voor publiek) |
| **Vrijwilligers PWA** | Ja (mat, wegen, dojo, spreker) |
| **Eigen Deco** | Optioneel als backup netwerk |

**Storingen:**

| Situatie | Actie |
|----------|-------|
| Internet valt weg | Hotspot aan, alle tablets + laptops op hotspot |
| Internet + hotspot onmogelijk | Lokale server starten → scenario C |
| Online server crash | Lokale server starten → scenario C |

### Scenario B: Slecht WiFi bereik
| | |
|---|---|
| **Netwerk** | Eigen lokaal netwerk (Deco's ~60 devices / hubs / LAN-kabels) |
| **Internet** | Via LAN-aansluiting sporthal |
| **Server** | judotournament.org (online) |
| **Publieke PWA** | Nee (beperkte netwerkcapaciteit) |
| **Vrijwilligers PWA** | Ja (mat, wegen, dojo, spreker) |

**Storingen:**

| Situatie | Actie |
|----------|-------|
| Deco / eigen netwerk uitval | Herstarten Deco's, of overschakelen op LAN-kabels |
| Online server crash | Lokale server starten → scenario C |

### Scenario C: Geen internet / server crash
| | |
|---|---|
| **Netwerk** | Eigen lokaal netwerk (verplicht) |
| **Server** | Lokale server op primaire laptop |
| **Publieke PWA** | Nee |
| **Vrijwilligers PWA** | Ja, op lokaal IP (bijv. 192.168.1.100:8000) |

**Storingen:**

| Situatie | Actie |
|----------|-------|
| Primaire laptop crash | Standby laptop starten, tablets naar standby IP |
| Standby ook stuk | Papieren backup — verder op papier |

---

## Wat Heb Je Nodig?

### Hardware Checklist

| Item | Aantal | Doel |
|------|--------|------|
| Laptop A (organisator) | 1 | Hoofdserver |
| Laptop B (backup) | 1 | Reserve server |
| TP-Link Deco M4 | 3 units | Lokaal wifi netwerk |
| Mat tablets | Per mat | Score invoer |
| Printer | 1 | Noodplan prints |
| Papier | Voldoende | Backup schema's |

### Welke Laptop is Geschikt?

**Korte versie:** Elke laptop van na 2018 voldoet.

| Wat | Minimum | Beter |
|-----|---------|-------|
| Windows | Windows 10 | Windows 11 |
| Mac | macOS 10.15 | macOS 13+ |
| Geheugen | 4 GB | 8 GB |
| Opslag vrij | 10 GB | 20 GB |
| Accu | 2 uur | 4+ uur |

**Werkt WEL:**
- HP ProBook, EliteBook
- Lenovo ThinkPad
- Dell Latitude
- MacBook Air/Pro
- Acer Aspire (zakelijk)

**Werkt NIET:**
- Chromebooks (geen PHP)
- Tablets/iPads (alleen als client)
- Hele oude laptops (< 2015)

### Wat Je NIET Nodig Hebt

- Geen speciale software installatie
- Geen IT kennis
- Geen extra servers

---

## Opstartprocedure voor Beginners

> **Voor wie:** Iedereen die niet technisch is maar wel het systeem moet opzetten

### Welk scenario kies ik?

Check van tevoren het WiFi bereik in de sporthal:

| Bereik | Scenario | Advies |
|--------|----------|--------|
| Goed en stabiel | **A** | Sporthal WiFi gebruiken, Deco optioneel als backup |
| Matig of wisselend | **B** | Eigen Deco / hubs / LAN, internet via LAN-aansluiting |
| Geen of zeer slecht | **C** | Volledig lokaal, eigen netwerk verplicht |

**Tip:** Test het bereik met je telefoon op meerdere plekken in de zaal (bij de matten, jurytafel, tribunes).

### De Deco M4 Mesh Uitgelegd

**Wat is het?**
Een set van 3 kleine witte kastjes die samen een wifi netwerk maken.

**Belangrijk:** De Deco's worden **alleen op stroom** aangesloten, NIET op de laptops! Ze maken hun eigen wifi netwerk waar laptops en tablets draadloos mee verbinden.

**Hoeveel Deco's heb je nodig?**

| Aantal | Dekking | Geschikt voor |
|--------|---------|---------------|
| 2 | ~260m² | Kleine sporthal |
| 3 | ~400m² | Normale sporthal (standaard) |
| 5 | ~650m² | Grote sporthal |
| 10 | ~1300m² | Evenementenhal |

> Je kunt tot 10 Deco's combineren in één netwerk.

**Fysieke aansluiting:**
```
Stopcontact        Stopcontact        Stopcontact
     │                  │                  │
 ┌───┴───┐          ┌───┴───┐          ┌───┴───┐
 │ Deco 1│ ~~~~~~~~ │ Deco 2│ ~~~~~~~~ │ Deco 3│
 └───────┘  (wifi)  └───────┘  (wifi)  └───────┘

Dat is alles! Alleen stroom, geen kabels tussen Deco's nodig.
```

**Hoe werkt het?**
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│   SPORTHAL PLATTEGROND                                          │
│                                                                 │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                                                         │   │
│   │    [Deco 2]                              [Deco 3]       │   │
│   │       ◯                                     ◯           │   │
│   │                                                         │   │
│   │                                                         │   │
│   │     Mat 1         Mat 2         Mat 3        Mat 4      │   │
│   │    ┌─────┐       ┌─────┐       ┌─────┐      ┌─────┐     │   │
│   │    │     │       │     │       │     │      │     │     │   │
│   │    └─────┘       └─────┘       └─────┘      └─────┘     │   │
│   │                                                         │   │
│   │                                                         │   │
│   │                    [Deco 1]                             │   │
│   │                       ◯                                 │   │
│   │                  JURYTAFEL                              │   │
│   │              ┌──────────────┐                           │   │
│   │              │ Laptop A & B │                           │   │
│   │              │   Printer    │                           │   │
│   │              └──────────────┘                           │   │
│   │                                                         │   │
│   │   INGANG ════                                           │   │
│   └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Plaatsing tips:**
- Deco 1: Bij jurytafel (ALTIJD hier, dit is de hoofdunit)
- Deco 2 en 3: Aan de zijkanten, ongeveer halverwege de zaal
- Zet ze HOOG (op tafel/kast), niet op de grond
- Niet achter metalen kasten of pilaren

### Laptop Aansluiten - Stap voor Stap

**Wat je nodig hebt:**
- Laptop met oplader
- Stroom (stopcontact of stekkerdoos)
- Wifi (via Deco of sporthal)

**Stap 1: Laptop opstarten**
```
1. Sluit de oplader aan op de laptop
2. Sluit de oplader aan op het stopcontact
3. Druk op de aan/uit knop van de laptop
4. Wacht tot Windows/Mac volledig is opgestart
   (je ziet het bureaublad)
```

**Stap 2: Verbinden met wifi**

*Voor Windows:*
```
1. Klik rechtsonder op het wifi-icoon (📶)
2. Zoek "Deco_Toernooi" (of je netwerknaam)
3. Klik erop
4. Klik "Verbinden"
5. Voer het wachtwoord in
6. Wacht tot er "Verbonden" staat
```

*Voor Mac:*
```
1. Klik rechtsboven op het wifi-icoon (📶)
2. Zoek "Deco_Toernooi" (of je netwerknaam)
3. Klik erop
4. Voer het wachtwoord in
5. Wacht tot het vinkje verschijnt
```

**Stap 3: Browser openen**
```
1. Dubbelklik op Chrome, Edge, of Safari icoon
2. Of: klik op de taakbalk onderaan
3. Typ in de adresbalk: judotournament.org
4. Druk Enter
```

### Deco M4 Installeren - Eerste Keer

> **Eenmalig:** Dit hoef je maar 1x te doen (thuis, voor het toernooi)

**Wat je nodig hebt:**
- De 3 Deco units
- Stroom voor alle 3
- Je telefoon
- De Deco app (gratis in App Store / Play Store)

**Stappen:**
```
1. Download de "TP-Link Deco" app op je telefoon
2. Maak een account aan (of log in)
3. Sluit Deco 1 aan op stroom
4. Wacht tot lampje BLAUW knippert
5. Volg instructies in de app
6. Kies een netwerknaam, bijv: "Deco_Toernooi"
7. Kies een wachtwoord (schrijf dit op!)
8. Voeg Deco 2 en 3 toe via de app
9. Test: verbind je telefoon met het nieuwe netwerk
```

**Lampjes betekenis:**
| Kleur | Betekenis |
|-------|-----------|
| Blauw knipperend | Klaar om te installeren |
| Blauw vast | Bezig met opstarten |
| Groen/Wit | Alles werkt goed |
| Geel/Oranje | Zwak signaal (verplaats unit) |
| Rood | Geen verbinding (check stroom/andere units) |

### Tablets/iPads Verbinden

**Voor elke tablet:**
```
1. Ga naar Instellingen
2. Tik op "Wifi" of "Draadloos"
3. Zoek "Deco_Toernooi"
4. Tik erop
5. Voer wachtwoord in
6. Wacht op verbinding

7. Open de browser (Chrome of Safari)
8. Typ: 192.168.1.100:8000
9. Je ziet nu de toernooi app
10. Log in met je mat-PIN
```

### Printer Aansluiten

**Stap 1: Fysiek aansluiten**
```
1. Zet printer naast Laptop A
2. Sluit USB-kabel aan:
   - Ene kant in printer
   - Andere kant in laptop
3. Sluit stroomkabel aan
4. Zet printer aan
```

**Stap 2: Test printen**
```
1. Open de browser op Laptop A
2. Ga naar judotournament.org
3. Log in
4. Ga naar Noodplan
5. Klik op "Weeglijst"
6. Klik Ctrl+P (of Cmd+P op Mac)
7. Kies je printer
8. Klik "Afdrukken"
```

### Veelvoorkomende Problemen

**"Ik zie geen wifi netwerken"**
```
Check:
- Is de wifi aan op je laptop? (vaak een knopje of toetscombinatie)
- Is de Deco aangesloten en lampje wit/groen?
- Ben je dichtbij genoeg? (max 30 meter)
```

**"Wachtwoord wordt niet geaccepteerd"**
```
Check:
- Hoofdletters/kleine letters goed?
- Geen spaties aan het begin/eind?
- Juiste netwerk geselecteerd?
```

**"Pagina laadt niet"**
```
Check:
- Ben je verbonden met wifi? (kijk rechtsboven/rechtsonder)
- Heb je het juiste adres getypt? (192.168.1.100:8000)
- Draait de server op Laptop A?
```

**"Printer print niet"**
```
Check:
- USB-kabel goed aangesloten (beide kanten)?
- Printer aan?
- Papier in de printer?
- Inkt/toner niet leeg?
```

### Stroomvoorziening Tips

**Hoeveel stopcontacten heb je nodig?**
```
- Laptop A:        1 stopcontact
- Laptop B:        1 stopcontact
- Printer:         1 stopcontact
- Deco 1:          1 stopcontact
- Deco 2:          1 stopcontact
- Deco 3:          1 stopcontact
────────────────────────────────
TOTAAL:            6 stopcontacten
```

**Aanbevolen:**
- Neem 2 stekkerdozen mee (4-6 aansluitingen elk)
- Check of de sporthal genoeg stopcontacten heeft bij jurytafel
- Neem een verlengkabel mee (5-10 meter)

**Let op:**
- Zorg dat kabels niet over looppaden liggen (struikelgevaar!)
- Tape kabels vast met gaffertape als nodig
- Zet Deco units niet direct naast magnetrons of andere apparaten

---

## Voorbereiding (1 Dag Voor Toernooi)

### Stap 1: Laptops Voorbereiden

```
1. Start Laptop A
2. Open de browser
3. Ga naar judotournament.org
4. Log in als organisator
5. Ga naar het toernooi
6. Klik "Download Lokale Kopie"
7. Wacht tot download klaar is
8. Herhaal voor Laptop B
```

### Stap 2: Printer Test

```
1. Ga naar Noodplan in het menu
2. Print een test weeglijst
3. Controleer of kleuren goed printen
```

### Stap 3: Deco M4 Controleren

```
1. Sluit Deco aan op stroom
2. Wacht tot lampje wit brandt
3. Open Deco app op telefoon
4. Controleer: alle 3 units online
```

---

## Op De Wedstrijddag (Ochtend)

### Stap 1: Netwerk Opzetten

```
1. Plaats Deco units in sporthal:
   - 1 bij jurytafel (centraal)
   - 1 bij ingang
   - 1 aan andere kant zaal

2. Verbind Laptop A met Deco wifi
   - Netwerknaam: "Deco_Toernooi" (of je eigen naam)
   - Wachtwoord: [je gekozen wachtwoord]

3. Verbind Laptop B met zelfde netwerk
```

### Stap 2: Servers Starten

**Op Laptop A:**
```
1. Dubbelklik "Start Toernooi Server"
2. Wacht tot venster zegt "Server actief op 192.168.1.100"
3. Laat dit venster open!
```

**Op Laptop B:**
```
1. Dubbelklik "Start Backup Server"
2. Wacht tot venster zegt "Standby modus actief"
3. Laat dit venster open!
```

### Stap 3: Devices Verbinden

```
1. Pak mat tablet
2. Verbind met Deco wifi
3. Open browser
4. Ga naar: 192.168.1.100:8000
5. Log in met mat PIN
6. Herhaal voor alle tablets
```

### Stap 4: Noodplan Printen

```
1. Op Laptop A, ga naar Noodplan
2. Print de volgende documenten:
   - Weeglijsten (alle blokken)
   - Wedstrijdschema's per blok (matrix, leeg)
   - Contactlijst coaches
   - Zaaloverzicht
3. Leg prints klaar bij jurytafel
```

---

## Als Er Iets Misgaat

### Internet valt weg (scenario A)

**Wat je ziet:** Publieke PWA werkt niet, vrijwilligers PWA laden traag of niet.

**Wat je doet:**
1. Hotspot aan op telefoon
2. Alle tablets + laptops verbinden met hotspot
3. Wedstrijden gaan door via judotournament.org (via hotspot)

**Als hotspot ook niet lukt:** Lokale server starten → scenario C toepassen.

---

### Online server crash (scenario A/B)

**Wat je ziet:** judotournament.org reageert niet, foutmelding in browser.

**Wat je doet:**
1. Schakel over naar lokale server (zie "Lokale Server Starten" hieronder)
2. Alle tablets naar lokaal IP overschakelen
3. Publieke PWA is tijdelijk niet beschikbaar

---

### Deco / eigen netwerk uitval (scenario B)

**Wat je ziet:** Tablets kunnen niet verbinden, laptops zien elkaar niet.

**Wat je doet:**
1. Check Deco units: stroom aangesloten? Lampje brandt wit?
2. Herstart Deco's: stekker eruit, 10 sec wachten, stekker erin
3. Wacht 2 minuten
4. Als het niet werkt: overschakelen op LAN-kabels voor laptops

---

### Primaire laptop crash (scenario C)

**Wat je ziet:** Mat tablets reageren niet, foutmelding "Server niet bereikbaar".

**Wat je doet:**
1. Start standby laptop
2. Start lokale server op standby laptop
3. Alle tablets naar standby IP overschakelen

**Hoelang duurt dit?** 2-3 minuten maximaal.

---

### Alles crasht (noodgeval)

**Wat je ziet:** Niets werkt meer digitaal.

**Wat je doet:**
1. RUSTIG BLIJVEN
2. Pak de geprinte wedstrijdschema's
3. Verdeel schema's over de matten
4. Instrueer: "Vul handmatig W en J kolommen in"
5. Na toernooi: invoeren in systeem

---

## Status Controleren

Op het organisator scherm zie je altijd de status:

```
┌─────────────────────────────────────────────┐
│  Cloud Sync:     ● GROEN = OK              │
│  Backup Server:  ● GROEN = Standby actief  │
│  Netwerk:        ● GROEN = Stabiel         │
└─────────────────────────────────────────────┘
```

**Kleuren:**
- **GROEN** = Alles OK
- **ORANJE** = Waarschuwing, let op
- **ROOD** = Probleem, actie nodig

---

## Contactpersonen Bij Problemen

| Probleem | Wie bellen |
|----------|------------|
| Software bugs | Havun: [telefoonnummer] |
| Netwerk/hardware | Lokale IT vrijwilliger |
| Judotechnisch | Hoofdjury |

---

## Veelgestelde Vragen

### "Moet ik iets installeren?"

Nee. Alles werkt via de browser. Je hoeft alleen de "Download Lokale Kopie" functie te gebruiken.

### "Wat als ik geen backup laptop heb?"

Dan heb je 1 niveau minder bescherming. Zorg dat je de papieren backup goed voorbereidt.

### "Kunnen ouders nog meekijken als internet weg is?"

Niet op hun telefoon. Wel op de publieke schermen in de zaal (die draaien lokaal).

### "Hoeveel data kan ik kwijtraken?"

Maximaal de laatste 5 seconden. Dat is meestal 0-1 wedstrijden.

### "Moet ik een IT-er zijn?"

Nee. Als je een laptop kunt opstarten en een browser openen, kun je dit.

---

## Samenvatting

```
VOOR HET TOERNOOI:
✓ Download lokale kopie op 2 laptops
✓ Test Deco netwerk
✓ Print noodplan documenten

OP DE DAG:
✓ Zet Deco netwerk op
✓ Start beide laptops
✓ Verbind tablets
✓ Leg prints klaar

ALS HET MISGAAT:
✓ Internet weg → Hotspot aan, of lokale server starten
✓ Server crash → Lokale server starten (scenario C)
✓ Laptop crasht → Standby laptop overneemt
✓ Alles crasht → Papieren backup
```

---

## Bijlagen

### A. Voorbeeld Wedstrijdschema (Matrix)

```
┌────────────────────────────────────────────────────────────────┐
│ Open Westfries Judotoernooi              01-02-2026            │
├────────────────────────────────────────────────────────────────┤
│ Poule #12 - Mini's 7-8j 22-25kg     Mat 2 | Blok 1            │
├────┬──────────────────┬─────┬─────┬─────┬─────┬────┬────┬─────┤
│ Nr │ Naam             │  1  │  2  │  3  │ WP │ JP │Plts│
│    │                  │ W J │ W J │ W J │    │    │    │
├────┼──────────────────┼─────┼─────┼─────┼────┼────┼────┤
│ 1  │ Jan de Vries     │ █ █ │     │     │    │    │    │
│    │ (J.S. Hoorn)     │     │     │     │    │    │    │
├────┼──────────────────┼─────┼─────┼─────┼────┼────┼────┤
│ 2  │ Piet Jansen      │     │ █ █ │     │    │    │    │
│    │ (J.V. Enkhuizen) │     │     │     │    │    │    │
├────┼──────────────────┼─────┼─────┼─────┼────┼────┼────┤
│ 3  │ Kees Bakker      │     │     │ █ █ │    │    │    │
│    │ (S.C. Medemblik) │     │     │     │    │    │    │
└────┴──────────────────┴─────┴─────┴─────┴────┴────┴────┘

Legenda: W = Wedstrijdpunten (0 of 2) | J = Judopunten
         █ = Niet van toepassing (zelfde judoka)
```

### B. Snelle Referentiekaart

Print deze uit en hang op bij jurytafel:

```
╔═══════════════════════════════════════════════════════════════╗
║  NOODPLAN SNELGIDS                                            ║
╠═══════════════════════════════════════════════════════════════╣
║                                                               ║
║  INTERNET WEG?                                                ║
║  → Hotspot aan op telefoon                                    ║
║  → Alle devices op hotspot                                    ║
║                                                               ║
║  SERVER CRASH?                                                ║
║  → Lokale server starten op laptop                            ║
║  → Tablets naar lokaal IP overschakelen                       ║
║                                                               ║
║  LAPTOP CRASHT?                                               ║
║  → Standby laptop starten                                     ║
║  → Tablets naar standby IP                                    ║
║                                                               ║
║  ALLES CRASHT?                                                ║
║  → Pak geprinte schema's                                      ║
║  → Verdeel over matten                                        ║
║  → Handmatig invullen                                         ║
║                                                               ║
║  HULP NODIG?                                                  ║
║  → Havun: [telefoonnummer]                                    ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
```
