# Code Review Rapport - 14 februari 2026

> **Door:** HavunCore code audit (Claude Opus 4.6)
> **Scope:** Volledige codebase review - models, controllers, services, security, routes, middleware, config
> **Doel:** Concrete verbeteracties om de code robuuster, veiliger en onderhoudbaarder te maken

---

## Scores

| Laag | Score | Verdict |
|------|-------|---------|
| Models | 8.5/10 | Goed, N+1 risico's oplossen |
| Controllers | B+ | Functioneel, fat controllers refactoren |
| Services | B+ | BlokVerdeling voorbeeldig, rest inconsistent |
| Security | B+ | Solide basis, specifieke fixes nodig |

---

## REEDS GEFIXT (14 feb 2026)

Deze twee kritieke issues zijn al opgelost:

1. **Local sync auth** - `LocalSyncAuth` middleware op alle `/local-server/*` routes
   - Bestanden: `app/Http/Middleware/LocalSyncAuth.php`, `bootstrap/app.php`, `routes/web.php`
   - Toegang via: offline mode, privé IP (LAN), of `LOCAL_SYNC_TOKEN` bearer token

2. **Coach PIN login throttle** - `throttle:login` (5/min) op coach portal login
   - Bestand: `routes/web.php` regel 492

---

## PRIORITEIT 1: Security Hardening

### 1.1 Health endpoint beschermen

**Probleem:** `/health/detailed` lekt environment, database driver, disk usage zonder auth.

**Bestand:** `routes/web.php` (regel waar `/health/detailed` staat)

**Fix:**
```php
Route::get('/health/detailed', [HealthController::class, 'detailed'])
    ->middleware('auth:organisator')
    ->name('health.detailed');
```

Of IP whitelist in de controller:
```php
public function detailed(Request $request)
{
    if (!in_array($request->ip(), ['127.0.0.1', '::1', '188.245.159.115'])) {
        abort(403);
    }
    // ...
}
```

### 1.2 Admin klanten route beschermen

**Probleem:** `/admin/klanten` heeft alleen `auth:organisator` maar geen role check. Elke organisator kan klanten zien.

**Bestand:** `routes/web.php` (regel 136-145 omgeving)

**Fix:** Voeg een check toe in de route group:
```php
Route::middleware(['auth:organisator'])->group(function () {
    Route::get('admin/klanten', [AdminController::class, 'klanten'])
        ->middleware(function ($request, $next) {
            if (!auth('organisator')->user()?->isSitebeheerder()) {
                abort(403);
            }
            return $next($request);
        });
    // ... andere admin routes
});
```

Of beter: maak een `AdminMiddleware` class.

### 1.3 Hardcoded wachtwoord defaults verwijderen

**Probleem:** `config/toernooi.php` heeft publieke defaults voor admin wachtwoorden.

**Bestand:** `config/toernooi.php` (regel 52-53)

**Huidige code:**
```php
'admin_password' => env('ADMIN_PASSWORD', 'WestFries2026'),
'superadmin_pin' => env('SUPERADMIN_PIN', '1234'),
```

**Fix:**
```php
'admin_password' => env('ADMIN_PASSWORD') ?: (app()->environment('local') ? 'dev' : throw new \RuntimeException('ADMIN_PASSWORD not set')),
'superadmin_pin' => env('SUPERADMIN_PIN') ?: (app()->environment('local') ? '0000' : throw new \RuntimeException('SUPERADMIN_PIN not set')),
```

### 1.4 API routes rate limiting

**Probleem:** Public API endpoints (`/api/v1/*`) hebben geen throttle.

**Let op:** `routes/api.php` wordt NIET geladen (geen `api:` in `bootstrap/app.php` `withRouting()`). Dit is dode code. Ofwel verwijderen, ofwel correct laden met rate limiting.

**Optie A - Verwijder `api.php`** (aanbevolen als routes niet nodig zijn)

**Optie B - Laad correct:**
```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
)
```
En voeg throttle toe in `api.php`:
```php
Route::prefix('v1')->middleware('throttle:public-api')->group(function () {
    // ...
});
```

### 1.5 Locale switch ownership validatie

**Probleem:** `routes/web.php` (regel 56-80) - Iemand kan een club locale wijzigen zonder eigenaar te zijn.

**Fix:** Valideer dat de club bij het huidige toernooi/organisator hoort voordat de locale gewijzigd wordt.

---

## PRIORITEIT 2: Data Integriteit

### 2.1 Missing DB transactions bij judoka verplaatsingen

**Probleem:** `WedstrijddagController` verplaatst judoka's tussen poules ZONDER transaction. Als de attach faalt na de detach, is de judoka "kwijt".

**Bestand:** `app/Http/Controllers/WedstrijddagController.php` (rond regel 209-284)

**Huidige code:**
```php
$oudePoule->judokas()->detach($judoka->id);
$nieuwePoule->judokas()->attach($judoka->id);
```

**Fix:**
```php
DB::transaction(function () use ($oudePoule, $nieuwePoule, $judoka) {
    $oudePoule->judokas()->detach($judoka->id);
    $nieuwePoule->judokas()->attach($judoka->id);
    // Statistieken updaten etc.
});
```

**Doe dit voor ALLE multi-step operaties in deze controller.**

### 2.2 Blok markeerNietGewogenAlsAfwezig bypassed model events

**Probleem:** `Blok.php` (regel 100-114) gebruikt bulk `update()` waardoor model observers niet getriggerd worden.

**Bestand:** `app/Models/Blok.php`

**Huidige code:**
```php
Judoka::whereIn('id', $judokaIds)
    ->whereNull('gewicht_gewogen')
    ->update(['aanwezigheid' => 'afwezig']);
```

**Fix:** Als er observers zijn op Judoka (check `SyncQueueObserver`), gebruik dan een loop:
```php
$judokas = Judoka::whereIn('id', $judokaIds)
    ->whereNull('gewicht_gewogen')
    ->where('aanwezigheid', '!=', 'afwezig')
    ->get();

foreach ($judokas as $judoka) {
    $judoka->update(['aanwezigheid' => 'afwezig']);
}
```

### 2.3 ToernooiService auto-cleanup verwijderen

**Probleem:** `ToernooiService.php` (regel 24-27) verwijdert automatisch oude toernooien bij aanmaken van een nieuw toernooi. Dit is gevaarlijk.

**Fix:** Verwijder de automatische cleanup. Maak het een expliciete admin actie.

---

## PRIORITEIT 3: Performance (N+1 Queries)

### 3.1 Model accessors die queries doen

**Bestanden en locaties:**

| Model | Method | Regel | Probleem |
|-------|--------|-------|----------|
| `Toernooi.php` | `getTotaalWedstrijdenAttribute()` | ~412 | `$this->poules()->sum()` per aanroep |
| `Toernooi.php` | `getTotaalJudokasAttribute()` | ~417 | `$this->judokas()->count()` per aanroep |
| `Judoka.php` | `isVasteGewichtsklasse()` | ~213 | Haalt poule + toernooi op |
| `Poule.php` | `updateStatistieken()` | ~213-235 | Count + range queries per poule |
| `Club.php` | `berekenAantalCoachKaarten()` | ~194-234 | Nested loops met queries |
| `Mat.php` | `resetWedstrijdSelectieVoorPoule()` | ~64-111 | 3x `Wedstrijd::find()` |

**Aanpak per type:**

**A) Accessors → gebruik `withCount`/`withSum` in de query:**
```php
// In controller/service waar je toernooien ophaalt:
$toernooi = Toernooi::withCount('judokas')
    ->withSum('poules', 'aantal_wedstrijden')
    ->find($id);
```

**B) Meervoudige finds → gebruik `whereIn`:**
```php
// Mat.php - in plaats van 3x find():
$wedstrijdIds = array_filter([
    $this->actieve_wedstrijd_id,
    $this->volgende_wedstrijd_id,
    $this->reserve_wedstrijd_id,
]);
$wedstrijden = Wedstrijd::whereIn('id', $wedstrijdIds)->get()->keyBy('id');
```

**C) Loop met queries → eager load vooraf:**
```php
// Club::berekenAantalCoachKaarten() - load relaties vooraf:
$clubs = Club::with(['judokas.poules.blok'])->get();
```

### 3.2 Poule classifier caching

**Probleem:** `Poule.php` `getClassifier()` (regel 312-318) maakt ELKE aanroep een nieuwe instance.

**Fix:**
```php
private ?CategorieClassifier $cachedClassifier = null;

private function getClassifier(): CategorieClassifier
{
    return $this->cachedClassifier ??= new CategorieClassifier($this->toernooi);
}
```

### 3.3 Poule dubbele range queries

**Probleem:** `Poule.php` `updateTitel()` roept zowel `getGewichtsRange()` als `getLeeftijdsRange()` aan, die beide dezelfde judokas ophalen.

**Fix:** Combineer in één method:
```php
private function getRanges(): array
{
    $judokas = $this->judokas()->get(['geboortejaar', 'gewicht_ingeschreven', 'gewicht_gewogen']);
    return [
        'gewicht' => $this->calculateGewichtsRange($judokas),
        'leeftijd' => $this->calculateLeeftijdsRange($judokas),
    ];
}
```

---

## PRIORITEIT 4: Fat Controllers Refactoren

### Huidige situatie

| Controller | Regels | Grootste method |
|-----------|--------|-----------------|
| `BlokController` | 1315 | `sprekerInterface()` 175 regels |
| `PouleController` | 1192 | `zoekMatch()` 247 regels |
| `MatController` | 1161 | `plaatsJudoka()` 273 regels |
| `WedstrijddagController` | 1062 | Judoka verplaatsing ~75 regels |
| `CoachPortalController` | 809 | Code duplicatie (8x zelfde auth pattern) |
| `ToernooiController` | 828 | `update()` 189 regels |
| `JudokaController` | 647 | `voerValidatieUit()` 91 regels |

### Refactoring plan

**Stap 1: Extract services voor de zwaarste methods**

| Uit controller | Naar service | Methods |
|----------------|-------------|---------|
| `PouleController` | `PouleMatchingService` | `zoekMatch()` logica |
| `MatController` | `MatWedstrijdService` | `plaatsJudoka()`, uitslagen |
| `BlokController` | `StandingsService` | `berekenPouleStand()`, `getEliminatieStandings()` |
| `BlokController` | `SprekerService` | `sprekerInterface()` data prep |
| `WedstrijddagController` | `JudokaVerplaatsService` | Verplaats logica + transactions |
| `ToernooiController` | `ToernooiUpdateService` | Complex update logica |

**Stap 2: CoachPortalController auth middleware**

Huidige code dupliceert 8x:
```php
$club = $this->getLoggedInClub($request, $toernooiModel, $code);
if (!$club) {
    return $this->redirectToLoginExpired(...);
}
```

**Fix:** Maak `CoachPortalAuth` middleware die club + toernooi resolve:
```php
class CoachPortalAuth
{
    public function handle(Request $request, Closure $next)
    {
        $club = $this->resolveClub($request);
        if (!$club) {
            return redirect()->route('coach.portal.code', [...]);
        }
        $request->attributes->set('coach_club', $club);
        return $next($request);
    }
}
```

**Stap 3: MatController device duplicatie**

Huidige code heeft dubbele methods voor admin vs device:
```php
public function registreerUitslag() { ... }
public function registreerUitslagDevice() { ... }
```

**Fix:** Gebruik middleware voor device auth, niet aparte controller methods.

---

## PRIORITEIT 5: Code Kwaliteit & Consistency

### 5.1 Inconsistente naming (NL/EN mix)

**Patroon:** Methods zijn mix van Nederlands en Engels.

| Nederlands | Engels alternatief |
|------------|-------------------|
| `vindJudokaViaQR()` | `findJudokaByQR()` |
| `verplaatsOverpouler()` | `moveOvercategorized()` |
| `berekenAantalCoachKaarten()` | `calculateCoachCardCount()` |
| `markeerAanwezig()` | `markPresent()` |

**Beslissing nodig:** Kies één taal voor method namen. Aanbeveling: **Nederlands behouden** (domein is NL, team is NL), maar wees consistent.

### 5.2 Magic strings → Enums/Constants

**Voorbeelden:**
```php
// In WegingService.php:
$oudePoule = $judoka->poules()->where('type', 'voorronde')->first();

// In ImportService.php:
$importStatus = 'compleet'; // of 'niet_in_categorie', 'te_corrigeren'
```

**Fix:** Maak enums:
```php
enum PouleType: string {
    case Voorronde = 'voorronde';
    case Kruisfinale = 'kruisfinale';
    case Eliminatie = 'eliminatie';
}

enum ImportStatus: string {
    case Compleet = 'compleet';
    case NietInCategorie = 'niet_in_categorie';
    case TeCorrigeren = 'te_corrigeren';
}
```

### 5.3 Error handling inconsistentie

| Service | Exception type | Verdict |
|---------|---------------|---------|
| `MollieService` | `MollieException` (custom) | Goed |
| `ImportService` | `ImportException` (custom) | Goed |
| `ToernooiService` | Generic exceptions | Moet custom |
| `WegingService` | Geen exceptions | Moet custom |
| `LocalSyncService` | Geen exceptions | Moet custom |

**Fix:** Maak service-specifieke exceptions:
```php
class WegingException extends JudoToernooiException { }
class SyncException extends JudoToernooiException { }
class ToernooiException extends JudoToernooiException { }
```

### 5.4 Array returns → DTOs

**Probleem:** Veel services retourneren untyped arrays.

**Voorbeelden:**
- `ImportService::importeerDeelnemers()` → `array`
- `WimpelService::verwerkPoule()` → `array`
- `LocalSyncService::createResult()` → `object` (stdClass)

**Fix:** Maak DTOs:
```php
class ImportResult {
    public function __construct(
        public int $geimporteerd = 0,
        public int $overgeslagen = 0,
        public array $fouten = [],
        public array $warnings = [],
    ) {}
}
```

### 5.5 ImportService::verwerkRij() opsplitsen

**Probleem:** 168 regels in één method.

**Fix:** Split in:
- `parseRijData(array $rij, array $mapping): array`
- `validateJudokaData(array $data): array` (returns errors)
- `classifyJudoka(array $data, Toernooi $toernooi): array`
- `createOrUpdateJudoka(Toernooi $toernooi, array $data): Judoka`

---

## PRIORITEIT 6: Security Hardening (Lange Termijn)

### 6.1 Club pincodes hashen

**Probleem:** `Club.php` slaat pincodes plaintext op in de pivot tabel.

**Fix:** Hash pincodes zoals wachtwoorden:
```php
// Bij opslaan:
$club->toernooien()->updateExistingPivot($toernooi->id, [
    'pincode' => bcrypt($pincode),
]);

// Bij checken:
if (!Hash::check($inputPin, $club->pivot->pincode)) {
    return false;
}
```

**Let op:** Dit breekt de huidige `getPincodeForToernooi()` method die plaintext teruggeeft voor weergave. Overweeg een apart veld voor weergave, of toon pincode alleen bij aanmaken.

### 6.2 CoachKaart pincode versterken

**Probleem:** 4 cijfers = 10.000 mogelijkheden. Met throttle is het beter, maar alsnog zwak.

**Fix:** Verhoog naar 6 cijfers in `CoachKaart::generatePincode()`:
```php
public static function generatePincode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
```

**Let op:** Bestaande kaarten hebben 4-cijferige pincodes. Migratie nodig.

### 6.3 CSP versterken

**Probleem:** `SecurityHeaders.php` gebruikt `unsafe-inline` en `unsafe-eval` in CSP.

**Lange termijn fix:**
1. Vervang inline scripts door externe bestanden
2. Gebruik nonce-based CSP: `'nonce-{random}'`
3. Bundle CDN dependencies lokaal (jsdelivr, cloudflare, unpkg, pusher)

---

## VOORBEELDIGE CODE (Behouden & Uitbreiden)

Deze patterns zijn goed en moeten als voorbeeld dienen:

### BlokVerdeling helpers (`app/Services/BlokVerdeling/`)
- SOLID: elke helper heeft 1 verantwoordelijkheid
- Pure functions: geen side effects, goed testbaar
- Constants in eigen class
- Goede PHPDoc bij complexe algoritmes

**Gebruik dit pattern voor nieuwe services!**

### Exception hiërarchie (`app/Exceptions/`)
- `JudoToernooiException` base class
- User message vs technical message scheiding
- Static factory methods
- Automatic logging

### FreemiumService
- Single responsibility
- Geen database writes, alleen reads
- Pure logica, goed testbaar

### ActivityLogger
- Never fails (try-catch)
- Automatische actor detectie
- Length limits

---

## Actieplan (Volgorde)

### Sprint 1 (Direct)
- [ ] Fix health endpoint auth (1.1)
- [ ] Fix admin klanten route (1.2)
- [ ] Verwijder hardcoded wachtwoord defaults (1.3)
- [ ] Fix of verwijder dode `api.php` (1.4)
- [ ] Wrap WedstrijddagController verplaatsingen in transactions (2.1)

### Sprint 2 (Korte termijn)
- [ ] Fix top 3 N+1 queries: Toernooi accessors, Mat finds, Poule ranges (3.1, 3.2, 3.3)
- [ ] Cache Poule classifier (3.2)
- [ ] Fix locale switch ownership (1.5)
- [ ] Fix Blok bulk update vs model events (2.2)

### Sprint 3 (Middellange termijn)
- [ ] Extract `PouleMatchingService` uit PouleController (4)
- [ ] Extract `MatWedstrijdService` uit MatController (4)
- [ ] Extract `StandingsService` uit BlokController (4)
- [ ] Maak `CoachPortalAuth` middleware (4)

### Sprint 4 (Lange termijn)
- [ ] Maak PouleType en ImportStatus enums (5.2)
- [ ] Maak service-specifieke exceptions (5.3)
- [ ] Introduceer DTOs voor array returns (5.4)
- [ ] Split ImportService::verwerkRij() (5.5)

### Backlog
- [ ] Hash club pincodes (6.1)
- [ ] Verhoog CoachKaart pincode naar 6 cijfers (6.2)
- [ ] CSP nonce-based maken (6.3)
- [ ] ToernooiService auto-cleanup verwijderen (2.3)
- [ ] Standaardiseer method naming (5.1)

---

*Dit rapport is gegenereerd door HavunCore code audit. Gebruik `/kb code-review` vanuit JudoToernooi om dit terug te vinden.*
