# Interfaces - PWA & Devices

> **Workflow info:** Zie `GEBRUIKERSHANDLEIDING.md` voor voorbereiding vs wedstrijddag
> **Authenticatie:** Zie `PLANNING_AUTHENTICATIE_SYSTEEM.md` voor device binding details

## Wie ziet wat?

| Rol | Interface | Navigatie tabs | Authenticatie |
|-----|-----------|----------------|---------------|
| **Superadmin** | layouts.app | Volledig | Wachtwoord (prod) / PIN (dev) |
| **Organisator** | layouts.app | Volledig + financieel | Email + wachtwoord |
| **Beheerders** | layouts.app | Volledig (geen financieel) | Email + wachtwoord |
| **Hoofdjury** | layouts.app | Volledig (geen financieel) | URL + PIN + device |
| **Weging** | Standalone PWA | Geen | URL + PIN + device |
| **Mat** | Standalone PWA | Geen | URL + PIN + device |
| **Spreker** | Standalone PWA | Geen | URL + PIN + device |
| **Dojo** | Standalone PWA | Geen | URL + PIN + device |

---

## Device Binding voor PWA's

Alle standalone PWA's (Weging, Mat, Spreker, Dojo) vereisen device binding:

### Flow
1. Organisator/Hoofdjury maakt toegang aan (Instellingen → Organisatie)
2. Vrijwilliger ontvangt URL + PIN
3. Eerste keer: opent URL → voert PIN in → device wordt gebonden
4. Daarna: device wordt herkend → direct naar interface

### Beheer
- Toegangen aanmaken/verwijderen per rol
- Device status zien (gebonden / wachtend)
- Device resetten (nieuw device kan binden)
- Automatische reset bij "Einde toernooi"

---

## Consistent Layout Pattern

Alle PWA interfaces (Weging, Dojo Scanner, Mat, Spreker) zijn **standalone** - geen navigatie tabs:

| Element | Positie | Percentage |
|---------|---------|------------|
| Header | Top | ~60px |
| Scanner area | Boven | 45% |
| Controls/Info | Onder | 55% |

**Kenmerken:**
- Vaste hoogtes (geen springende elementen)
- Scanner: 300px max-width, 220px qrbox
- Blauwe kleur theme (#1e40af)
- Input/button altijd zichtbaar onder scanner

---

## PWA Apps per Rol

| Interface | PWA Naam | Device | Manifest |
|-----------|----------|--------|----------|
| **Dojo Scanner** | Dojo Scanner | Smartphone | manifest-dojo.json |
| **Weging** | Weging | Smartphone / Tablet | manifest-weging.json |
| **Mat** | Mat Interface | PC / Laptop / Tablet | manifest-mat.json |
| **Spreker** | Spreker | iPad / Tablet | manifest-spreker.json |

---

## Weging Interface

**Pad:** `resources/views/pages/weging/interface.blade.php`

### Toegang
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Weging toegangen

### Layout (Standalone PWA)
- **Geen** navigatie/header van layouts.app
- Fixed layout: scanner bovenin (45%), controls onderin (55%)
- Blauwe kleur theme (#1e40af)

### Functionaliteit
- **Scan QR** of **zoek op naam** → judoka selecteren
- Numpad voor gewicht invoeren
- Statistieken: gewogen/totaal per blok
- Countdown timer naar eindtijd weging (indien ingesteld)
- Blok sluiten wanneer weegtijd voorbij is

### Scanner Specs
- Html5Qrcode library
- Max-width: 300px, qrbox: 220px
- Min-height: 200px voor zichtbaarheid
- Zoekinput altijd zichtbaar onder scanner

---

## Dojo Scanner Interface

**Pad:** `resources/views/pages/dojo/scanner.blade.php`

### Toegang
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Dojo toegangen

### Layout (Standalone PWA)
- **Identiek aan Weging** - 45%/55% split
- Fixed layout: scanner bovenin (45%), info onderin (55%)
- Blauwe kleur theme (#1e40af)

### Functionaliteit
- Scan coachkaart QR-code
- **Toont foto van coach** → vrijwilliger vergelijkt met persoon
- Valideer coach toegang
- Handmatig invoeren optie (altijd zichtbaar onder scanner)

### Scanner Specs
- Html5Qrcode library
- Max-width: 300px, qrbox: 220px
- Min-height: 200px voor zichtbaarheid

---

## Mat Interface

**Pad:** `resources/views/pages/mat/interface.blade.php`

### Toegang
- URL + PIN + device binding
- **Gekoppeld aan specifieke mat** (Mat 1, Mat 2, etc.)
- Beheer via Instellingen → Organisatie → Mat toegangen

### Layout (Standalone PWA)
- Standalone header met klok
- Geen navigatie tabs
- Blauwe kleur theme (#1e40af)

### Functionaliteit
- Blok/Mat selectie (alleen toegewezen mat)
- Poules per mat bekijken
- Wedstrijden afwerken (uitslag registreren)
- Eliminatie bracket met drag & drop
- Stand bijhouden
- Poule afronden → naar spreker

---

## Spreker Interface

**Pad:** `resources/views/pages/spreker/interface.blade.php`

### Toegang
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Spreker toegangen

### Layout (Standalone PWA)
- Standalone header met klok
- Geen navigatie tabs
- Auto-refresh elke 10 seconden

### Functionaliteit
- Wachtrij afgeronde poules
- Eindstand met 1e, 2e, 3e plaats
- Prijsuitreiking markeren
- Geschiedenis (localStorage)

---

## Hoofdjury Interface

**Pad:** Gebruikt reguliere `layouts.app`

### Toegang
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Hoofdjury toegangen

### Functionaliteit
- Volledig overzicht (alle tabs)
- Device management (URLs + PINs uitdelen)
- **Geen** toegang tot financieel

---

## Installatie PWA

1. Open de interface pagina in browser (na device binding)
2. Klik tandwiel (rechtsonder) → Instellingen
3. Klik "Installeer [App Naam]"
4. PWA opent direct deze pagina (niet homepage)

## Versie & Updates

- Versie in `config/toernooi.php` → `version`
- Service Worker in `public/sw.js` → `VERSION`
- **Beide verhogen bij release!**
- Forceer Update knop in instellingen modal
