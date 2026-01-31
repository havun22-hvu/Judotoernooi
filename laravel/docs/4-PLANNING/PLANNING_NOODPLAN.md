# Noodplan - Implementatie Plan

> **Status:** Geïmplementeerd (2024-12-17)
> **Doel:** Print-ready backup systeem voor wanneer techniek faalt tijdens toernooi

---

## Overzicht

Het noodplan biedt organisators de mogelijkheid om alle essentiële toernooigegevens te printen als backup voor technische problemen.

**Toegang:** Admin, Organisator, Hoofdjury

---

## 1. Functionaliteit

### VOOR HET TOERNOOI (Backup)

| Print | Beschrijving |
|-------|--------------|
| **Poules per blok** | Alle poules van 1 blok, of alle blokken tegelijk |
| **Weeglijst** | Alle judoka's met gewichtsklasse |
| **Zaaloverzicht** | Welke poule op welke mat in welk blok |
| **Weegkaarten** | Alle / per club / 1 specifieke judoka |
| **Coachkaarten** | Alle / per club / 1 specifieke coach |
| **Lege wedstrijdschema's** | Templates voor 2, 3, 4, 5, 6, 7 judoka's |
| **Instellingen samenvatting** | Weegtijden en starttijden per blok |
| **Contactlijst coaches** | Telefoonnummers voor noodgevallen |

### TIJDENS DE WEDSTRIJD (Live)

| Print | Beschrijving |
|-------|--------------|
| **Gecorrigeerde poules** | Na overpoulen - per blok of alle blokken |
| **Aangepast zaaloverzicht** | Na overpoulen/nieuwe matverdeling |
| **Ingevulde wedstrijdschema's** | Per blok - met scores (lijst-formaat) |
| **Ingevulde schema's (matrix)** | Matrix-formaat zoals mat interface, 1 per A4, met namen ingevuld |
| **Actief wedstrijdschema** | Huidige staat van lopende poule |

---

## 2. Routes

**Bestand:** `routes/web.php`

```php
// Noodplan routes (admin/organisator/hoofdjury)
Route::prefix('noodplan')->name('noodplan.')->group(function () {
    Route::get('/', [NoodplanController::class, 'index'])->name('index');

    // Voor het toernooi
    Route::get('/poules/{blok?}', [NoodplanController::class, 'printPoules'])->name('poules');
    Route::get('/weeglijst', [NoodplanController::class, 'printWeeglijst'])->name('weeglijst');
    Route::get('/zaaloverzicht', [NoodplanController::class, 'printZaaloverzicht'])->name('zaaloverzicht');
    Route::get('/weegkaarten', [NoodplanController::class, 'printWeegkaarten'])->name('weegkaarten');
    Route::get('/weegkaarten/club/{club}', [NoodplanController::class, 'printWeegkaartenClub'])->name('weegkaarten.club');
    Route::get('/weegkaarten/judoka/{judoka}', [NoodplanController::class, 'printWeegkaart'])->name('weegkaart');
    Route::get('/coachkaarten', [NoodplanController::class, 'printCoachkaarten'])->name('coachkaarten');
    Route::get('/coachkaarten/club/{club}', [NoodplanController::class, 'printCoachkaartenClub'])->name('coachkaarten.club');
    Route::get('/coachkaarten/coach/{coach}', [NoodplanController::class, 'printCoachkaart'])->name('coachkaart');
    Route::get('/leeg-schema/{aantal}', [NoodplanController::class, 'printLeegSchema'])->name('leeg-schema');
    Route::get('/instellingen', [NoodplanController::class, 'printInstellingen'])->name('instellingen');
    Route::get('/contactlijst', [NoodplanController::class, 'printContactlijst'])->name('contactlijst');

    // Tijdens wedstrijd
    Route::get('/wedstrijdschemas/{blok?}', [NoodplanController::class, 'printWedstrijdschemas'])->name('wedstrijdschemas');
    Route::get('/poule/{poule}/schema', [NoodplanController::class, 'printPouleSchema'])->name('poule-schema');
    Route::get('/ingevuld-schemas/{blok?}', [NoodplanController::class, 'printIngevuldSchemas'])->name('ingevuld-schemas');
});
```

---

## 3. Noodplan Index Layout

```
+----------------------------------------------------------+
| NOODPLAN - [Toernooi Naam]                               |
| Momentopname: 14:32:15                                   |
+----------------------------------------------------------+
|                                                          |
| VOOR HET TOERNOOI (backup)                              |
| ┌─────────────────────────────────────────────────────┐ |
| │ Poules:     [Blok 1] [Blok 2] [Blok 3] [Alle]      │ |
| │ Overzichten: [Weeglijst] [Zaaloverzicht]            │ |
| │ Kaarten:    [Weegkaarten ▼] [Coachkaarten ▼]       │ |
| │ Templates:  [2] [3] [4] [5] [6] [7] judoka's       │ |
| └─────────────────────────────────────────────────────┘ |
|                                                          |
| TIJDENS DE WEDSTRIJD (live)                             |
| ┌─────────────────────────────────────────────────────┐ |
| │ Gecorrigeerde poules: [Blok 1] [Blok 2] [Alle]     │ |
| │ Aangepast zaaloverzicht: [Print]                    │ |
| │ Ingevulde schema's: [Blok 1] [Blok 2] [Blok 3]     │ |
| └─────────────────────────────────────────────────────┘ |
|                                                          |
| ACTIEVE POULES (klik voor huidige staat)                |
| ┌─────────────────────────────────────────────────────┐ |
| │ Mat 1: Poule A - Mini's -26kg [Print]              │ |
| │ Mat 2: Poule B - A-pup -30kg [Print]               │ |
| │ Mat 3: Poule C - B-pup -34kg [Print]               │ |
| └─────────────────────────────────────────────────────┘ |
|                                                          |
+----------------------------------------------------------+
```

---

## 4. Kritieke Bestanden

| Bestand | Actie |
|---------|-------|
| `app/Http/Controllers/NoodplanController.php` | NIEUW |
| `resources/views/pages/noodplan/index.blade.php` | NIEUW |
| `resources/views/pages/noodplan/poules.blade.php` | NIEUW |
| `resources/views/pages/noodplan/weeglijst.blade.php` | NIEUW |
| `resources/views/pages/noodplan/zaaloverzicht.blade.php` | NIEUW |
| `resources/views/pages/noodplan/weegkaarten.blade.php` | NIEUW |
| `resources/views/pages/noodplan/coachkaarten.blade.php` | NIEUW |
| `resources/views/pages/noodplan/leeg-schema.blade.php` | NIEUW |
| `resources/views/pages/noodplan/instellingen.blade.php` | NIEUW |
| `resources/views/pages/noodplan/contactlijst.blade.php` | NIEUW |
| `resources/views/pages/noodplan/wedstrijdschemas.blade.php` | NIEUW |
| `resources/views/pages/noodplan/poule-schema.blade.php` | NIEUW |
| `resources/views/pages/noodplan/ingevuld-schema.blade.php` | NIEUW (matrix met namen) |
| `resources/views/layouts/print.blade.php` | NIEUW (basis print layout) |
| `resources/views/pages/toernooi/edit.blade.php` | Toggle toevoegen |
| `routes/web.php` | Routes toevoegen |

---

## 5. Lege Wedstrijdschema's - Print Specificaties

Het lege wedstrijdschema moet exact de online mat-interface nabootsen.

### Layout

| Kolom | Beschrijving |
|-------|--------------|
| **Nr** | Judoka nummer (1-7) |
| **Naam** | Leeg veld om naam+club in te vullen (220px) |
| **1-21** | Wedstrijdkolommen met W en J subkolommen |
| **WP** | Totaal wedstrijdpunten |
| **JP** | Totaal judopunten |
| **Plts** | Eindplaats |

### Orientatie

| Judoka's | Wedstrijden | Orientatie |
|----------|-------------|------------|
| 2 | 1 | Portrait |
| 3 | 3 | Portrait |
| 4 | 6 | Portrait |
| 5 | 10 | Portrait |
| 6 | 15 | Landscape |
| 7 | 21 | Landscape |

### Styling Details

- **Header**: Donkere achtergrond (#1f2937) met witte tekst
- **Actieve cellen**: Wit met dunne scheidingslijn tussen W en J (#ccc)
- **Inactieve cellen**: Zwart (#1f2937), geen scheidingslijn
- **Tussen wedstrijden**: Dikke zwarte lijn (2px)
- **WP/JP kolommen**: Grijze achtergrond (#f3f4f6), zwarte tekst
- **Plts kolom**: Gele achtergrond (#fef9c3), zwarte tekst

### Print Color Override

```css
@media print {
    /* Force kleuren printen */
    .schema-table, .schema-table * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Override layout default die alles zwart maakt */
    .header-row, .header-row th, .header-row th * {
        background: #1f2937 !important;
        color: white !important;
    }
}
```

### Legenda

```
W = Wedstrijdpunten (0 of 2)
J = Judopunten (Yuko=5, Waza-Ari=7, Ippon=10)
```

---

## 6. Ingevulde Schema's (Matrix) - Print Specificaties

Print van poules die op de mat staan, in matrix-formaat zoals de mat interface.

### Kenmerken

- **1 poule per A4 pagina** (page-break tussen poules)
- **Matrix layout** zoals leeg-schema, maar met namen ingevuld
- **Oriëntatie:** Portrait (≤5 judoka's) / Landscape (≥6 judoka's)
- **Scores optioneel:** als wedstrijden gespeeld zijn, toon W/J scores
- **Selectie checkboxes:** per poule aanvinken welke mee geprint moeten worden
- **Afwezige judoka's:** worden automatisch gefilterd (niet getoond)

### Selectie Toolbar

Bovenaan de pagina staat een toolbar (niet meegeprint):
- **Alles aan / Alles uit** - snel alle poules (de)selecteren
- **Teller** - "X van Y geselecteerd"
- **Print knop** - print alleen geselecteerde poules

Elke poule heeft een checkbox links van de titel. Uitgevinkte poules worden:
- 50% transparant getoond (opacity-50)
- Niet meegenomen in de print (display: none via CSS)

### Header per poule

```
┌─────────────────────────────────────────────────────────────────┐
│ Poule #12 - Mini's 7-8j 22-25kg          Mat 2 | Blok 1        │
└─────────────────────────────────────────────────────────────────┘
```

### Matrix

Zelfde layout als leeg-schema:
- Kolom 1: Nr (1, 2, 3...)
- Kolom 2: Naam + club (ingevuld!)
- Kolommen 3+: Wedstrijden (W | J) - actieve cellen wit, inactief zwart
- Laatste kolommen: WP, JP, Plts

### Route

```
GET /noodplan/ingevuld-schemas/{blok?}
```

- Zonder blok: alle blokken met poules die wedstrijden hebben
- Met blok: alleen dat blok

---

## 7. Print CSS Strategie (Algemeen)

```css
@media print {
    /* Verberg niet-printbare elementen */
    .no-print, nav, header, footer, .print-hide { display: none !important; }

    /* Reset achtergronden voor inkbesparing */
    * { background: white !important; color: black !important; }

    /* Pagina breaks */
    .page-break { page-break-after: always; }
    .no-break { page-break-inside: avoid; }

    /* Tabel borders zichtbaar */
    table, th, td { border: 1px solid black !important; }

    /* A4 marges */
    @page { margin: 1cm; }
}
```

**Let op:** `@stack('styles')` moet NA de layout styles komen zodat pagina-specifieke `@page` directives (zoals landscape) de defaults kunnen overriden.

---

## 7. Implementatie Volgorde

- [x] 1. NoodplanController basis
- [x] 2. Routes toevoegen (met middleware)
- [x] 3. Print layout (`layouts/print.blade.php`)
- [x] 4. Noodplan index pagina
- [x] 5. Toggle in instellingen (onder Pagina Builder)
- [x] 6. Print: Poules per blok
- [x] 7. Print: Weeglijst
- [x] 8. Print: Zaaloverzicht
- [x] 9. Print: Weegkaarten
- [x] 10. Print: Coachkaarten
- [x] 11. Print: Lege templates (2-7 judokas)
- [x] 12. Print: Instellingen samenvatting
- [x] 13. Print: Contactlijst coaches
- [x] 14. Print: Ingevulde wedstrijdschema's
- [x] 15. Print: Actieve poule (huidige staat)

---

## 8. Live Backup Sync (Offline Noodplan)

> **Status:** Geïmplementeerd (2026-01-30)

### Probleem
Bij internet uitval tijdens wedstrijddag kan de server niet bereikt worden en zijn de laatste uitslagen niet printbaar.

### Oplossing: Fetch Polling
Elke toernooi-pagina synchroniseert automatisch elke 30 seconden alle poule data naar localStorage.

### Werking
```
Toernooi pagina open → Fetch elke 30 sec
                              ↓
                       Server retourneert alle poule data
                              ↓
                       localStorage wordt bijgewerkt
                              ↓
                       Bij internet uitval: print vanuit localStorage
```

### Implementatie

**API Endpoint:**
- `GET /{organisator}/toernooi/{toernooi}/noodplan/sync-data` - JSON response

**localStorage keys:**
- `noodplan_{toernooi_id}_poules` - Alle poule data met uitslagen
- `noodplan_{toernooi_id}_laatste_sync` - Timestamp laatste update
- `noodplan_{toernooi_id}_count` - Aantal gespeelde wedstrijden

**JavaScript (in layouts/app.blade.php):**
- Start fetch polling automatisch op elke toernooi-pagina
- Sync elke 30 seconden
- Auto-restart na visibility change (slaapstand/tab switch)
- Status indicator rechtsonder: "Backup actief | X uitslagen | HH:MM"

**Noodplan pagina:**
- OFFLINE BACKUP sectie toont sync status
- "Print vanuit backup" knop genereert print-ready HTML uit localStorage
- Werkt ook als server offline is

### Gebruiker hoeft NIETS te doen
- Sync start automatisch bij openen toernooi pagina
- Backup loopt op achtergrond
- Auto-restart na slaapstand/tab switch

---

## 9. Enterprise Redundantie (Nieuw)

> **Status:** Planning
> **Documentatie:** Zie [REDUNDANTIE.md](../3-TECHNICAL/REDUNDANTIE.md) voor technische details

Voor grote toernooien is een uitgebreid noodplan beschikbaar met:

- **Hot Standby Server** - Tweede laptop als backup
- **Lokaal Mesh Netwerk** - Werkt zonder internet (Deco M4)
- **Real-time Replicatie** - Max 5 sec data verlies
- **Automatic Failover** - <10 sec recovery time
- **Health Dashboard** - Live status monitoring

### Gerelateerde Documentatie

| Document | Beschrijving |
|----------|--------------|
| [REDUNDANTIE.md](../3-TECHNICAL/REDUNDANTIE.md) | Technisch veiligheidsplan met architectuur |
| [NOODPLAN-HANDLEIDING.md](../2-FEATURES/NOODPLAN-HANDLEIDING.md) | Praktische gids voor organisatoren |

---

## 10. Notities

- Toegang alleen voor admin, organisator en hoofdjury
- Alle prints moeten werken zonder JavaScript
- Inktvriendelijk: wit achtergrond, zwarte tekst
- Elke poule op nieuwe pagina (page-break)
- Weegkaarten/coachkaarten: meerdere per A4 pagina
- Weeg/coachkaarten opties:
  - **Alle**: na "Verdeel over matten", print alles in één keer
  - **Per club**: dropdown selectie
  - **1 specifieke**: bij calamiteiten (coach ziek, plotselinge wijziging)
- **Let op:** Weegkaarten vereisen:
  1. "Valideer judoka's" (na einde inschrijving) → QR-codes aangemaakt
  2. Blokverdeling + "Verdeel over matten" (tab Blokken) → blokinfo beschikbaar
- {blok?} parameter: optioneel, zonder = alle blokken
