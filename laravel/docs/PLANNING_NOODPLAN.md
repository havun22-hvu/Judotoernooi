# Noodplan - Implementatie Plan

> **Status:** Te implementeren
> **Doel:** Print-ready backup systeem voor wanneer techniek faalt tijdens toernooi

---

## Overzicht

Het noodplan biedt organisators de mogelijkheid om alle essentiële toernooigegevens te printen als backup voor technische problemen.

---

## 1. Routes

**Bestand:** `routes/web.php`

```php
// Noodplan routes (alleen admin/hoofdjury)
Route::middleware(['toernooi.rol:admin,hoofdjury'])->group(function () {
    Route::get('noodplan', [NoodplanController::class, 'index'])->name('toernooi.noodplan.index');
    Route::get('noodplan/poules', [NoodplanController::class, 'printPoules'])->name('toernooi.noodplan.poules');
    Route::get('noodplan/blokken', [NoodplanController::class, 'printBlokken'])->name('toernooi.noodplan.blokken');
    Route::get('noodplan/weeglijst', [NoodplanController::class, 'printWeeglijst'])->name('toernooi.noodplan.weeglijst');
    Route::get('noodplan/blok/{blok}/poules', [NoodplanController::class, 'printBlokPoules'])->name('toernooi.noodplan.blok-poules');
    Route::get('noodplan/blok/{blok}/wedstrijden', [NoodplanController::class, 'printBlokWedstrijden'])->name('toernooi.noodplan.blok-wedstrijden');
    Route::get('noodplan/leeg-schema/{aantal}', [NoodplanController::class, 'printLeegSchema'])->name('toernooi.noodplan.leeg-schema');
});
```

---

## 2. Print knop bij wedstrijdschema (organisator)

**Bestand:** `resources/views/pages/mat/interface.blade.php`

- Conditie: alleen tonen als rol = admin of hoofdjury (NIET mat)
- Print knop per poule wedstrijdschema
- Print huidige staat (ingevulde scores)

---

## 3. Lege wedstrijdschema templates

**Functionaliteit:**
- Templates voor 2, 3, 4, 5, 6, 7 judokas
- Lege cellen voor handmatig invullen
- Standaard wedstrijdvolgorde uit toernooi instellingen
- WP/JP kolommen leeg

---

## 4. Kritieke Bestanden

| Bestand                                       | Actie                     |
|-----------------------------------------------|---------------------------|
| `app/Http/Controllers/NoodplanController.php` | NIEUW                     |
| `resources/views/pages/noodplan/index.blade.php` | NIEUW                  |
| `resources/views/pages/noodplan/poules.blade.php` | NIEUW                 |
| `resources/views/pages/noodplan/blokken.blade.php` | NIEUW                |
| `resources/views/pages/noodplan/weeglijst.blade.php` | NIEUW              |
| `resources/views/pages/noodplan/blok-poules.blade.php` | NIEUW            |
| `resources/views/pages/noodplan/blok-wedstrijden.blade.php` | NIEUW       |
| `resources/views/pages/noodplan/leeg-schema.blade.php` | NIEUW            |
| `resources/views/pages/mat/interface.blade.php` | Print knop toevoegen   |
| `routes/web.php`                              | Routes toevoegen          |

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
}
```

---

## 6. Noodplan Index Layout

```
+--------------------------------------------------+
| NOODPLAN - [Toernooi Naam]                       |
| Momentopname: 14:32:15                           |
+--------------------------------------------------+
|                                                  |
| VOOR HET TOERNOOI                               |
| [Print Alle Poules]  [Print Weeglijst]          |
| [Print Blokverdeling]                           |
|                                                  |
| PER BLOK                                        |
| Blok 1: [Poules] [Wedstrijdschema's]            |
| Blok 2: [Poules] [Wedstrijdschema's]            |
| Blok 3: [Poules] [Wedstrijdschema's]            |
|                                                  |
| LEGE TEMPLATES                                  |
| [2 judoka's] [3] [4] [5] [6] [7 judoka's]       |
|                                                  |
+--------------------------------------------------+
```

---

## 7. Implementatie Volgorde

- [ ] 1. NoodplanController basis
- [ ] 2. Routes toevoegen
- [ ] 3. Print layout + CSS
- [ ] 4. Noodplan index pagina
- [ ] 5. Print views (poules, blokken, weeglijst)
- [ ] 6. Print views (blok-specifiek)
- [ ] 7. Lege templates (2-7 judokas)
- [ ] 8. Print knop mat interface

---

## 8. Notities

- Toegang alleen voor admin en hoofdjury
- Alle prints moeten werken zonder JavaScript
- Inktvriendelijk: wit achtergrond, zwarte tekst
- Elke poule/blok op nieuwe pagina (page-break)
