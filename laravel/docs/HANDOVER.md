# Handover - 2 februari 2026

## Vandaag gedaan: Afmelden judoka's

### 1. Gewicht 0 = Afmelden
- Bij weging interface: gewicht 0 invoeren markeert judoka als afwezig
- WegingController accepteert nu gewicht 0 (voorheen min 15)
- Tip tekst toegevoegd onder registreer knop

### 2. Weeglijst Live - Wijzig functie
- "Wijzig" knop toegevoegd per judoka in interface-admin
- Modal met gewicht input + Afmelden knop
- Werkt ook na weegtijd sluiting

### 3. Wedstrijddag poules - Afmelden
- ✕ knop bij elke judoka in poule-card (hover)
- "Afmelden" knop in zoek-match modal (🔍)
- Bevestiging gevraagd voordat afgemeld wordt
- Controller method `meldJudokaAf` + route toegevoegd

### 4. Herstel endpoint (backend klaar)
- `herstelJudoka` method in WedstrijddagController
- Route: `POST wedstrijddag/herstel-judoka`
- Zet `aanwezigheid` terug naar null

---

## Morgen verder: Herstel afwezige judoka's

### Wat moet gebeuren:
De ⓘ info-icon in de poule-card header toont nu alleen een tooltip met afwezige judoka's. Dit moet uitgebreid worden:

1. **Van tooltip naar interactieve dropdown**
   - Klik op ⓘ → dropdown met afwezige judoka's
   - Per judoka een "Herstel" knop

2. **Herstel actie**
   - Roept `herstelJudoka` endpoint aan (al klaar)
   - Judoka komt in wachtruimte terecht (poule-koppeling is al weg)
   - Pagina refresh

### Relevante bestanden:
- `poule-card.blade.php:76-79` - huidige tooltip code
- `WedstrijddagController.php:839-862` - herstelJudoka method
- `web.php:367` - route definitie

---

## Eerdere handover: Noodplan pagina

### Nieuwe secties op tab 3 (Noodplan/Instellingen):
1. Lokaal netwerk vs Internet uitleg - met live status metingen
2. Netwerk modus keuze - MET/ZONDER eigen router (Deco)
3. IP-adressen configureren - 3 velden met copy knoppen
4. Voorbereiding (avond ervoor) - download knoppen
5. Bij storing: overstappen naar lokale server

### Database velden (al aangemaakt):
- `toernooien.local_server_primary_ip` (varchar 45)
- `toernooien.local_server_standby_ip` (varchar 45)
- `toernooien.hotspot_ip` (varchar 45)
- `toernooien.heeft_eigen_router` (boolean)
- `toernooien.eigen_router_ssid` (varchar 100)
- `toernooien.hotspot_ssid` (varchar 100)

---

## Test URLs
- Weeglijst Live: `https://staging.judotournament.org/.../weging/interface`
- Wedstrijddag poules: `https://staging.judotournament.org/.../wedstrijddag/poules`
