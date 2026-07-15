---
title: Dojo, Mat, Spreker & Hoofdjury
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Dojo, Mat, Spreker & Hoofdjury

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Dojo Scanner Interface

**Pad:** `resources/views/pages/dojo/scanner.blade.php`

### Toegang
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Dojo toegangen

### Layout (Standalone PWA)

Volgt het standaard `Consistent Layout Pattern` (zie boven) — 45% scanner area + 55% content area. Kleur-theme: `#1e40af` (blue-800). Content-vlak: instructies, "Coaches gescand vandaag"-teller, blauwe info-balk.

### Functionaliteit
- Scan coachkaart QR-code
- **Toont foto van coach** → vrijwilliger vergelijkt met persoon
- Valideer coach toegang
- Handmatig invoeren optie (modal)

### Scanner Specs
- Html5Qrcode library
- Max-width: 300px, qrbox: 220x220, aspectRatio: 1.0
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
- **BELANGRIJK:** Elke mat heeft eigen PWA toegang. Mat 1 PWA ziet alleen Mat 1, niet andere matten.

**Doel:** Gefocust werken, geen afleiding door navigatie

**Mat binding:** De mat dropdown is niet wijzigbaar voor device-bound toegang. Header toont "Mat X Interface". 6 matten = 6 aparte PWA toegangen.

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
- **Real-time updates:** Via Reverb WebSocket (score, beurt, poule_klaar, bracket) — geen polling meer

### Eliminatie A/B Split Mats

Eliminatie poules kunnen de B-groep op een aparte mat draaien. De mat interface past zich automatisch aan:

| `groep_filter` | Gedrag |
|----------------|--------|
| `null` | Beide tabs (A+B) zichtbaar (standaard, zelfde mat) |
| `'A'` | Alleen A-tab (Hoofdboom) zichtbaar |
| `'B'` | Alleen B-tab (Herkansing) zichtbaar, direct actief |

De "Afronden" knop verschijnt als alle wedstrijden van de zichtbare groep gespeeld zijn. Backend controleert of ALLE wedstrijden (A+B) klaar zijn voordat `spreker_klaar` wordt gezet.

> **Zie:** `ELIMINATIE/B-MAT-EN-WEERGAVE.md` → "B-groep op Aparte Mat"

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

**Tab 1: Uitslagen**
- Wachtrij afgeronde poules
- Eindstand met 1e, 2e, 3e plaats
- Prijsuitreiking markeren

**Tab 2: Oproepen**
- Geschiedenis van afgeroepen poules (localStorage)

**Tab 3: Notities (Spiekbriefje)**
- Schermvullende textarea voor welkomstteksten, aankondigingen
- **Word-wrap**: Lange woorden breken automatisch af (geen tekst buiten beeld)
- **Zoom**: +/- knoppen, lettergrootte 14-48px
- **Templates**: Opgeslagen per organisator (niet per toernooi!) in localStorage
- **Auto-save**: Elke 2 seconden bij typen
- **Fixed toolbar**: Altijd zichtbaar onderaan scherm

**Algemeen:**
- Auto-refresh elke 10 seconden
- **Real-time updates:** Auto-reload bij `mat-poule-klaar` event (via Reverb)

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

