---
title: Als er iets misgaat, status & contact
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Als er iets misgaat, status & contact

> Onderdeel van [Noodplan-handleiding](../NOODPLAN-HANDLEIDING.md).

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

