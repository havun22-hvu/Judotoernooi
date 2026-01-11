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

### Toernooi Instellingen (3 tabs)

**Route:** `/toernooi/{id}/edit`
**View:** `resources/views/pages/toernooi/edit.blade.php`

#### Tab: Toernooi
1. **Basis Gegevens** - Naam, datum, locatie
2. **Wedstrijdschema's** - Volgorde wedstrijden per poulegrootte
3. **Leeftijds- en Gewichtsklassen** - Per categorie:
   - Max leeftijd
   - Naam (label)
   - Geslacht (Gemengd / Jongens / Meisjes)
   - Wedstrijdsysteem (Poules / Kruisfinale / Eliminatie)
   - Max kg verschil (0 = vaste klassen, >0 = dynamische indeling)
   - Gewichtsklassen (alleen bij max kg = 0)
   - Buttons: JBN 2025, JBN 2026

#### Tab: Organisatie
1. **Device Toegangen** - URLs + PINs beheren
2. **Snelkoppelingen** - Pagina Builder + Noodplan
3. **Online Betalingen** - Mollie configuratie
4. **Bloktijden** - Weeg- en starttijden (stappen van 15 min)

#### Tab: Test
- Test/debug functies voor development

---

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
- Tabel met kolommen: Naam, Club, Leeftijd, Gewicht(sklasse), Blok, Gewogen, Tijd
- Niet-gewogen rijen geel gemarkeerd
- Zoeken op naam of club
- Filter per blok en status (gewogen/niet gewogen)
- Live updates (auto-refresh elke 10 seconden)
- Statistieken: gewogen/totaal per blok (vaste breedte, altijd zichtbaar)
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

## Coachkaarten

Coaches krijgen toegangskaarten voor de dojo. Het aantal is gebaseerd op het **grootste blok** van de club.

### Berekening aantal coachkaarten

```
Formule: ceil(max_judokas_per_blok / judokas_per_coach)

Voorbeeld: Club met 15 judoka's verdeeld over 3 blokken (8, 4, 3)
→ Grootste blok = 8 judoka's
→ ceil(8 / 5) = 2 coachkaarten

┌────────────────────────────┬─────────┐
│ Max judoka's in één blok   │ Kaarten │
├────────────────────────────┼─────────┤
│ 1-5                        │ 1       │
│ 6-10                       │ 2       │
│ 11-15                      │ 3       │
│ 16-20                      │ 4       │
└────────────────────────────┴─────────┘
```

**Instelling:** `judokas_per_coach` in toernooi instellingen (default: 5)

**Fallback:** Als blokken nog niet zijn toegewezen, wordt het totaal aantal judoka's gebruikt.

### Implementatie

**Model:** `app/Models/Club.php`
```php
public function berekenAantalCoachKaarten(Toernooi $toernooi): int
{
    $perCoach = $toernooi->judokas_per_coach ?? 5;

    $judokas = $this->judokas()
        ->where('toernooi_id', $toernooi->id)
        ->with('poules.blok')
        ->get();

    if ($judokas->isEmpty()) {
        return 0;
    }

    // Count judokas per blok
    $judokasPerBlok = [];
    foreach ($judokas as $judoka) {
        foreach ($judoka->poules as $poule) {
            if ($poule->blok_id) {
                $blokId = $poule->blok_id;
                $judokasPerBlok[$blokId] = ($judokasPerBlok[$blokId] ?? 0) + 1;
            }
        }
    }

    // Fallback to total if no blokken assigned
    if (empty($judokasPerBlok)) {
        return (int) ceil($judokas->count() / $perCoach);
    }

    // Use largest block
    $maxJudokasInBlok = max($judokasPerBlok);
    return (int) ceil($maxJudokasInBlok / $perCoach);
}
```

### Genereren coachkaarten

**Controller:** `CoachKaartController@genereer`

Bij genereren:
1. Tel judoka's per club voor dit toernooi
2. Bereken benodigd aantal kaarten
3. Maak ontbrekende kaarten aan
4. Verwijder overtollige (niet-gescande) kaarten

```php
foreach ($clubs as $club) {
    $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi);
    $huidigAantal = $club->coachKaartenVoorToernooi($toernooi->id)->count();

    // Create missing cards
    for ($i = $huidigAantal; $i < $benodigdAantal; $i++) {
        CoachKaart::create([...]);
    }

    // Remove excess cards (only unscanned ones)
    if ($huidigAantal > $benodigdAantal) {
        // delete excess...
    }
}
```

---

## Dojo Scanner Interface

**Pad:** `resources/views/pages/dojo/scanner.blade.php`

### Toegang
- URL + PIN + device binding
- Beheer via Instellingen → Organisatie → Dojo toegangen

### Layout (Standalone PWA) - REFERENTIE VOOR ALLE SCANNERS

**Bovenste 45% - Scanner Area** (`bg-blue-800/50`):
1. **Scan knop** (inactief): Grote groene ronde knop (w-28 h-28)
2. **Scanner** (actief): Camera preview + rode "Stop" knop eronder
3. **Handmatig invoeren**: "Of voer code handmatig in..." knop

**Onderste 55% - Content Area**:
1. **Instructies**: Genummerde stappen (wit vlak)
2. **Stats**: "Coaches gescand vandaag" + teller
3. **Info**: Blauwe info balk

**Kleur theme:** #1e40af (blue-800)

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

## Weegkaart (Judoka's eigen kaart)

**Route:** `/weegkaart/{qr_code}`
**View:** `resources/views/pages/weegkaart/show.blade.php`
**Controller:** `WeegkaartController@show`

### Inhoud Weegkaart
- **Header**: Toernooi naam + datum
- **Naam**: Groot en prominent (voor weegkamer)
- **Club**: Onder de naam
- **Classificatie**: Leeftijd, gewicht, band, geslacht
- **Blok info** (indien toegewezen):
  - Blok naam (bijv. "Blok 1")
  - Starttijd wedstrijden
  - Weegtijden (start - einde)
  - **Mat nummer** (alleen na "Maak weegkaarten")
- **QR code**: Voor scannen bij weging
- **Download/Delen**: Knoppen voor opslaan en delen

### Vereisten
1. Judoka moet in poule zitten → blok wordt getoond
2. Poule moet mat hebben + `weegkaarten_gemaakt_op` gezet → mat wordt getoond

---

## Versie & Updates

- Versie in `config/toernooi.php` → `version`
- Service Worker in `public/sw.js` → `VERSION`
- **Beide verhogen bij release!**
- Forceer Update knop in instellingen modal
