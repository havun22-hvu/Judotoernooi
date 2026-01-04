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

### Admin vs Device-bound

De interfaces Mat, Spreker en Weging hebben elk **2 versies**:

| Interface | Admin/Organisator/Hoofdjury | Device-bound vrijwilliger |
|-----------|---------------------------|--------------------------|
| **Mat** | layouts.app (met menu) | Standalone PWA |
| **Spreker** | layouts.app (met menu) | Standalone PWA |
| **Weging** | layouts.app (met menu) | Standalone PWA |

- **Admin/Organisator/Hoofdjury** → via menu → zien `layouts.app` met volledig menu
- **Device-bound vrijwilliger** → via speciale URL + PIN → zien Standalone PWA zonder menu

Zie de specifieke interface secties voor routes en views.

---

## Device Binding voor PWA's

Alle standalone PWA's (Weging, Mat, Spreker, Dojo) vereisen device binding:

### Flow
1. Organisator/Hoofdjury maakt toegang aan (Instellingen → Organisatie)
2. Vrijwilliger ontvangt URL + PIN
3. Eerste keer: opent URL → voert PIN in → device wordt gebonden
4. Daarna: device wordt herkend → direct naar interface

### Beheer (Instellingen → Organisatie)
- Toegangen aanmaken/verwijderen per rol
- Device status zien (gebonden / wachtend)
- Device resetten (nieuw device kan binden)
- Automatische reset bij "Einde toernooi"

### Layout Organisatie tab
De Organisatie tab bevat:
1. **Device Toegangen** - URLs + PINs beheren
2. **Snelkoppelingen** - Pagina Builder + Noodplan
3. **Online Betalingen** - Mollie configuratie
4. **Bloktijden** - Weeg- en starttijden (stappen van 15 min)

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
- Tabel met alle judoka's en hun weegstatus
- Filter per blok
- Live updates (auto-refresh elke 10 seconden)
- Statistieken: gewogen/totaal per blok
- Countdown timer naar eindtijd weging per blok

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

De Mat Interface heeft **2 versies** afhankelijk van wie het opent:

### Versie 1: Admin/Hoofdjury (met menu)

**Route:** `/toernooi/{toernooi}/mat/interface`
**View:** `resources/views/pages/mat/interface-admin.blade.php`
**Layout:** `layouts.app` - volledig menu bovenaan

**Toegang:**
- Ingelogd als organisator, beheerder of hoofdjury
- Via menu: Matten → Mat Interface

**Doel:** Overzicht houden, kunnen navigeren naar andere pagina's

### Versie 2: Tafeljury bij de mat (standalone PWA)

**Route:** `/toegang/{code}` (bijv. `/toegang/4BRJIYPTHSTK`)
**View:** `resources/views/pages/mat/interface.blade.php`
**Layout:** Standalone PWA - geen menu, alleen header met klok

**Toegang:**
- URL + PIN + device binding
- **Gekoppeld aan specifieke mat** (Mat 1, Mat 2, etc.)
- Beheer via Instellingen → Organisatie → Mat toegangen

**Doel:** Gefocust werken, geen afleiding door navigatie

### Layout verschil

| Versie | Menu | Header | Navigatie |
|--------|------|--------|-----------|
| **Admin** | layouts.app (blauw menu) | Standaard | Volledig |
| **Tafeljury** | Geen | Standalone + klok | Geen |

### Functionaliteit (beide versies)
- Blok/Mat selectie
- Poules per mat bekijken
- Wedstrijden afwerken (uitslag registreren)
- Eliminatie bracket met drag & drop
- Stand bijhouden
- Poule afronden → naar spreker

---

## Spreker Interface

De Spreker Interface heeft **2 versies** afhankelijk van wie het opent:

### Versie 1: Admin/Hoofdjury (met menu)

**Route:** `/toernooi/{toernooi}/spreker/interface`
**View:** `resources/views/pages/spreker/interface-admin.blade.php`
**Layout:** `layouts.app` - volledig menu bovenaan

**Toegang:**
- Ingelogd als organisator, beheerder of hoofdjury
- Via menu: Spreker

**Doel:** Overzicht houden, kunnen navigeren naar andere pagina's

### Versie 2: Spreker vrijwilliger (standalone PWA)

**Route:** `/toegang/{code}`
**View:** `resources/views/pages/spreker/interface.blade.php`
**Layout:** Standalone PWA - geen menu

**Toegang:**
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Spreker toegangen

**Doel:** Gefocust werken, geen afleiding door navigatie

### Layout verschil

| Versie | Menu | Kenmerken |
|--------|------|-----------|
| **Admin** | layouts.app (blauw menu) | Standaard |
| **Vrijwilliger** | Geen | Standalone header + klok, auto-refresh |

### Functionaliteit (beide versies)
- Wachtrij afgeronde poules
- Eindstand met 1e, 2e, 3e plaats
- Prijsuitreiking markeren
- Geschiedenis (localStorage)
- Auto-refresh elke 10 seconden

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
