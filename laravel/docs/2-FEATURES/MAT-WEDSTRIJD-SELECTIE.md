# Mat Wedstrijd Selectie (Groen/Geel/Blauw Systeem)

## Probleem

Voorheen had elke **poule** een eigen selectie. Dit werkt niet goed wanneer meerdere poules op dezelfde mat staan:

- 3 poules op 1 mat = meerdere selecties mogelijk
- Fysiek kan er maar 1 wedstrijd tegelijk op de mat
- Verwarring bij mat-jury en toeschouwers

## Oplossing: Mat-niveau selectie met 3 kleuren

Selecties worden opgeslagen op **mat niveau**, niet op poule niveau. Er zijn 3 statussen:

| Kleur | Database veld | Betekenis | UI |
|-------|---------------|-----------|-----|
| **Groen** | `mat.actieve_wedstrijd_id` | Wedstrijd speelt NU | Groene achtergrond |
| **Geel** | `mat.volgende_wedstrijd_id` | Judoka's staan KLAAR | Gele achtergrond |
| **Blauw** | `mat.gereedmaken_wedstrijd_id` | Judoka's moeten GEREEDMAKEN | Blauwe achtergrond |
| **Neutraal** | NULL | Geen selectie | Witte achtergrond |

---

## Database

**Tabel: `matten`**

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `actieve_wedstrijd_id` | bigint NULL | FK → wedstrijden (groene wedstrijd) |
| `volgende_wedstrijd_id` | bigint NULL | FK → wedstrijden (gele wedstrijd) |
| `gereedmaken_wedstrijd_id` | bigint NULL | FK → wedstrijden (blauwe wedstrijd) |

---

## Interactie Logica

### Selecteren (klik op ongeselecteerde wedstrijd)

| Huidige situatie | Nieuwe wedstrijd wordt |
|------------------|----------------------|
| Geen groen | **Groen** |
| Wel groen, geen geel | **Geel** |
| Wel groen, wel geel, geen blauw | **Blauw** |
| Groen + geel + blauw aanwezig | Alert: "Deselecteer eerst een wedstrijd" |

### Deselecteren (klik op geselecteerde wedstrijd)

| Klik op | Actie | Doorschuiving |
|---------|-------|---------------|
| **Groen** | Vraag bevestiging: "Wedstrijd stoppen?" | Geel → Groen, Blauw → Geel |
| **Geel** | Direct deselecteren | Blauw → Geel |
| **Blauw** | Direct deselecteren | Geen doorschuiving |

**Belangrijk:** Deselectie alleen bij klik op eigen kleur!

### Wedstrijd afgerond (uitslag geregistreerd)

1. Groene wedstrijd wordt gemarkeerd als gespeeld
2. Automatische doorschuiving:
   - Geel → Groen
   - Blauw → Geel
   - Blauw wordt null

---

## Legenda

Bovenaan elke mat interface wordt een legenda getoond:

```
┌─────────────────────────────────────────────────────┐
│ ● Speelt nu   ● Staat klaar   ● Gereed maken        │
│   (groen)       (geel)          (blauw)             │
└─────────────────────────────────────────────────────┘
```

---

## Poule Verplaatsing / Toevoegen

### Poule verplaatst naar andere mat

| Kleur | Gedrag |
|-------|--------|
| **Groen** | Blijft staan tot wedstrijd klaar of gedeselecteerd |
| **Geel** | Wordt automatisch null (gereset), blauw → geel |
| **Blauw** | Wordt automatisch null (gereset) |

### Poule toegevoegd aan mat

- Geen automatische selectie
- Mat-jury klikt handmatig op gewenste wedstrijden

### Poule verwijderd van mat

- Als groene wedstrijd van deze poule was: groen = null, geel → groen, blauw → geel
- Als gele wedstrijd van deze poule was: geel = null, blauw → geel
- Als blauwe wedstrijd van deze poule was: blauw = null

---

## Controller Logica

### MatController@setWedstrijdStatus

```php
public function setWedstrijdStatus(Request $request, Mat $mat)
{
    $validated = $request->validate([
        'actieve_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        'volgende_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        'gereedmaken_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
    ]);

    // Valideer dat wedstrijden bij poules op deze mat horen
    foreach (['actieve', 'volgende', 'gereedmaken'] as $type) {
        $key = "{$type}_wedstrijd_id";
        if ($validated[$key]) {
            $wedstrijd = Wedstrijd::find($validated[$key]);
            if ($wedstrijd->poule->mat_id !== $mat->id) {
                return response()->json(['error' => 'Wedstrijd hoort niet bij deze mat'], 422);
            }
        }
    }

    $mat->update($validated);

    return response()->json(['success' => true, 'mat' => [
        'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
        'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
        'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
    ]]);
}
```

### Doorschuiving na deselectie groen

```php
// Bij deselectie van groen (wedstrijd stoppen)
$mat->update([
    'actieve_wedstrijd_id' => $mat->volgende_wedstrijd_id,    // geel → groen
    'volgende_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id, // blauw → geel
    'gereedmaken_wedstrijd_id' => null,                        // blauw = null
]);
```

### Doorschuiving na deselectie geel

```php
// Bij deselectie van geel
$mat->update([
    'volgende_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id, // blauw → geel
    'gereedmaken_wedstrijd_id' => null,                        // blauw = null
]);
```

---

## View Updates

### Mat Interface (_content.blade.php)

```javascript
// Kleurbepaling
const isGroen = wedstrijd.id === this.matSelectie.actieve_wedstrijd_id;
const isGeel = wedstrijd.id === this.matSelectie.volgende_wedstrijd_id;
const isBlauw = wedstrijd.id === this.matSelectie.gereedmaken_wedstrijd_id;

// CSS classes
let kleurClass = '';
if (isGroen) kleurClass = 'bg-green-100 border-green-500';
else if (isGeel) kleurClass = 'bg-yellow-100 border-yellow-500';
else if (isBlauw) kleurClass = 'bg-blue-100 border-blue-500';
```

### Selectie logica

```javascript
toggleWedstrijd(wedstrijdId) {
    const isGroen = wedstrijdId === this.matSelectie.actieve_wedstrijd_id;
    const isGeel = wedstrijdId === this.matSelectie.volgende_wedstrijd_id;
    const isBlauw = wedstrijdId === this.matSelectie.gereedmaken_wedstrijd_id;

    if (isGroen) {
        // Deselecteer groen met bevestiging
        if (confirm('Wedstrijd stoppen?')) {
            this.deselecteerGroen();
        }
    } else if (isGeel) {
        // Deselecteer geel
        this.deselecteerGeel();
    } else if (isBlauw) {
        // Deselecteer blauw
        this.deselecteerBlauw();
    } else {
        // Selecteer nieuw
        this.selecteerWedstrijd(wedstrijdId);
    }
}

selecteerWedstrijd(wedstrijdId) {
    if (!this.matSelectie.actieve_wedstrijd_id) {
        // Geen groen → wordt groen
        this.matSelectie.actieve_wedstrijd_id = wedstrijdId;
    } else if (!this.matSelectie.volgende_wedstrijd_id) {
        // Wel groen, geen geel → wordt geel
        this.matSelectie.volgende_wedstrijd_id = wedstrijdId;
    } else if (!this.matSelectie.gereedmaken_wedstrijd_id) {
        // Wel groen + geel, geen blauw → wordt blauw
        this.matSelectie.gereedmaken_wedstrijd_id = wedstrijdId;
    } else {
        alert('Deselecteer eerst een wedstrijd');
        return;
    }
    this.saveMatSelectie();
}

deselecteerGroen() {
    // Doorschuiven: geel → groen, blauw → geel
    this.matSelectie.actieve_wedstrijd_id = this.matSelectie.volgende_wedstrijd_id;
    this.matSelectie.volgende_wedstrijd_id = this.matSelectie.gereedmaken_wedstrijd_id;
    this.matSelectie.gereedmaken_wedstrijd_id = null;
    this.saveMatSelectie();
}

deselecteerGeel() {
    // Doorschuiven: blauw → geel
    this.matSelectie.volgende_wedstrijd_id = this.matSelectie.gereedmaken_wedstrijd_id;
    this.matSelectie.gereedmaken_wedstrijd_id = null;
    this.saveMatSelectie();
}

deselecteerBlauw() {
    this.matSelectie.gereedmaken_wedstrijd_id = null;
    this.saveMatSelectie();
}
```

---

## Migratie

### Database migratie

```php
Schema::table('matten', function (Blueprint $table) {
    $table->foreignId('gereedmaken_wedstrijd_id')->nullable()->constrained('wedstrijden')->nullOnDelete();
});
```

---

## Test Scenarios

### Selectie

| Test | Verwacht resultaat |
|------|-------------------|
| Klik wedstrijd (niets geselecteerd) | Wordt groen |
| Klik andere wedstrijd (alleen groen) | Wordt geel |
| Klik andere wedstrijd (groen + geel) | Wordt blauw |
| Klik andere wedstrijd (groen + geel + blauw) | Alert |

### Deselectie

| Test | Verwacht resultaat |
|------|-------------------|
| Klik groene wedstrijd + bevestig | Geel → groen, blauw → geel |
| Klik gele wedstrijd | Blauw → geel |
| Klik blauwe wedstrijd | Blauw = null |

### Doorschuiving na uitslag

| Test | Verwacht resultaat |
|------|-------------------|
| Registreer uitslag groene wedstrijd | Geel → groen, blauw → geel |

### Multi-poule

| Test | Verwacht resultaat |
|------|-------------------|
| 3 poules op mat, selecteer uit elke poule 1 | 1 groen, 1 geel, 1 blauw |
| Verplaats poule met gele wedstrijd | Blauw → geel, groen blijft |

---

## Plaatsbepaling in Poule (Standings)

### Punten Systeem

| Uitslag | WP (Wedstrijd Punten) | JP (Judo Punten) |
|---------|----------------------|------------------|
| Winst | 2 | Score van wedstrijd |
| Gelijkspel | 1 | Score van wedstrijd |
| Verlies | 0 | Score van wedstrijd |

### Plaatsbepaling Regels (in volgorde)

1. **Hoogste WP** (wedstrijd punten)
2. **Hoogste JP** (judo punten) - bij gelijke WP
3. **Onderling resultaat** (head-to-head) - bij gelijke WP én JP:
   - 2 judoka's: winnaar van onderlinge wedstrijd staat hoger
   - 3+ judoka's: alleen als één judoka van ALLE anderen in groep heeft gewonnen
4. **Gedeelde positie** (barrage nodig) - bij cirkel-resultaat (A→B, B→C, C→A)

### Afwezige Judoka's

Judoka's met **0 WP én 0 JP** worden **niet** in de uitslag getoond.
Dit zijn afwezige judoka's die geen wedstrijden hebben gespeeld.

### Voorbeeld Cirkel-Resultaat (Barrage)

```
Judoka A wint van B (A→B)
Judoka B wint van C (B→C)
Judoka C wint van A (C→A)

Resultaat: A, B en C krijgen allemaal dezelfde positie
→ Barrage nodig om winnaar te bepalen
```

### Code Locatie

- `getPlaats()` functie in `_content.blade.php` (mat interface)
- `heeftBarrageNodig()` functie voor detectie cirkel-resultaat
