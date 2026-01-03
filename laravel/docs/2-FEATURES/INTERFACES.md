# Interfaces - PWA & Devices

> **Workflow info:** Zie `GEBRUIKERSHANDLEIDING.md` voor voorbereiding vs wedstrijddag

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
| **Dojo Scanner** | Dojo Scanner | 📱 Smartphone | manifest-dojo.json |
| **Weging** | Weging | 📱 Smartphone / Tablet | manifest-weging.json |
| **Mat** | Mat Interface | 💻 PC / Laptop / Tablet | manifest-mat.json |
| **Spreker** | Spreker | 📋 iPad / Tablet | manifest-spreker.json |

---

## Weging Interface

**Pad:** `resources/views/pages/weging/interface.blade.php`

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

### Layout (Standalone PWA)
- **Identiek aan Weging** - 45%/55% split
- Fixed layout: scanner bovenin (45%), info onderin (55%)
- Blauwe kleur theme (#1e40af)

### Functionaliteit
- Scan coach kaart QR-code
- Valideer coach toegang
- Toon foto voor verificatie
- Handmatig invoeren optie (altijd zichtbaar onder scanner)

### Scanner Specs
- Html5Qrcode library
- Max-width: 300px, qrbox: 220px
- Min-height: 200px voor zichtbaarheid

---

## Mat Interface

**Pad:** `resources/views/pages/mat/interface.blade.php`

### Layout (Standalone PWA)
- Standalone header met klok
- Geen navigatie tabs
- Blauwe kleur theme (#1e40af)

### Functionaliteit
- Blok/Mat selectie
- Poules per mat bekijken
- Wedstrijden afwerken (uitslag registreren)
- Eliminatie bracket met drag & drop
- Stand bijhouden
- Poule afronden → naar spreker

---

## Spreker Interface

**Pad:** `resources/views/pages/spreker/interface.blade.php`

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

## Installatie

1. Open de interface pagina in browser
2. Klik tandwiel (⚙️) rechtsboven → Instellingen
3. Klik "Installeer [App Naam]"
4. PWA opent direct deze pagina (niet homepage)

## Versie & Updates

- Versie in `config/toernooi.php` → `version`
- Service Worker in `public/sw.js` → `VERSION`
- **Beide verhogen bij release!**
- Forceer Update knop in instellingen modal
