---
title: Weging Interface
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Weging Interface

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Weging Interface

De Weging heeft **2 totaal verschillende versies**:

### Versie 1: Admin/Hoofdjury - Weeglijst (met menu)

**Route:** `/toernooi/{toernooi}/weging/interface`
**View:** `resources/views/pages/weging/interface-admin.blade.php`
**Layout:** `layouts.app` - volledig menu bovenaan

**Toegang:**
- Ingelogd als organisator, beheerder of hoofdjury
- Via menu: Weging → Weging Interface

**Doel:** Live overzicht van weegstatus alle judoka's

**Functionaliteit:**
- Tabel met kolommen: Naam, Club, Leeftijd, Gewicht(sklasse), Blok, Gewogen, Tijd, **Actie**
- Niet-gewogen rijen geel gemarkeerd
- Zoeken op naam of club
- Filter per blok en status (gewogen/niet gewogen)
- Live updates (auto-refresh elke 10 seconden)
- Statistieken: gewogen/totaal per blok (vaste breedte, altijd zichtbaar)
- Countdown timer naar eindtijd weging per blok (uu:mm:ss format)
- **"Wijzig" knop per judoka** - opent modal om gewicht aan te passen:
  - Werkt ook na weegtijd sluiting
  - Gewicht 0 = afmelden (kan niet deelnemen)
  - "Afmelden" snelknop in modal
- **Knop "Blok X: Einde weegtijd"** - verschijnt naast blok-filter als een blok geselecteerd is:
  - Sluit de weegtijd voor dat blok
  - Markeert alle niet-gewogen judoka's als afwezig
  - Bevestigingsdialog voordat actie uitgevoerd wordt
  - Knop toont "Gesloten" als blok al gesloten is
  - **Technische werking:** Zie `3-DEVELOPMENT/FUNCTIES.md` → "Sluit Weegtijd"

### Versie 2: Weging vrijwilliger - Scanner PWA (standalone)

**Route:** `/toegang/{code}`
**View:** `resources/views/pages/weging/interface.blade.php`
**Layout:** Standalone PWA - geen menu

**Toegang:**
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Weging toegangen

**Doel:** Snel wegen met QR scanner en numpad

**Functionaliteit:**
- **Scan QR** of **zoek op naam** → judoka selecteren
- Numpad voor gewicht invoeren
- **Gewicht 0 = afmelden** (kan niet deelnemen) - tip tekst onder registreer knop
- Statistieken: gewogen/totaal per blok
- Countdown timer naar eindtijd weging

### Layout verschil

| Versie | Inhoud | Layout |
|--------|--------|--------|
| **Admin** | Weeglijst (tabel) | layouts.app met menu |
| **Vrijwilliger** | Scanner + numpad | Standalone PWA 45%/55% split |

### Scanner Specs (alleen vrijwilliger versie)
- Html5Qrcode library
- Max-width: 300px, qrbox: 220px
- Min-height: 200px voor zichtbaarheid
- Zoekinput altijd zichtbaar onder scanner

### Scanner Gedrag
- **Auto-stop**: Scanner stopt automatisch na succesvolle scan
- **QR URL extractie**: Scanner herkent volledige weegkaart URLs en extraheert QR code
- **Stop knop**: ONDER de scanner (niet ernaast) zodat altijd bereikbaar
- **Numpad**: Verschijnt na selectie judoka voor gewichtinvoer

### Weging Interface Layout (mobiel) - v1.1.3

**Identiek aan Dojo Scanner layout (45%/55% split)**

**Bovenste 45% - Scanner Area** (`bg-blue-800/50`):
1. **Scan knop** (inactief): Grote groene ronde knop gecentreerd
2. **Scanner** (actief): Camera preview + rode "Stop" knop eronder
3. **Zoekveld**: "Of zoek op naam..." - altijd zichtbaar onderaan area

**Onderste 55% - Content Area**:
1. **Instructies** (geen judoka geselecteerd): Genummerde stappen
2. **Judoka sectie** (judoka geselecteerd):
   - Judoka info (naam, club, gewichtsklasse, blok)
   - Gewicht input veld
   - Numpad (4x3 grid + C knop)
   - Groene "Registreer" knop
   - Feedback melding
3. **Stats + History** (altijd zichtbaar onderaan):
   - "Recent gewogen" + totaal aantal
   - Lijst laatste 10 gewogen (opgeslagen in localStorage)

---

