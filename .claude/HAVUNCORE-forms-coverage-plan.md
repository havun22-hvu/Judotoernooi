# Overdracht → HavunCore: forms-coverage heuristiek route-based maken

> **Doel-project:** HavunCore (`D:\GitHub\HavunCore`) — NIET JudoToernooi.
> **Aanleiding:** JudoToernooi haalt de K&V-forms-drempel (60%) niet, maar dat is een
> **meet-artefact** van de scanner-heuristiek, geen echt validatie-gat. De materiële
> route-dekking ligt hoger. Dit document beschrijft de scanner-fix + de SaaS-brede
> impact, zodat een HavunCore-sessie het via `/arch` → `/mpc` kan oppakken.
> **Aanbevolen start:** `/arch --project=havuncore "forms-coverage route-based"` (deze MD als input).

---

## 1. Het probleem (precies)

Bestand: `app/Services/QualitySafety/QualitySafetyScanner.php`, methode `formsCoverage()` (rond regel 714).

Huidige heuristiek is **occurrence-based**:

```
covered  = (# classes die `extends FormRequest`) + (# inline ::validate-occurrences)
coverage = min(covered, writeRoutes) / writeRoutes
```

waarbij `writeRoutes` = `# Route::(post|put|patch|delete)(` over `routes/`.

Drie systematische ondertellingen — coverage komt structureel **te laag** uit:

1. **Gedeelde FormRequest telt als 1, dekt N routes.** `ToernooiRequest` op zowel
   `store` als `update` is één class (`extends FormRequest` matcht 1×) maar dekt 2 write-routes.
   In JudoToernooi geldt dit voor `ToernooiRequest`, `StamJudokaRequest`, `ClubRequest` e.a.
2. **FormRequest-subclasses die niet letterlijk `extends FormRequest` schrijven** worden gemist —
   bv. passkey-request-classes die een tussenliggende base-class extenden.
3. **Service-laag-validatie telt niet.** Een controller die `$service->maak($request->all())`
   aanroept waarna de service valideert, telt als 0. Idem gedeelde validerende `do*()`-methodes
   (in JudoToernooi delen veel mat-routes hun validatie via één `doX()` + Device-wrapper → de
   occurrence-teller ziet 1 validate voor N routes).

Netto: een project dat materieel ~goed valideert kan toch onder 60% scoren → vals `high`/`critical`
finding bij elke `/start`-audit. Inline→FormRequest converteren verzet de teller niet (−1 inline / +1 class).

---

## 2. Voorgestelde fix — route-based coverage

Vervang occurrence-telling door **per-write-route bepalen óf de handler valideert**:

```
coverage = (# write-routes waarvan de handler valideert) / (# write-routes die input verwerken)
```

### 2.1 Algoritme
1. **Enumereer write-routes** uit `routes/*.php`:
   - `Route::post|put|patch|delete('uri', <handler>)`
   - `Route::match(['put','patch'], ...)` → write-verbs eruit filteren
   - `Route::resource(...)` → `store`(POST), `update`(PUT/PATCH), `destroy`(DELETE)
   - `Route::apiResource(...)` → idem
   - `Route::controller(X::class)->group(...)` → handler-class uit de group-context
2. **Resolve handler → controller-class + methode**:
   - `[FooController::class, 'store']` (array-syntax, meest voorkomend)
   - `'FooController@store'` (string-syntax)
   - closure `fn()`/`function()` → body inline inspecteren
3. **Resolve class → bestand** via PSR-4 (`App\Http\Controllers\FooController` →
   `app/Http/Controllers/FooController.php`).
4. **Bepaal validatie in de methode** (één van):
   - parameter type-hint op een `FormRequest`-subclass (`function store(FooRequest $r)`) —
     volg de class transitief: extends FormRequest **of** extends een eigen base die uiteindelijk
     bij FormRequest uitkomt.
   - body bevat `->validate(` / `->validateWithBag(` / `Validator::make(` / `$this->validate(`.
   - **(optioneel, fase 2)** body delegeert 1 niveau diep naar een service- of `do*()`-methode
     die zélf valideert (in JudoToernooi het dominante patroon voor de mat-routes).
5. **Noemer-correctie**: write-routes die **geen request-input verwerken** (bv. `destroy` met
   enkel route-model-binding, of een pure toggle-actie zonder body) horen NIET in de noemer —
   anders straf je projecten voor input-loze acties. Heuristiek: handler gebruikt geen
   `$request`/`$req->...` → uit de noemer (categorie "geen input"). In JudoToernooi is dit ~30%
   van de write-routes (zie bijlage).

### 2.2 Robuustheid / conservatisme (kritiek voor blast-radius)
- **Onresolvebare route = NIET als ongevalideerd tellen.** Als handler-class/methode/bestand
  niet te resolven is (dynamische routes, vendor-controllers, macro's), sluit de route uit de
  noemer i.p.v. 'm als gat te rekenen. Anders ontstaan vals-positieve `critical`s.
- Negeer `vendor/` (bestaande test `test_forms_check_skips_vendor_directory` borgt dit — moet groen blijven).
- Closures zonder `$request`-gebruik → "geen input".

### 2.3 Lichter alternatief (als volledige route-resolutie te fragiel blijkt)
**Optie C — FormRequest-*usages* tellen i.p.v. *classes*:** tel type-hint-occurrences
`function \w+\([A-Z]\w*Request \$` in controller-signatures (gedeelde FormRequest op store+update
telt dan als 2) + inline-validates + service-validatie-signatuur. Lost klacht #1 (de grootste)
op met ~10% van de complexiteit en zonder route→method-mapping. Lost #2/#3 deels op.
**Aanbeveling:** laat `/arch` kiezen tussen volledige route-based (correct, complexer) en optie C
(pragmatisch). Mijn voorkeur: begin met C als de getallen daarmee al eerlijk worden; escaleer naar
route-based alleen als C onvoldoende blijkt.

---

## 3. Test-impact (HavunCore)

Bestand: `tests/Unit/QualitySafety/QualitySafetyScannerTest.php`. De helper `buildLaravelSkeleton(writeRoutes, formRequests, inlineValidates)` en deze 6 tests coderen het **occurrence-gedrag** en moeten mee-evolueren:
- `test_forms_check_clean_when_above_warning`
- `test_forms_check_high_when_below_warning`
- `test_forms_check_critical_when_below_critical_threshold`
- `test_forms_check_counts_inline_validates_as_coverage`
- `test_forms_check_skips_vendor_directory`
- `test_forms_check_skips_when_no_write_routes`

Nieuwe skeleton-helper nodig die routes mét echte handler-referenties + controller-methodes bouwt
(zodat route→method-resolutie getest wordt), plus cases voor: gedeelde FormRequest op 2 routes,
resource-route-expansie, input-loze destroy (uit noemer), onresolvebare route (uit noemer).

Config blijft: `quality-safety.thresholds.forms_warning_pct` (60) / `forms_critical_pct` (30).

---

## 4. SaaS blast-radius (waarom dit zorgvuldig moet)

Deze scanner draait bij **élk Havun-project** z'n `/start`-audit (`docs:issues`/qv-scan).
De heuristiek wijzigen verandert de quality-gate-uitkomst voor **alle** projecten —
HavunClub, Memoria, JudoToernooi, etc. — niet alleen het project dat de klacht triggerde.

**Risico's:**
- Een strengere/andere meting kan elders **nieuwe vals-positieve `critical`s** opleveren →
  `/start` blokkeert of ruist bij projecten die nu schoon zijn.
- Route-resolutie die op project X werkt kan op project Y stuk lopen (andere route-stijl:
  closures, macro's, `Route::controller`, modules/packages).

**Veilige uitrol (aanbevolen):**
1. **Dual-compute met grace-periode**: bereken zowel oud (occurrence) als nieuw (route-based),
   rapporteer beide in de finding-payload, maar **gate** eerst nog op de oude — of zet het nieuwe
   getal als `informational` totdat het op alle projecten geverifieerd is.
2. **Conservatief bij twijfel** (zie §2.2): onresolvebaar → uit de noemer, nooit als gat.
3. **Verifieer op ≥3 echte projecten** vóór het de gate wordt; vergelijk oude vs nieuwe coverage
   en inspecteer elk verschil handmatig (geen blinde acceptatie van een hoger % — controleer dat
   het hogere getal écht klopt, niet dat de teller losser is geworden).
4. Eventueel per-project drempel-override in `config/quality-safety.php` als één project
   structureel afwijkt (bv. veel input-loze action-routes).

---

## 5. Concreet startpunt voor de HavunCore-sessie
- Lees: `app/Services/QualitySafety/QualitySafetyScanner.php` → `formsCoverage()` + helper `countMatches()` + `laravelRootOrNull()`.
- Lees: de 6 `test_forms_check_*` + `buildLaravelSkeleton()` in de test.
- Lees: `config/quality-safety.php` (thresholds).
- `/arch --project=havuncore` met deze MD → blueprint → `/mpc`.
- Definition of done: route-based (of optie C) coverage, alle bestaande qv-tests groen +
  nieuwe cases, dual-compute/grace zodat geen enkel bestaand project een vals-positieve
  `critical` krijgt, geverifieerd op meerdere projecten.

---

## Bijlage — JudoToernooi referentiemeting (write-route-audit, 24-06-2026)

Volledige handmatige audit van `routes/web.php` + `routes/api.php` (handler-methodes individueel gelezen):

| Categorie | Aantal |
|---|---|
| **qv-scanner-meting** (occurrence-based, rauwe `Route::write`-telling) | **215 routes / 59%** |
| Handmatige route-audit: write-routes onderzocht (handler-methodes gelezen) | ~150 |
| Mét validatie (FormRequest / `validate()` / signature-verify / gedeelde validerende `do*()`) | ~95 |
| Geen input (route-model-binding / toggle / auth-only acties, géén request-body) → **uit noemer** | ~45 |
| Écht-ongevalideerd (rauwe input zonder `validate()`) bij audit | 10 |

**Het verschil ZELF is de testcase.** De scanner meet **215 write-routes / 59%**; de handmatige
audit telt ~150 echte handler-routes en **materieel ≈90%** (95 / (150 − 45)). De kloof komt door
precies de 3 ondertellingen uit §1: de scanner telt rauwe `Route::write`-occurrences (closures,
dubbele route-files) in de noemer én ondertelt gedeelde FormRequests/`do*()`-validatie + input-loze
routes. De nieuwe heuristiek moet voor JudoToernooi ≈90% rapporteren, niet 59% — en wel omdat de
meting correcter wordt, **niet** omdat de teller losser wordt gezet.

**De 10 echt-ongevalideerde routes — ALLE 10 inmiddels afgehandeld in JudoToernooi (24-06):**
1. `JudokaController@importConfirm` — `mapping` → **gevalideerd** (`884ba064`)
2. `StamJudokaController@importConfirm` — `mapping` → **gevalideerd** (`884ba064`)
3. `BlokController@genereerVerdeling` — `balans` bounds → **gevalideerd** (`884ba064`)
4. `BlokController@genereerVariabeleVerdeling` — `max_per_blok` → **gevalideerd** (`884ba064`)
5. `BlokController@kiesVariant` — `toewijzingen` → **gevalideerd** (`884ba064`)
6. `PubliekController@favorieten` — `judoka_ids` → **gevalideerd** (`884ba064`)
7. `PasskeyController@qrGenerate` — `browser`/`os` → **begrensd** (`2dc966cc`)
8. `MollieController@simulateComplete` — échte issue was de ongeguarde `betaling/simulate`-route →
   **environment-guard toegevoegd** (`c13446a1`, `abort_if(config('app.env')==='production')`)
9. `LocalSyncController@receiveSync` — `$request->all()` → **structuur afgedwongen + cap** (`dbd88f06`)
10. `ScoreboardController@errorReport` — **types/lengtes afgedwongen** mét behoud truncatie (`dbd88f06`)
