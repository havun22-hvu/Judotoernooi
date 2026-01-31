# Noodplan Handleiding voor Organisatoren

> **Voor wie:** Organisatoren, hoofdjury, toernooidirecteuren
> **Doel:** Stap-voor-stap gids voor een fail-safe toernooi

---

## Waarom een Noodplan?

Bij een groot toernooi (200+ deelnemers, 500+ bezoekers) kunnen dingen misgaan:
- Wifi valt uit
- Internet weg
- Laptop crasht
- Stroom uitval

Met dit noodplan blijft je toernooi **altijd** doorlopen.

---

## Kort Overzicht

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   NIVEAU 1: Alles werkt normaal                            │
│   → Via internet, judotournament.org                        │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   NIVEAU 2: Internet weg                                   │
│   → Lokaal netwerk neemt over (Deco mesh)                  │
│   → Toernooi gaat gewoon door                              │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   NIVEAU 3: Laptop crasht                                  │
│   → Backup laptop neemt over                               │
│   → Max 2 minuten onderbreking                             │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   NIVEAU 4: Alles crasht                                   │
│   → Papieren schema's                                       │
│   → Handmatig scoren                                        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

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

### Wat Je NIET Nodig Hebt

- Geen speciale software installatie
- Geen IT kennis
- Geen extra servers

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

### Scenario 1: Internet Weg

**Wat je ziet:**
- Publiek dashboard op smartphones werkt niet
- Lokale tablets werken WEL

**Wat je doet:**
- Niets! Lokaal netwerk werkt automatisch door
- Informeer publiek: "Tijdelijk geen live scores op telefoon"
- Wedstrijden gaan gewoon door

**Wanneer het terugkomt:**
- Scores worden automatisch gesynchroniseerd
- Geen actie nodig

---

### Scenario 2: Laptop A Crasht

**Wat je ziet:**
- Mat tablets reageren niet
- Foutmelding "Server niet bereikbaar"

**Wat je doet:**

```
1. Ga naar Laptop B
2. Dubbelklik "Activeer als Hoofdserver"
3. Wacht 30 seconden
4. Meld aan mat-vrijwilligers:
   "Nieuw IP: 192.168.1.101"
   OF
   "Refresh de pagina"
5. Test 1 tablet
6. Als het werkt: doorgaan
```

**Hoelang duurt dit?**
- 2-3 minuten maximaal

---

### Scenario 3: Beide Laptops Crashen

**Wat je ziet:**
- Niets werkt meer digitaal

**Wat je doet:**

```
1. RUSTIG BLIJVEN
2. Roep mat-vrijwilligers bij elkaar
3. Pak de geprinte wedstrijdschema's
4. Verdeel schema's over de matten
5. Instrueer:
   "Vul handmatig de W en J kolommen in"
   "W = 0 of 2 (verlies of winst)"
   "J = judopunten (5, 7 of 10)"
6. Jij noteert de uitslagen centraal
7. Na toernooi: invoeren in systeem
```

**Hoelang duurt dit?**
- 5-10 minuten om over te schakelen
- Daarna: toernooi gaat door

---

### Scenario 4: Deco Wifi Werkt Niet

**Wat je ziet:**
- Tablets kunnen niet verbinden
- Laptops kunnen elkaar niet vinden

**Wat je doet:**

```
1. Check Deco units:
   - Stroom aangesloten?
   - Lampje brandt wit?
2. Herstart Deco units:
   - Stekker eruit
   - 10 seconden wachten
   - Stekker erin
3. Wacht 2 minuten
4. Als het niet werkt:
   - Laptop A als wifi hotspot gebruiken
   - Instructies: [Windows] of [Mac] hotspot
```

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
✓ Internet weg → Niets doen, lokaal werkt
✓ Laptop A crasht → Start Laptop B
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
║  → Niets doen, lokaal werkt automatisch                       ║
║                                                               ║
║  LAPTOP A CRASHT?                                             ║
║  → Ga naar Laptop B                                           ║
║  → Dubbelklik "Activeer als Hoofdserver"                      ║
║  → Meld nieuw IP aan vrijwilligers                            ║
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
