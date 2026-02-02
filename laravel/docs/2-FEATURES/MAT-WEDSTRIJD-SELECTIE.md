# Mat Wedstrijd Selectie (Groen/Geel Systeem)

## Probleem

Voorheen had elke **poule** een eigen `actieve_wedstrijd_id` (groen) en `huidige_wedstrijd_id` (geel). Dit werkt niet goed wanneer meerdere poules op dezelfde mat staan:

- 3 poules op 1 mat = 3 groene en 3 gele wedstrijden mogelijk
- Fysiek kan er maar 1 wedstrijd tegelijk op de mat
- Verwarring bij mat-jury en toeschouwers

## Oplossing: Mat-niveau selectie

Groen (speelt nu) en geel (klaar maken) worden opgeslagen op **mat niveau**, niet op poule niveau.

### Database

**Tabel: `matten`** (nieuwe velden)

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `actieve_wedstrijd_id` | bigint NULL | FK → wedstrijden (groene wedstrijd) |
| `volgende_wedstrijd_id` | bigint NULL | FK → wedstrijden (gele wedstrijd) |

**Tabel: `poules`** (velden VERWIJDEREN)

| Kolom | Status |
|-------|--------|
| `actieve_wedstrijd_id` | DEPRECATED → verwijderen |
| `huidige_wedstrijd_id` | DEPRECATED → verwijderen |

### Terminologie

| Kleur | Database veld | Betekenis | UI |
|-------|---------------|-----------|-----|
| **Groen** | `mat.actieve_wedstrijd_id` | Wedstrijd speelt NU op de mat | Groene achtergrond |
| **Geel** | `mat.volgende_wedstrijd_id` | Judoka's moeten klaar staan | Gele achtergrond |
| **Neutraal** | NULL | Geen selectie | Witte achtergrond |

---

## Interactie Logica

### Klik op wedstrijd

| Situatie | Actie | Resultaat |
|----------|-------|-----------|
| Geen groen, geen geel | Klik wedstrijd X | X wordt **groen** |
| Wel groen, geen geel | Klik wedstrijd Y | Y wordt **geel** |
| Wel groen, wel geel | Klik wedstrijd Z | Alert: "Deselecteer eerst geel" |

### Klik op groene wedstrijd (stoppen)

1. Vraag bevestiging: "Wedstrijd stoppen?"
2. Bij "OK":
   - Geel wordt groen (promoveren)
   - Geel wordt null
3. Bij "Annuleren": niets

### Klik op gele wedstrijd (deselecteren)

- Geel wordt null (direct, geen bevestiging)
- Groen blijft staan

### Wedstrijd afgerond (uitslag geregistreerd)

1. Groene wedstrijd wordt gemarkeerd als gespeeld
2. Geel wordt automatisch groen
3. Auto-fallback: eerste ongespeelde wedstrijd van actieve poules wordt geel

---

## Poule Verplaatsing / Toevoegen

### Poule verplaatst naar andere mat

| Veld | Gedrag |
|------|--------|
| **Groen** | Blijft staan tot wedstrijd klaar of gedeselecteerd |
| **Geel** | Wordt automatisch null (gereset) |

**Reden:** Gele wedstrijd is "klaar maken" - bij verplaatsing moeten judoka's opnieuw opgeroepen worden.

### Poule toegevoegd aan mat

- Geen automatische selectie
- Mat-jury klikt handmatig op gewenste wedstrijd

### Poule verwijderd van mat

- Als groene wedstrijd van deze poule was: groen wordt null
- Als gele wedstrijd van deze poule was: geel wordt null

---

## Controller Logica

### MatController@setHuidigeWedstrijd

```php
public function setHuidigeWedstrijd(Request $request, Mat $mat)
{
    $validated = $request->validate([
        'actieve_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        'volgende_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
    ]);

    // Valideer dat wedstrijden bij poules op deze mat horen
    if ($validated['actieve_wedstrijd_id']) {
        $wedstrijd = Wedstrijd::find($validated['actieve_wedstrijd_id']);
        if ($wedstrijd->poule->mat_id !== $mat->id) {
            return response()->json(['error' => 'Wedstrijd hoort niet bij deze mat'], 422);
        }
    }

    $mat->update([
        'actieve_wedstrijd_id' => $validated['actieve_wedstrijd_id'],
        'volgende_wedstrijd_id' => $validated['volgende_wedstrijd_id'],
    ]);

    return response()->json(['success' => true]);
}
```

### PouleController@verplaats

```php
public function verplaats(Request $request, Poule $poule)
{
    $nieuweMatId = $request->input('mat_id');
    $oudeMatId = $poule->mat_id;

    // Reset geel op oude mat als het een wedstrijd van deze poule was
    if ($oudeMatId) {
        $oudeMat = Mat::find($oudeMatId);
        if ($oudeMat->volgende_wedstrijd_id) {
            $geleWedstrijd = Wedstrijd::find($oudeMat->volgende_wedstrijd_id);
            if ($geleWedstrijd && $geleWedstrijd->poule_id === $poule->id) {
                $oudeMat->update(['volgende_wedstrijd_id' => null]);
            }
        }
        // Groen blijft staan - mat-jury moet handmatig stoppen
    }

    $poule->update(['mat_id' => $nieuweMatId]);

    return response()->json(['success' => true]);
}
```

---

## View Updates

### Mat Interface (_content.blade.php)

**Huidige code (poule niveau):**
```javascript
const isGroen = wedstrijd.id === poule.actieve_wedstrijd_id;
const isGeel = wedstrijd.id === poule.huidige_wedstrijd_id;
```

**Nieuwe code (mat niveau):**
```javascript
const isGroen = wedstrijd.id === this.mat.actieve_wedstrijd_id;
const isGeel = wedstrijd.id === this.mat.volgende_wedstrijd_id;
```

### Publiek PWA (Live Matten)

**Huidige code:**
```php
$groeneWedstrijd = $poule->actieveWedstrijd;
$geleWedstrijd = $poule->huidigeWedstrijd;
```

**Nieuwe code:**
```php
$groeneWedstrijd = $mat->actieveWedstrijd;
$geleWedstrijd = $mat->volgendeWedstrijd;
```

---

## Migratie Strategie

### Stap 1: Database migratie

```php
Schema::table('matten', function (Blueprint $table) {
    $table->foreignId('actieve_wedstrijd_id')->nullable()->constrained('wedstrijden')->nullOnDelete();
    $table->foreignId('volgende_wedstrijd_id')->nullable()->constrained('wedstrijden')->nullOnDelete();
});
```

### Stap 2: Data migratie (optioneel)

Als er actieve toernooien zijn met groen/geel ingesteld:
```php
// Migreer bestaande data van poules naar matten
foreach (Poule::whereNotNull('actieve_wedstrijd_id')->get() as $poule) {
    if ($poule->mat) {
        $poule->mat->update([
            'actieve_wedstrijd_id' => $poule->actieve_wedstrijd_id,
            'volgende_wedstrijd_id' => $poule->huidige_wedstrijd_id,
        ]);
    }
}
```

### Stap 3: Code updates

1. MatController - nieuwe endpoint of update bestaande
2. Mat model - relaties `actieveWedstrijd()` en `volgendeWedstrijd()`
3. Views - alle referenties naar `poule.actieve_wedstrijd_id` → `mat.actieve_wedstrijd_id`
4. PubliekController - groen/geel van mat halen

### Stap 4: Verwijder oude velden (later)

```php
Schema::table('poules', function (Blueprint $table) {
    $table->dropColumn(['actieve_wedstrijd_id', 'huidige_wedstrijd_id']);
});
```

---

## Impacted Files

| File | Wijziging |
|------|-----------|
| `database/migrations/xxx_add_wedstrijd_selectie_to_matten.php` | Nieuwe velden |
| `app/Models/Mat.php` | Nieuwe relaties |
| `app/Models/Poule.php` | Relaties verwijderen (later) |
| `app/Http/Controllers/MatController.php` | Nieuwe logica |
| `app/Http/Controllers/PouleController.php` | Reset geel bij verplaatsing |
| `app/Http/Controllers/PubliekController.php` | Groen/geel van mat |
| `resources/views/pages/mat/partials/_content.blade.php` | JS logica |
| `resources/views/pages/publiek/index.blade.php` | Display logica |

---

## Backwards Compatibility

Tijdens transitie:
1. **Lees** groen/geel van mat (nieuw)
2. **Fallback** naar poule als mat velden null zijn (tijdelijk)
3. **Schrijf** alleen naar mat velden

Na volledige migratie:
- Verwijder poule velden
- Verwijder fallback code

---

## Test Scenarios

### Basis functionaliteit

| Test | Verwacht resultaat |
|------|-------------------|
| Klik wedstrijd (geen groen) | Wordt groen |
| Klik andere wedstrijd (wel groen, geen geel) | Wordt geel |
| Klik groene wedstrijd + bevestig | Geel → groen, geel = null |
| Klik gele wedstrijd | Geel = null |

### Poule verplaatsing

| Test | Verwacht resultaat |
|------|-------------------|
| Verplaats poule met groene wedstrijd | Groen blijft op oude mat |
| Verplaats poule met gele wedstrijd | Geel wordt null op oude mat |
| Voeg nieuwe poule toe aan mat | Geen automatische selectie |

### Multi-poule scenario

| Test | Verwacht resultaat |
|------|-------------------|
| 3 poules op mat, klik wedstrijd poule A | Wordt groen |
| Klik wedstrijd poule B | Wordt geel |
| Poule B verplaatst | Geel wordt null, groen (poule A) blijft |
