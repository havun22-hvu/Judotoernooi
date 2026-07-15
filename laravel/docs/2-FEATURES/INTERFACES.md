---
title: Interfaces per rol
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Interfaces per rol

> Elke rol heeft een eigen PWA met eigen rechten. Toegang loopt via device-binding: de eerste
> keer dat een link geopend wordt, koppelt het apparaat zich — daarna kan alleen dát apparaat erbij.
> **Index-doc** — de details staan in [`INTERFACES/`](INTERFACES/). Zie de wegwijzer onderaan.

## Wie ziet wat?

| Rol | Interface | Navigatie tabs | Authenticatie |
|-----|-----------|----------------|---------------|
| **Superadmin** | layouts.app | Volledig | Wachtwoord (prod) / PIN (dev) |
| **Organisator** | layouts.app | Volledig + financieel | Email + wachtwoord |
| **Beheerders** | layouts.app | Volledig (geen financieel) | Email + wachtwoord |
| **Hoofdjury** | layouts.app | Volledig (geen financieel) | URL + PIN + device |
| **Weging** | Standalone PWA | Geen | URL + device binding |
| **Mat** | Standalone PWA | Geen | URL + device binding |
| **Spreker** | Standalone PWA | Geen | URL + device binding |
| **Dojo** | Standalone PWA | Geen | URL + device binding |
| **Organisator (mobiel)** | Responsive dashboard | Quick-actions | Email + wachtwoord (bestaande login) |

### Admin vs Device-bound

De interfaces Mat, Spreker en Weging hebben elk **2 versies**:

| Interface | Admin/Organisator/Hoofdjury | Device-bound vrijwilliger |
|-----------|---------------------------|--------------------------|
| **Mat** | layouts.app (met menu) | Standalone PWA |
| **Spreker** | layouts.app (met menu) | Standalone PWA |
| **Weging** | layouts.app (met menu) | Standalone PWA |

- **Admin/Organisator/Hoofdjury** → via menu → zien `layouts.app` met volledig menu
- **Device-bound vrijwilliger** → via unieke URL (device binding bij eerste keer) → zien Standalone PWA zonder menu

Zie de specifieke interface secties voor routes en views.

---

## PWA Apps per Rol

| Interface | PWA Naam | Device | Verbinding | Manifest |
|-----------|----------|--------|------------|----------|
| **Dojo Scanner** | Dojo Scanner | Smartphone / Tablet | Mobiele data | manifest-dojo.json |
| **Weging** | Weging | Smartphone / Tablet | Mobiele data | manifest-weging.json |
| **Spreker** | Spreker | iPad / Tablet | Mobiele data | manifest-spreker.json |
| **Mat** | Mat Interface | PC / Laptop / Tablet / iPad | WiFi (jurytafel) | manifest-mat.json |
| **Hoofdjury** | - | PC / Laptop | WiFi (jurytafel) | - |
| **Organisator (mobiel)** | - | Smartphone | Mobiele data | - (responsive dashboard) |

### Portable gebruik (kleedkamers)

Weging en Dojo draaien op **smartphones/tablets met mobiele data**:
- Geen afhankelijkheid van sporthal WiFi
- Werkt in kleedkamers en bij ingang
- Alleen internetverbinding nodig (4G/5G voldoende)

Spreker draait op **iPad/tablet met mobiele data** (groter scherm voor tekst lezen)

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [TOEGANG](INTERFACES/TOEGANG.md) | Vrijwilligersdatabase en device-binding — hoe een apparaat aan een rol vastzit. |
| [PORTAAL](INTERFACES/PORTAAL.md) | Budoschool-portaal: modus, wie mag muteren. |
| [WEGING](INTERFACES/WEGING.md) | Weeglijst (admin) vs scanner-PWA (vrijwilliger), scanner-specs. |
| [COACHKAARTEN](INTERFACES/COACHKAARTEN.md) | De twee fasen en het genereren van kaarten. |
| [COACHKAARTEN-QR](INTERFACES/COACHKAARTEN-QR.md) | Time-based QR tegen screenshot-misbruik. |
| [COACHKAART-OVERDRACHT](INTERFACES/COACHKAART-OVERDRACHT.md) | Kaart doorgeven aan een andere coach. |
| [COACH-INCHECK](INTERFACES/COACH-INCHECK.md) | In/uitcheck-flow en de database erachter (optioneel systeem). |
| [COACH-HISTORY](INTERFACES/COACH-HISTORY.md) | Check-in history-overzichten. |
| [COACH-OVERRULE](INTERFACES/COACH-OVERRULE.md) | Hoofdjury die een check-in overrulet. |
| [MAT-DOJO-SPREKER](INTERFACES/MAT-DOJO-SPREKER.md) | Dojo-scanner, mat-, spreker- en hoofdjury-interface. |
| [WEEGKAART](INTERFACES/WEEGKAART.md) | De kaart van de judoka zelf. |
| [PUBLIEK](INTERFACES/PUBLIEK.md) | Toeschouwers-PWA: tabs, live matten, favorieten, Reverb. |
| [ORGANISATOR-MOBIEL](INTERFACES/ORGANISATOR-MOBIEL.md) | Responsive dashboard voor de organisator. |

## Consistent Layout Pattern

Alle PWA scanner interfaces (Weging, Dojo Scanner) zijn **standalone** met identieke layout:

| Element | Positie | Hoogte |
|---------|---------|--------|
| Header | Top | ~60px |
| Scanner area | Bovenste helft | 45% van scherm |
| Content area | Onderste helft | 55% van scherm |

**Scanner Area (45%) bevat:**
1. **Scan knop** (inactief): Grote groene ronde knop (w-28 h-28)
2. **Scanner** (actief): Camera preview + rode stop knop eronder
3. **Zoekveld**: "Of zoek op naam..." - altijd zichtbaar onderaan

**Kenmerken:**
- Scanner pas actief NA klikken op groene knop
- Stop knop ALTIJD direct onder scanner (niet ernaast!)
- Scanner: max-width 300px, qrbox 220x220, min-height 200px
- Blauwe kleur theme (#1e40af)

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
