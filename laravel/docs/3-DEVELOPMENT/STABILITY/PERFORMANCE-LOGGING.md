---
title: Database Performance, Async Import & Activity Logging
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Database Performance, Async Import & Activity Logging

> Onderdeel van [Stabiliteitspatronen](../STABILITY.md).

## 9. Database Performance

### Indexes (Migration: 2026_02_02_120000)

```php
// poules
$table->index('leeftijdsklasse');
$table->index('gewichtsklasse');
$table->index('type');

// judokas
$table->index('club_id');
$table->index('aanwezigheid');
$table->index('geslacht');

// wedstrijden
$table->index('is_gespeeld');
$table->index('winnaar_id');

// poule_judoka
$table->index('judoka_id');
```

### N+1 Prevention

```php
// ❌ FOUT - N+1 queries
foreach ($judokas as $judoka) {
    echo $judoka->club->naam; // Query per judoka
}

// ✓ GOED - Eager loading
$judokas = Judoka::with('club')->get();
foreach ($judokas as $judoka) {
    echo $judoka->club->naam; // Geen extra query
}
```

---

## 10. Async Import (Optional)

Voor grote imports (> 100 judokas) is er een background job beschikbaar.

### Job: ImportJudokasJob

```php
use App\Jobs\ImportJudokasJob;

// Dispatch job
$job = new ImportJudokasJob($toernooi, $rows, $mapping, $header);
$importId = $job->getImportId();
dispatch($job);

// Check progress
$progress = ImportJudokasJob::getProgress($importId);
// {processed: 50, total: 100, percentage: 50, status: 'processing'}
```

### Progress Endpoint

```
GET /organisator/{organisator}/toernooi/{toernooi}/judoka/import/progress?import_id=xxx
```

```json
{
    "processed": 50,
    "total": 100,
    "percentage": 50,
    "status": "processing",
    "error": null,
    "updated_at": "2026-02-02T12:00:00+01:00"
}
```

### Status Values

| Status | Betekenis |
|--------|-----------|
| `starting` | Job gestart, nog geen rows verwerkt |
| `processing` | Bezig met importeren |
| `completed` | Import succesvol afgerond |
| `failed` | Import gefaald (zie error) |

---

## 11. Activity Logging

Elke belangrijke actie in de applicatie wordt gelogd in de `activity_logs` tabel. Dit biedt een audit trail zodat je altijd kunt achterhalen wie, wat, wanneer deed.

### Tabel: `activity_logs`

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| `toernooi_id` | FK | Gekoppeld toernooi |
| `actie` | string(50) | `verplaats_judoka`, `registreer_uitslag`, etc. |
| `model_type` | string(50) | `Judoka`, `Poule`, `Wedstrijd` |
| `model_id` | uint/null | ID van betreffende record |
| `beschrijving` | string | Leesbaar: "Jevi van Bussel verplaatst naar Poule 7" |
| `properties` | json/null | `{old: {...}, new: {...}, meta: {...}}` |
| `actor_type` | string(30) | `organisator`, `rol_sessie`, `device`, `systeem` |
| `actor_id` | uint/null | organisator_id of device_toegang_id |
| `actor_naam` | string(100) | "Henk (admin)" of "Weging (sessie)" |
| `ip_adres` | string(45) | Client IP |
| `interface` | string(30) | `dashboard`, `mat`, `weging`, `hoofdjury` |

### Service: `ActivityLogger`

```php
use App\Services\ActivityLogger;

// Basis logging
ActivityLogger::log($toernooi, 'verplaats_judoka', "Jevi verplaatst naar Poule 7", [
    'model' => $judoka,
    'properties' => ['van_poule' => 3, 'naar_poule' => 7],
]);
```

### Actor detectie (automatisch)

1. `auth('organisator')->user()` → organisator
2. `request()->get('device_toegang')` → device (via CheckDeviceBinding middleware)
3. `session('rol_type')` → rol_sessie
4. Fallback → 'systeem'

### Welke acties worden gelogd

**Wedstrijddag:** verplaats_judoka, nieuwe_judoka, meld_af, herstel, verwijder_uit_poule
**Mat:** registreer_uitslag, plaats_judoka, verwijder_judoka, poule_klaar
**Weging:** registreer_gewicht, markeer_aanwezig, markeer_afwezig
**Poules:** genereer_poules, maak_poule, verwijder_poule, verplaats_judoka
**Blokken:** sluit_weging, activeer_categorie, reset_categorie, reset_alles, reset_blok
**Toernooi:** update_instellingen, afsluiten, verwijder

### View

Route: `/{slug}/toernooi/{toernooi}/activiteiten`
Filterable tabel met actie, model type, zoekterm. Paginering 50 per pagina.

---

