# Interfaces - PWA & Devices

> **Workflow info:** Zie `GEBRUIKERSHANDLEIDING.md` voor voorbereiding vs wedstrijddag
> **Authenticatie:** Device binding via 12-char role codes — zie `CheckDeviceBinding` middleware + `DeviceToegang` model.

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
| **Organisator (mobiel)** | Responsive dashboard | Quick-actions | Email + wachtwoord (bestaande login) |

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

## Vrijwilligers Database

Organisatoren kunnen vrijwilligers opslaan voor hergebruik tussen toernooien.

### Database

**Tabel:** `vrijwilligers`

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `id` | bigint | PK |
| `organisator_id` | bigint | FK → organisatoren |
| `voornaam` | string | Naam vrijwilliger |
| `telefoonnummer` | string | Voor WhatsApp linkjes |
| `functie` | enum | mat, weging, spreker, dojo, hoofdjury |
| `timestamps` | | created_at, updated_at |

### UI (Instellingen → Organisatie)

Eén link/knop opent popup voor vrijwilligers beheer:

```
┌─────────────────────────────────────────────────┐
│  VRIJWILLIGERS                         [Sluiten]│
├─────────────────────────────────────────────────┤
│  + Nieuwe vrijwilliger                          │
├─────────────────────────────────────────────────┤
│  Jan         06-12345678    Mat       [🗑️]      │
│  Piet        06-87654321    Weging    [🗑️]      │
│  Marie       06-11223344    Spreker   [🗑️]      │
└─────────────────────────────────────────────────┘
```

**Toevoegen:** Inline formulier (voornaam, telefoon, functie dropdown)

### Koppeling met Device Toegang

Bij device toegang aanmaken/bewerken:
1. Dropdown "Selecteer vrijwilliger" (gefilterd op rol)
2. Bij selectie: naam wordt automatisch ingevuld
3. Knoppen per toegang:
   - **📋 URL** - kopieer link naar klembord (bestaand)
   - **📋 PIN** - kopieer pincode naar klembord (bestaand)
   - **WhatsApp** - opent `https://wa.me/{nummer}?text={bericht}` (nieuw, alleen als telefoon bekend)

### WhatsApp Bericht

Template (automatisch gegenereerd):
```
Hoi {voornaam}! Hier is je link voor {rol} op {toernooi_naam}:
{url}
PIN: {pincode}
```

---

## Device Binding voor PWA's

Alle standalone PWA's (Weging, Mat, Spreker, Dojo) vereisen device binding:

### Flow
1. Organisator/Hoofdjury maakt toegang aan (Instellingen → Organisatie)
2. **Optioneel:** Selecteer vrijwilliger uit database → naam + WhatsApp link
3. Vrijwilliger ontvangt URL + PIN (via WhatsApp of handmatig)
4. Eerste keer: opent URL → voert PIN in → device wordt gebonden
5. Daarna: device wordt herkend → direct naar interface

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
1. **Inschrijving & Portaal** - Hoe komen judoka's in het systeem?
2. **Device Toegangen** - URLs + PINs beheren
3. **Snelkoppelingen** - Pagina Builder + Noodplan
4. **Online Betalingen** - Mollie configuratie
5. **Bloktijden** - Weeg- en starttijden (stappen van 15 min)

---

## Budoschool Portaal Instellingen

### Portaal modus (Instellingen → Organisatie)

| Modus | Nieuwe judoka's | Mutaties/wijzigen | Bekijken |
|-------|-----------------|-------------------|----------|
| **UIT** | ❌ | ❌ | ✅ |
| **Alleen mutaties** | ❌ | ✅ | ✅ |
| **Volledig** | ✅ | ✅ | ✅ |

### Wanneer welke modus?

| Modus | Gebruik |
|-------|---------|
| **UIT** | Organisator beheert alles (CSV import of handmatig) |
| **Alleen mutaties** | Inschrijving extern, clubs kunnen gewicht/band/etc wijzigen via portaal |
| **Volledig** | Clubs schrijven zelf in én kunnen wijzigen |

### UI Flow (Instellingen → Organisatie)

```
┌─────────────────────────────────────────────────────────┐
│ Inschrijving & Portaal                                  │
├─────────────────────────────────────────────────────────┤
│ Portaal modus:  [Volledig           ▼]                  │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ ☐ Betalingen via Mollie                             │ │
│ │   (alleen zichtbaar bij "Volledig")                 │ │
│ └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Combinatie met Mollie

| Portaal | Mollie checkbox | Scenario |
|---------|-----------------|----------|
| UIT | (verborgen) | Organisator doet alles zelf |
| Alleen mutaties | (verborgen) | Extern inschrijven, mutaties via portaal |
| Volledig | ☐ uit | Inschrijven via portaal, betaling extern (contant/factuur) |
| Volledig | ☑ aan | Volledig via ons systeem (portaal + Mollie) |

> **Let op:** Mollie checkbox alleen zichtbaar bij "Volledig" modus.

### Altijd beschikbaar (passief/read-only)

- **Budoschool portaal**: Overzicht ingeschreven judoka's (alleen lezen)
- **Publieke site**: Overzicht deelnemers per categorie

### Handmatige invoer (organisator)

Organisator kan ALTIJD zelf judoka's toevoegen via:
- **Judoka lijst** → "Judoka toevoegen" knop (nieuw)
- **CSV/Excel import** (bestaand)

### Database veld

**toernooien tabel:**
```
portaal_modus    ENUM('uit', 'mutaties', 'volledig') DEFAULT 'uit'
```

> **Mollie velden:** Zie `BETALINGEN.md` voor `betaling_actief` etc.

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

## Coachkaarten

Coaches krijgen toegangskaarten voor de dojo. Het aantal wordt in twee fasen bepaald:

### Fase 1: Tijdens Inschrijving

**Altijd 1 coachkaart per budoschool** - ongeacht aantal judoka's.

- Bij eerste judoka inschrijving → 1 coachkaart aangemaakt
- Oude/overtollige coachkaarten worden automatisch verwijderd
- Dit voorkomt verwarring en zorgt dat clubs niet te veel kaarten krijgen

### Fase 2: Na Einde Voorbereiding

Organisator klikt **"Genereer Coachkaarten"** na blokverdeling. Dan wordt berekend:

```
Formule: ceil(max_judokas_in_grootste_blok / judokas_per_coach)

Voorbeeld: Club met 11 judoka's in grootste blok, 5 per coach
→ ceil(11 / 5) = 3 coachkaarten

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

### Waarom per blok?

Een coach hoeft alleen aanwezig te zijn wanneer zijn judoka's wedstrijden hebben. Als een club 15 judoka's heeft verdeeld over 3 blokken (8, 4, 3), dan zijn er maximaal 8 judoka's tegelijk actief → 2 coachkaarten nodig.

### Implementatie

**Model:** `app/Models/Club.php`
```php
/**
 * @param bool $forceCalculate - true = bereken op basis van blokken (na voorbereiding)
 *                               false = altijd 1 (tijdens inschrijving)
 */
public function berekenAantalCoachKaarten(Toernooi $toernooi, bool $forceCalculate = false): int
{
    $judokas = $this->judokas()->where('toernooi_id', $toernooi->id)->with('poules.blok')->get();

    if ($judokas->isEmpty()) {
        return 0;
    }

    // During inschrijving: always 1 card
    if (!$forceCalculate) {
        return 1;
    }

    // After voorbereiding: calculate based on largest block
    $perCoach = $toernooi->judokas_per_coach ?? 5;
    $judokasPerBlok = [];
    foreach ($judokas as $judoka) {
        foreach ($judoka->poules as $poule) {
            if ($poule->blok_id) {
                $judokasPerBlok[$poule->blok_id] = ($judokasPerBlok[$poule->blok_id] ?? 0) + 1;
            }
        }
    }

    if (empty($judokasPerBlok)) {
        return 1; // Fallback if no blokken assigned
    }

    return max(1, (int) ceil(max($judokasPerBlok) / $perCoach));
}
```

### Genereren coachkaarten

**Controller:** `CoachKaartController@genereer`

**Wanneer:** Organisator klikt na blokverdeling op "Genereer Coachkaarten"

**Wat gebeurt er:**
1. Bereken per club het benodigde aantal op basis van grootste blok (`forceCalculate=true`)
2. Maak ontbrekende kaarten aan
3. Verwijder overtollige (niet-gescande) kaarten
4. Markeer voorbereiding als afgerond (`voorbereiding_klaar_op = now()`)

```php
foreach ($clubs as $club) {
    // forceCalculate=true: calculate based on largest block
    $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi, true);
    // ... create/delete cards
}
$toernooi->update(['voorbereiding_klaar_op' => now()]);
```

### Coachkaart activatie & device binding

**Vereisten voor geldige coachkaart:**
1. Geactiveerd op een device (device binding)
2. Pasfoto geüpload
3. Naam ingevuld

**QR-code alleen zichtbaar op gebonden device** - voorkomt screenshot-deling.

### Time-based QR Codes (Screenshot Beveiliging)

Coachkaart QR-codes zijn **tijdgebonden** om screenshot-fraude te voorkomen:

| Setting | Waarde | Beschrijving |
|---------|--------|--------------|
| `QR_VALID_MINUTES` | 5 min | Maximale leeftijd voor geldige scan |
| `QR_REFRESH_MINUTES` | 4 min | QR ververst automatisch na 4 minuten |

**Werking:**
1. QR-code bevat URL met timestamp (`t`) en HMAC signature (`s`)
2. Client-side JavaScript genereert elke 4 minuten nieuwe QR
3. Bij scan: server valideert signature en controleert leeftijd (max 5 min)
4. Verlopen QR → oranje "QR-CODE VERLOPEN" melding

**URL formaat:**
```
/coach-kaart/{qrCode}/scan?t={timestamp}&s={signature}
```

**Signature generatie (beide client + server):**
```
HMAC-SHA256(qrCode + '|' + timestamp, APP_KEY).substring(0, 16)
```

**Timer weergave:**
- Laatste 60 seconden: toont "Ververst over Xs" in oranje
- Na 4 minuten: QR wordt automatisch ververst
- Bij tab-switch: QR wordt direct ververst (visibilitychange event)

**Bestanden:**
- `CoachKaart::generateScanSignature()` - Server-side signature
- `CoachKaart::validateScanToken()` - Server-side validatie
- `show.blade.php` - Client-side QR generatie met Web Crypto API
- `scan-result.blade.php` - Toont "verlopen" melding bij `$tokenExpired`

### Coachkaart overdracht

Coaches kunnen worden afgewisseld tijdens het toernooi (bijv. ochtend/middag). Een coachkaart kan worden overgedragen aan een andere coach.

**Flow:**

```
Coach 1 (ochtend)                    Coach 2 (middag)
─────────────────                    ─────────────────
1. Opent link
2. Activeert: naam + foto
3. QR zichtbaar ✓
                                     4. Opent dezelfde link (of scant QR)
                                     5. Ziet: "Kaart overnemen van [Coach 1]?"
                                     6. Klikt "Overnemen"
                                     7. Vult in: eigen naam + foto
                                     8. QR zichtbaar ✓
9. Opent kaart →
   Ziet: "Overgedragen aan [Coach 2]"
   + foto van Coach 2
   🔒 QR niet meer zichtbaar
```

**Technisch:**
- Per coachkaart: 1 actieve binding (naam, foto, device)
- Bij overdracht: oude binding vervalt, nieuwe wordt actief
- Oude foto wordt verwijderd uit storage

**View na overdracht (voor vorige coach):**

```
┌─────────────────────────────────┐
│  🔒 Kaart overgedragen          │
│                                 │
│  Huidige coach:                 │
│  ┌─────────┐                    │
│  │  foto   │  [Naam Coach 2]    │
│  │         │  Sinds [tijdstip]  │
│  └─────────┘                    │
│                                 │
│  Jouw toegang is beëindigd.     │
└─────────────────────────────────┘
```

**Waarom foto tonen aan vorige coach:**
- Transparant: coach ziet aan wie is overgedragen
- Veiligheid: bij controle ziet vrijwilliger dat dit niet de actieve coach is

**Wisselgeschiedenis (dojo scanner):**

Bij scannen toont de dojo scanner niet alleen de huidige coach, maar ook alle wisselingen:

```
┌─────────────────────────────────┐
│  ✓ GELDIGE COACH                │
│                                 │
│  ┌─────────┐  Piet Jansen       │
│  │  foto   │  Club: Judo Hoorn  │
│  └─────────┘                    │
│                                 │
│  Wisselgeschiedenis:            │
│  ├─ 14:32 Piet Jansen ← huidig  │
│  └─ 09:15 Jan de Vries          │
└─────────────────────────────────┘
```

**Database:** `coach_kaart_wisselingen` tabel
```sql
- id
- coach_kaart_id (FK)
- naam
- foto (path, wordt NIET verwijderd)
- device_info
- geactiveerd_op
- overgedragen_op (NULL = huidige coach)
```

**Bestanden:**
- `CoachKaartController@show` - Toont kaart of "overgedragen" view
- `CoachKaartController@activeer` - Activatie/overdracht flow
- `CoachKaartController@scan` - Toont wisselgeschiedenis
- `resources/views/pages/coach-kaart/show.blade.php` - Kaart weergave
- `resources/views/pages/coach-kaart/activeer.blade.php` - Activatie formulier
- `resources/views/pages/coach-kaart/scan-result.blade.php` - Dojo scanner resultaat

### Coach In/Uitcheck Systeem (optioneel)

**Doel:** Voorkomen dat coachkaart wordt overgedragen terwijl coach nog in de dojo is.

**Instelling:** `coach_incheck_actief` in toernooi instellingen (default: false)

#### Flow bij ingeschakeld

**Dojo Scanner - In/Uitcheck:**
```
┌─────────────────────────────────┐
│  ✓ GELDIGE COACH                │
│                                 │
│  ┌─────────┐  Piet Jansen       │
│  │  foto   │  Club: Judo Hoorn  │
│  └─────────┘                    │
│                                 │
│  Status: ⬚ Niet ingecheckt      │  ← of ✅ Ingecheckt sinds 09:15
│                                 │
│  ┌─────────────────────────────┐│
│  │      CHECK IN               ││  ← Groene knop
│  └─────────────────────────────┘│
│  (of "CHECK UIT" als ingecheckt)│
└─────────────────────────────────┘
```

**Portal - Overdracht GEBLOKKEERD als coach ingecheckt:**

Clubs kunnen NIET zelf bepalen of overdracht mogelijk is. Dit voorkomt dat meerdere coaches op 1 QR-code "binnen gesmokkeld" worden.

```
┌─────────────────────────────────┐
│  🔒 OVERDRACHT NIET MOGELIJK    │
│                                 │
│  Huidige coach [Naam] is nog    │
│  ingecheckt in de dojo.         │
│                                 │
│  De coach moet eerst UIT-       │
│  checken bij de dojo scanner    │
│  voordat de kaart kan worden    │
│  overgedragen.                  │
│                                 │
│  [Begrepen]                     │
└─────────────────────────────────┘
```

**Coachkaart view - Instructie voor huidige coach:**

Als coach ingecheckt is en kaart bekijkt:
```
┌─────────────────────────────────┐
│  ℹ️ OVERDRACHT                  │
│                                 │
│  Wilt u deze kaart overdragen   │
│  aan een andere coach?          │
│                                 │
│  Ga naar de dojo scanner en     │
│  check uit. Daarna kan de       │
│  nieuwe coach de kaart          │
│  overnemen.                     │
└─────────────────────────────────┘
```

**Coachkaart view - Instructie voor nieuwe coach:**

Als nieuwe coach de link opent maar huidige coach nog ingecheckt:
```
┌─────────────────────────────────┐
│  🔒 KAART NOG IN GEBRUIK        │
│                                 │
│  [Naam huidige coach] is nog    │
│  ingecheckt in de dojo.         │
│                                 │
│  Vraag de huidige coach om      │
│  uit te checken bij de dojo     │
│  scanner. Daarna kunt u de      │
│  kaart overnemen.               │
└─────────────────────────────────┘
```

#### Database

**coach_kaarten tabel (nieuw veld):**
```sql
ingecheckt_op    TIMESTAMP NULL    -- NULL = niet ingecheckt
```

**toernooien tabel (nieuw veld):**
```sql
coach_incheck_actief    BOOLEAN DEFAULT FALSE
```

**coach_checkins tabel (history):**
```sql
- id
- coach_kaart_id (FK)
- toernooi_id (FK)
- naam              -- Naam coach op moment van actie
- club_naam         -- Club naam voor snelle weergave
- foto              -- Foto path (snapshot)
- actie             -- 'in', 'uit', 'uit_geforceerd'
- geforceerd_door   -- NULL of 'hoofdjury'
- created_at        -- Tijdstip van actie
```

#### Check-in History Overzichten

**Dojo Scanner - 2 Tabs:**

De dojo scanner krijgt 2 tabs: Scanner en Overzicht.

```
┌─────────────────────────────────────────────────┐
│  [Scanner]  [Overzicht]                         │
└─────────────────────────────────────────────────┘
```

**Tab 1: Scanner** (bestaand)
- QR scanner + check-in/uit knoppen

**Tab 2: Overzicht**

Simpel en overzichtelijk:
1. Bovenaan: zoek budoschool
2. Bij selectie: lijst kaarten met naam + in/uit status
3. Klik op kaart: toont check-in + overdracht geschiedenis

```
┌─────────────────────────────────────────────────┐
│  [Scanner]  [Overzicht]                         │
├─────────────────────────────────────────────────┤
│  🔍 Zoek budoschool...                          │
│  ┌─────────────────────────────────────────────┐│
│  │ Judo Hoorn                                  ││
│  │ Judo Alkmaar                                ││
│  │ Judo Den Helder                             ││
│  └─────────────────────────────────────────────┘│
└─────────────────────────────────────────────────┘
```

**Na selectie budoschool:**

```
┌─────────────────────────────────────────────────┐
│  [Scanner]  [Overzicht]                         │
├─────────────────────────────────────────────────┤
│  ← JUDO HOORN                       3 kaarten   │
├─────────────────────────────────────────────────┤
│  Kaart 1: Piet Jansen            ✅ IN (14:32)  │
│  Kaart 2: Jan de Vries           🚪 UIT (12:30) │
│  Kaart 3: (niet geactiveerd)     ⬚ --           │
└─────────────────────────────────────────────────┘
```

**Klik op kaart → Detail met geschiedenis:**

```
┌─────────────────────────────────────────────────┐
│  ← Kaart 1 - Judo Hoorn                         │
├─────────────────────────────────────────────────┤
│  Huidige coach: Piet Jansen                     │
│  Status: ✅ Ingecheckt sinds 14:32              │
├─────────────────────────────────────────────────┤
│  CHECK-IN GESCHIEDENIS                          │
│  14:32  ✅ IN   Piet Jansen                     │
│  12:30  🚪 UIT  Jan de Vries                    │
│  09:00  ✅ IN   Jan de Vries                    │
├─────────────────────────────────────────────────┤
│  OVERDRACHT GESCHIEDENIS                        │
│  14:30  Jan de Vries → Piet Jansen              │
│  08:45  (eerste activatie) Jan de Vries         │
└─────────────────────────────────────────────────┘
```

**Na scan QR-code:**
- Tab wisselt automatisch naar Overzicht
- Budoschool van gescande coach is geselecteerd

**Portal - Coachkaarten tab:**

Uitgebreide weergave met echte kaarten en foto's:

```
┌─────────────────────────────────────────────────┐
│  COACH KAARTEN                      3 kaarten   │
├─────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────┐   │
│  │ ┌──────┐  Kaart 1                        │   │
│  │ │      │  Piet Jansen                    │   │
│  │ │ foto │  ✅ Ingecheckt sinds 14:32      │   │
│  │ │      │                                 │   │
│  │ └──────┘  [Bekijk geschiedenis]          │   │
│  └──────────────────────────────────────────┘   │
│                                                 │
│  ┌──────────────────────────────────────────┐   │
│  │ ┌──────┐  Kaart 2                        │   │
│  │ │      │  Jan de Vries                   │   │
│  │ │ foto │  🚪 Vertrokken (09:00 → 12:30)  │   │
│  │ │      │                                 │   │
│  │ └──────┘  [Bekijk geschiedenis]          │   │
│  └──────────────────────────────────────────┘   │
│                                                 │
│  ┌──────────────────────────────────────────┐   │
│  │ ┌──────┐  Kaart 3                        │   │
│  │ │  ?   │  Niet geactiveerd               │   │
│  │ │      │  ⬚ Nog niet gebruikt            │   │
│  │ └──────┘  [Activeer]                     │   │
│  └──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

**Klik "Bekijk geschiedenis" → Alle coaches met foto's:**

Bij 3x overdracht zie je 3 kaartjes met foto + in/uit tijden:

```
┌─────────────────────────────────────────────────┐
│  Kaart 1 - Geschiedenis              [Sluiten] │
├─────────────────────────────────────────────────┤
│  ┌──────┐  Piet Jansen (HUIDIG)                 │
│  │ foto │  ✅ IN: 14:32                         │
│  └──────┘                                       │
├─────────────────────────────────────────────────┤
│  ┌──────┐  Jan de Vries                         │
│  │ foto │  IN: 09:00 → UIT: 12:30               │
│  └──────┘  Overgedragen 14:30                   │
├─────────────────────────────────────────────────┤
│  ┌──────┐  Ahmed Hassan                         │
│  │ foto │  IN: 08:00 → UIT: 08:45               │
│  └──────┘  Overgedragen 09:00                   │
└─────────────────────────────────────────────────┘
```

**Transparantie:** Clubs zien exact wie wanneer in/uit is gegaan, alle coaches met foto.

#### Overrule door Hoofdjury (Organisator Portal)

**Probleem:** Coach vergeet uit te checken, nieuwe coach wil kaart overnemen.

**Oplossing:** Hoofdjury kan uitcheck forceren via de **Organisator Portal** (niet via dojo scanner PWA).

**Let op:** Deze functie is NIET zichtbaar in:
- Dojo Scanner (PWA voor vrijwilligers)
- Club Portal (voor budoscholen)

**Organisator Portal - Coach Kaarten beheer:**
```
┌─────────────────────────────────────────────────┐
│  COACH KAARTEN BEHEER                           │
├─────────────────────────────────────────────────┤
│  🔍 Zoek club of coach...                       │
├─────────────────────────────────────────────────┤
│  ⚠️ GEBLOKKEERDE OVERDRACHTEN (1)               │
│  ┌──────────────────────────────────────────┐   │
│  │ Judo Hoorn - Kaart 1                     │   │
│  │ Jan de Vries nog ingecheckt sinds 09:00  │   │
│  │ Nieuwe coach wacht op overdracht         │   │
│  │                                          │   │
│  │ [🔓 Forceer uitcheck]                    │   │
│  └──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

**Na klik "Forceer uitcheck":**
- Bevestiging: "Weet u zeker dat u Jan de Vries wilt uitchecken?"
- Coach wordt automatisch uitgecheckt
- Wordt gelogd: "Geforceerd door hoofdjury om [tijd]"
- Kaart kan nu worden overgedragen door nieuwe coach

**Database logging:**
```sql
coach_checkins:
- actie: 'uit_geforceerd'
- geforceerd_door: 'hoofdjury'
```

#### Implementatie

**Routes:**
- `POST /coach-kaart/{qrCode}/checkin` - Check coach in
- `POST /coach-kaart/{qrCode}/checkout` - Check coach uit
- `POST /coach-kaart/{qrCode}/forceer-checkout` - Geforceerde uitcheck (hoofdjury pincode vereist)
- `GET /dojo/{toernooi}/overzicht` - Overzicht alle clubs + check-in status (JSON)
- `GET /dojo/{toernooi}/overzicht/{club}` - Detail voor 1 club (JSON)
- `GET /coach-kaart/{qrCode}/geschiedenis` - Alle coaches met foto's + in/uit tijden

**Model:** `CoachCheckin`
```php
- belongsTo CoachKaart
- belongsTo Toernooi
- scopeVandaag() // Filter op vandaag
- scopeVoorClub($clubId) // Filter op club
```

**Controller:** `DojoController`
```php
public function checkin(CoachKaart $coachKaart) {
    $coachKaart->update(['ingecheckt_op' => now()]);
    return back()->with('success', 'Coach ingecheckt');
}

public function checkout(CoachKaart $coachKaart) {
    $coachKaart->update(['ingecheckt_op' => null]);
    return back()->with('success', 'Coach uitgecheckt');
}
```

**Validatie in CoachKaartController@activeer (overdracht):**
```php
if ($toernooi->coach_incheck_actief && $coachKaart->ingecheckt_op) {
    // HARD BLOKKEREN - geen overdracht mogelijk
    // Huidige coach moet eerst uitchecken bij dojo scanner
    return back()->with('error', 'Overdracht niet mogelijk. Huidige coach moet eerst uitchecken bij de dojo scanner.');
}
```

**View logica (show.blade.php):**
```php
@if($toernooi->coach_incheck_actief && $coachKaart->ingecheckt_op)
    @if($isHuidigeCoach)
        // Toon: "Ga naar dojo scanner om uit te checken"
    @else
        // Toon: "Kaart nog in gebruik, vraag coach om uit te checken"
    @endif
@endif
```

---

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

> **Zie:** `ELIMINATIE/README.md` → "B-groep op Aparte Mat"

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
  - **Mat nummer** (zodra toegewezen)
- **QR code**: Voor scannen bij weging
- **Download/Delen**: Knoppen voor opslaan en delen

### Vereisten
1. Judoka moet in poule zitten → blok wordt getoond
2. Poule moet mat hebben → mat wordt getoond

**Belangrijk:** Weegkaarten zijn dynamisch en tonen altijd actuele info.

---

## Publieke PWA (Toeschouwers)

**Route:** `/publiek/{slug}`
**View:** `resources/views/pages/publiek/index.blade.php`
**Controller:** `PubliekController`

### Toegang
- Openbaar, geen authenticatie nodig
- PWA installeerbaar op homescreen

### Tabs

| Tab | Beschikbaarheid | Inhoud |
|-----|-----------------|--------|
| **Info** | Altijd | Toernooi info, tijdschema, QR-code |
| **Deelnemers** | Altijd | Per leeftijd/gewicht, ster voor favoriet |
| **Favorieten** | Altijd | Geselecteerde judoka's + hun poules |
| **Live Matten** | Wedstrijddag | Per mat wie speelt/klaar maakt |
| **Uitslagen** | Na afloop | Eindstanden per poule |

### Deelnemers Tab - Categorie Weergave

De deelnemers worden gegroepeerd op basis van de **toernooi-instellingen** (`$toernooi->gewichtsklassen`), NIET op basis van het `gewichtsklasse` veld in de judoka database.

**Logica per categorie:**

| `max_kg_verschil` | Type | Weergave |
|-------------------|------|----------|
| `0` (of leeg) | **Vaste klassen** | Buttons per gewichtsklasse (-24kg, -27kg, etc.) |
| `> 0` | **Dynamisch** | Alle judoka's in één lijst gesorteerd op leeftijd + gewicht |

**Controller bepaalt:**
1. Lees `$toernooi->gewichtsklassen` (categorie-instellingen)
2. Per leeftijdscategorie: check `max_kg_verschil`
3. Als `max_kg_verschil > 0` → dynamische indeling → geen gewichtsklasse-groepering
4. Als `max_kg_verschil == 0` → vaste klassen → groepeer op `gewichten` array uit config

**View toont:**
- **Dynamische categorie**: Alle judoka's direct zichtbaar, gesorteerd op leeftijd + gewicht
- **Vaste categorie**: Knoppen per gewichtsklasse (uit config), klik voor judoka lijst

**Voorbeeld configuratie:**
```php
// Toernooi instelling voor "jeugd" categorie
'jeugd' => [
    'label' => 'Jeugd',
    'max_leeftijd' => 11,
    'max_kg_verschil' => 3,  // > 0 = dynamisch
    'gewichten' => [],        // Leeg bij dynamisch
]

// Toernooi instelling voor "pupillen" categorie
'pupillen' => [
    'label' => 'Pupillen',
    'max_leeftijd' => 9,
    'max_kg_verschil' => 0,   // 0 = vaste klassen
    'gewichten' => ['-24', '-27', '-30', '+30'],
]
```

### Live Matten Tab - Groen/Geel Weergave

> **Uitgebreide documentatie:** Zie `MAT-WEDSTRIJD-SELECTIE.md` voor volledige technische details.

Per mat worden getoond:
1. **Groen (speelt nu)**: Wedstrijd met beide judoka's
2. **Geel (klaar maken)**: Volgende wedstrijd met beide judoka's

```
┌─────────────────────────────────┐
│ MAT 1                    [LIVE] │
│ Poule #5 - Jeugd -24            │
├─────────────────────────────────┤
│ 🥋 SPEELT NU                    │
│ Jan (wit) vs Piet (blauw)       │
│ Judo Hoorn vs Judo Alkmaar      │
├─────────────────────────────────┤
│ ⏳ KLAAR MAKEN                  │
│ Karel (wit) vs Ahmed (blauw)    │
│ Judo Enkhuizen vs Judo Den Helder│
└─────────────────────────────────┘
```

**Data:** Komt van `actieve_wedstrijd_id` (groen) en `volgende_wedstrijd_id` (geel) op de **mat** (niet poule).

**Belangrijk:** Er is maar 1 groen en 1 geel per mat, ongeacht het aantal poules op die mat.

### Live Matten - Weergavemodi (Overzicht/Detail)

Twee weergavemodi voor de Live Matten tab:

| Modus | Gebruik | Layout |
|-------|---------|--------|
| **Overzicht** (standaard) | LCD/PC scherm aan de kant, alle matten in zicht | Desktop: grid 2 kolommen, Mobiel: onder elkaar |
| **Detail** | Bij de mat zelf, 1 mat groot | Mat vult volledige breedte, grotere tekst |

**Wisselen tussen modi:**
- **Vergroten**: Klik op vierkantje-icoon (expand) rechtsboven in mat header → detail modus
- **Verkleinen**: Klik op dubbel-vierkantje-icoon (collapse) rechtsboven → terug naar overzicht

**Gedrag:**
- Desktop overzicht: `grid-cols-2` (2 rijen als >2 matten, alles zichtbaar)
- Mobiel/PWA overzicht: `grid-cols-1` (onder elkaar)
- Detail: geselecteerde mat fullwidth, rest verborgen
- State (`selectedMatId`) in Alpine.js, `null` = overzicht
- Bij Reverb updates blijft geselecteerde mat behouden

### Favorieten Tab - Groen/Geel Weergave

In de poule van je favoriet worden groen/geel spelers **bovenaan** getoond:

```
┌─────────────────────────────────┐
│ P#5 Jeugd -24    Mat 1 | Blok 1 │
├─────────────────────────────────┤
│ 🥋 Jan (speelt nu)    8WP 34JP  │  ← Groen, altijd bovenaan
│ ⏳ Karel (klaar maken) 6WP 25JP │  ← Geel, daarna
├─────────────────────────────────┤
│ 1. Piet ★              4WP 12JP │  ← Je favoriet
│ 2. Ahmed               2WP  5JP │
│ 3. ...                          │
└─────────────────────────────────┘
```

**Alerts:**
- Groene banner: "🥋 NU! [Naam] is aan het vechten!"
- Gele banner: "⚡ Maak je klaar! [Naam] is bijna aan de beurt"

### Real-time Updates via Reverb

De publiek app ontvangt real-time updates via WebSockets (Laravel Reverb):

| Event | Actie |
|-------|-------|
| `mat-score-update` | Herlaadt matten + favorieten data |
| `mat-beurt-update` | Herlaadt matten + favorieten data |
| `mat-poule-klaar` | Herlaadt matten + favorieten data |

> **Zie:** `CHAT.md` voor volledige Reverb documentatie

### Geen polling meer
Polling is volledig verwijderd (feb 2026). Reverb WebSocket events zijn de enige
bron van updates. Handmatige refresh-knop beschikbaar als fallback.

### Puntenberekening Favorieten
WP/JP worden **live berekend** uit wedstrijden in `PubliekController@favorieten`:
- WP: win=2, gelijk=1, verlies=0
- JP: som van scores per judoka

**Let op:** NIET uit `poule_judoka.punten` pivot (die gebruikt een andere formule).

### Bestanden
- `PubliekController@index` - Hoofd view
- `PubliekController@favorieten` - AJAX endpoint voor favorieten poules (berekent WP/JP)
- `resources/views/pages/publiek/index.blade.php` - Alpine.js SPA

---

## Organisator Mobiel (Responsive Dashboard)

> **Concept:** Organisator loopt door de zaal en lost problemen op via smartphone. Gerealiseerd via responsive `WedstrijddagMobielController` + `toernooi/mobiel.blade.php`.

### Wat is het?

Geen aparte PWA — het bestaande dashboard met een **mobiele modus** die quick-action functies toont op kleine schermen. De organisator is al ingelogd via email+wachtwoord.

### Quick-actions (wedstrijddag)

| Functie | Beschrijving |
|---------|--------------|
| **Judoka Zoeken** | Zoek op naam/club → gewicht zien/invullen → poule bekijken → overpoulen |
| **Judoka Toevoegen** | Aan bestaande poule toevoegen (last-minute, vergeten) |
| **Mat Voortgang** | Resterende wedstrijden per mat + per poule op die mat |
| **Chat** | Berichten naar mat/weging/dojo (bestaand chat systeem) |

### UX: Verwijzing naar volledige app

Op de mobiele view wordt prominent getoond:
> "Volledige voorbereiding? Open de app op tablet of PC voor alle functies."

Dit voorkomt verwarring — de smartphone is voor quick-fixes op de wedstrijddag, niet voor volledige toernooi voorbereiding.

### Buiten scope

- Volledige poule-indeling (te complex voor telefoon → laptop)
- Toernooi instellingen (eenmalig, doe je vooraf)
- Eliminatie bracket beheer (te complex voor klein scherm)
- Spreker interface (bestaat al als aparte PWA)

### Route & Bestanden

```
Route:  /{organisator}/toernooi/{toernooi}/wedstrijddag/mobiel
View:   pages/toernooi/mobiel.blade.php
API's:  wedstrijddag/mat-voortgang (GET), wedstrijddag/poules-api (GET)
        + hergebruik: judoka.zoek, weging.registreer, wedstrijddag.verplaats-judoka, wedstrijddag.nieuwe-judoka
```

### Authenticatie

Bestaande organisator login — geen device binding, geen aparte auth.

---

## Versie & Updates

- Versie in `config/toernooi.php` → `version`
- Service Worker in `public/sw.js` → `VERSION`
- **Beide verhogen bij release!**
- Forceer Update knop in instellingen modal
