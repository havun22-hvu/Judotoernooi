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
| **Ingevulde wedstrijdschema's** | Per blok - met scores |
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
| `resources/views/layouts/print.blade.php` | NIEUW (basis print layout) |
| `resources/views/pages/toernooi/edit.blade.php` | Toggle toevoegen |
| `routes/web.php` | Routes toevoegen |

---

## 5. Print CSS Strategie

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

---

## 6. Implementatie Volgorde

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

## 7. Notities

- Toegang alleen voor admin, organisator en hoofdjury
- Alle prints moeten werken zonder JavaScript
- Inktvriendelijk: wit achtergrond, zwarte tekst
- Elke poule op nieuwe pagina (page-break)
- Weegkaarten/coachkaarten: meerdere per A4 pagina
- Weeg/coachkaarten opties:
  - **Alle**: eind inschrijving, print alles in één keer
  - **Per club**: dropdown selectie
  - **1 specifieke**: bij calamiteiten (coach ziek, plotselinge wijziging)
- {blok?} parameter: optioneel, zonder = alle blokken
