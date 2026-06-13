> **Blueprint** — JUDOTOERNOOI | Gegenereerd: 2026-06-13 13:15 | Model: gemini-2.5-flash

Oké, ik heb de gevraagde functionaliteit geïmplementeerd en een gedetailleerde uitleg gegeven van elke stap.

---

### Overzicht van de Oplossing

1.  **Opslag (`eind_optie`, `golden_score_duur`):** Er is gekozen om deze instellingen op te slaan in een `JSONB`-kolom genaamd `categorie_instellingen` op de `toernooien` tabel. Deze kolom zal een associatieve array bevatten waarbij elke sleutel een `categorie_key` is (bijv. "U15", "Senioren") en de waarde een object is met `eind_optie` en `golden_score_duur` voor die categorie. Dit past bij het concept dat deze instellingen eigenschappen zijn van de categorie/leeftijdsklasse op toernooiniveau, en niet per individuele poule of wedstrijd.
    *   **Reden voor deze keuze:**
        *   Centraliseert categorie-specifieke regels per toernooi.
        *   Voorkomt redundantie als meerdere poules dezelfde `categorie_key` delen.
        *   Flexibel, omdat nieuwe categorieën dynamisch kunnen worden toegevoegd in de JSON zonder schemawijzigingen.
        *   Sluit aan bij het genoemde `getCategorieConfig()`-mechanisme door een gestructureerde configuratie per categorie te bieden.

2.  **Serialisatie (`ScoreboardController::formatMatch`):** De `formatMatch` methode in de `ScoreboardController` is uitgebreid om de `eind_optie` en `golden_score_duur` van de bijbehorende poule en toernooi op te halen en mee te sturen in de JSON-response. Standaardwaarden conform het contract worden toegepast als de instellingen niet expliciet zijn gedefinieerd.

3.  **Admin/Instellingen-UI (Conceptueel):** Een concept voor een beheerpagina is uitgewerkt, inclusief controller-logica en een Blade-template schets. Deze UI zal de organisator in staat stellen om voor elke unieke `categorie_key` (uit de reeds aangemaakte poules van het toernooi) de `eind_optie` en `golden_score_duur` in te stellen. IJF 2024 defaults worden als suggestie in de UI gebruikt, maar de backend handhaaft de contractuele defaults bij afwezigheid van expliciete instellingen.

4.  **Tests:** Feature tests zijn toegevoegd om de correcte werking van de API-endpoint (`/api/scoreboard/current-match`) te valideren, inclusief de verwerking van standaardwaarden, geconfigureerde waarden, en backward compatibility.

---

### Implementatie Details

Hieronder volgen de codeblokken voor de wijzigingen:

#### 1. Migratie: `add_category_settings_to_toernooien_table`

Deze migratie voegt de `categorie_instellingen` JSONB-kolom toe aan de `toernooien` tabel.

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_category_settings_to_toernooien_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            // Gebruik 'json' type voor JSONB in PostgreSQL of JSON in MySQL/MariaDB.
            // Laravel handhabt dit automatisch.
            // Nullable voor optionele instellingen en backwards compatibility.
            $table->json('categorie_instellingen')->nullable()->after('uitslagen_publiek');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('categorie_instellingen');
        });
    }
};

```

#### 2. Model Wijzigingen: `app/Models/Toernooi.php`

Het `Toernooi` model wordt uitgebreid met de nieuwe `categorie_instellingen` kolom, inclusief `casts` voor automatische JSON-parsing en een helper-methode `getCategorySetting` om eenvoudig categorie-specifieke instellingen op te halen met fallback naar standaardwaarden. Ook is een methode toegevoegd om de unieke categorie-sleutels van het toernooi op te halen, handig voor de admin-UI.

```php
// app/Models/Toernooi.php

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection; // Import Collection

class Toernooi extends Model
{
    use HasFactory;

    protected $table = 'toernooien';

    protected $fillable = [
        'naam',
        'locatie',
        'datum',
        'status',
        'organisator_id',
        'regelement_id',
        'is_recreatief',
        'uitslagen_publiek',
        'categorie_instellingen', // <-- Nieuwe veld
    ];

    protected $casts = [
        'datum' => 'datetime',
        'categorie_instellingen' => 'array', // <-- Cast als array voor JSON-parsing
    ];

    // Relaties (bestaande, ter context)
    public function organisator() { return $this->belongsTo(User::class, 'organisator_id'); }
    public function regelement() { return $this->belongsTo(Regelement::class, 'regelement_id'); }
    public function poules() { return $this->hasMany(Poule::class); }
    public function matten() { return $this->hasMany(Mat::class); }

    /**
     * Haalt een specifieke instelling op voor een gegeven categorie.
     *
     * @param string $categoryKey De sleutel die de categorie identificeert (bijv. "U15", "Senioren").
     * @param string $settingName De naam van de instelling (bijv. "eind_optie", "golden_score_duur").
     * @param mixed $default De standaardwaarde die moet worden teruggegeven als de instelling niet wordt gevonden.
     * @return mixed
     */
    public function getCategorySetting(string $categoryKey, string $settingName, mixed $default = null): mixed
    {
        if (!is_array($this->categorie_instellingen)) {
            return $default;
        }

        return data_get($this->categorie_instellingen, "$categoryKey.$settingName", $default);
    }

    /**
     * Haalt de unieke categorie-sleutels op die worden gebruikt in de poules van dit toernooi.
     * Dit is nuttig voor het vullen van de admin-UI.
     *
     * @return Collection<int, string>
     */
    public function getDistinctCategoryKeys(): Collection
    {
        return $this->poules()->distinct('categorie_key')->pluck('categorie_key');
    }
}

```

*(Aanname: `Poule.php` en `Wedstrijd.php` modellen bestaan en hebben de juiste relaties met `Toernooi` en `Poule` respectievelijk. Er zijn geen directe wijzigingen nodig in deze modellen behalve dat `Poule` een `categorie_key` heeft en een relatie met `Toernooi`.)*

#### 3. Serialisatie: `app/Http/Controllers/ScoreboardController.php`

De `formatMatch` methode is aangepast om de nieuwe velden `eind_optie` en `golden_score_duur` mee te sturen, inclusief de contractueel vastgelegde defaults.

```php
// app/Http/Controllers/ScoreboardController.php

<?php

namespace App\Http\Controllers;

use App\Models\Wedstrijd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\ScoreboardAssignment; // Aanname: Reverb event

class ScoreboardController extends Controller
{
    // ... andere methoden in ScoreboardController

    /**
     * Formatteert een wedstrijd object voor het scorebord display.
     *
     * @param Wedstrijd $wedstrijd
     * @return array
     */
    protected function formatMatch(Wedstrijd $wedstrijd): array
    {
        // Eager load relaties om N+1 queries te voorkomen
        $wedstrijd->load([
            'poule.toernooi',
            'deelnemer1.judoka',
            'deelnemer2.judoka',
            'scheidsrechter'
        ]);

        $poule = $wedstrijd->poule;
        $toernooi = $poule->toernooi;
        $categoryKey = $poule->categorie_key;

        // Haal categorie-instellingen op met standaardwaarden
        // CONTRACT: null/afwezig => app gebruikt 'golden_score' als default voor eind_optie
        $eindOptie = $toernooi->getCategorySetting($categoryKey, 'eind_optie', 'golden_score');

        // CONTRACT: golden_score_duur is een integer|null. null=onbeperkt.
        // Alleen relevant bij eind_optie='golden_score'.
        $goldenScoreDuur = null;
        if ($eindOptie === 'golden_score') {
            // Haal de geconfigureerde duur op.
            $configuredDuration = $toernooi->getCategorySetting($categoryKey, 'golden_score_duur', null);

            // Als geconfigureerd (niet null) en groter dan 0, gebruik de waarde.
            // Anders (null of 0), betekent dit onbeperkt, dus stuur null als per contract.
            if ($configuredDuration !== null && (int)$configuredDuration > 0) {
                $goldenScoreDuur = (int) $configuredDuration;
            } else {
                $goldenScoreDuur = null; // 0 of expliciet null in instellingen betekent onbeperkt
            }
        }

        // Basis structuur voorbeeld - aanpassen aan de werkelijke bestaande format
        $formattedMatch = [
            'id' => $wedstrijd->id,
            'poule_naam' => $poule->naam,
            'categorie_key' => $categoryKey,
            'deelnemer1' => [
                'id' => $wedstrijd->deelnemer1->id,
                'naam' => $wedstrijd->deelnemer1->judoka->naam,
                // ... andere details voor deelnemer1
            ],
            'deelnemer2' => [
                'id' => $wedstrijd->deelnemer2->id,
                'naam' => $wedstrijd->deelnemer2->judoka->naam,
                // ... andere details voor deelnemer2
            ],
            'scheidsrechter' => $wedstrijd->scheidsrechter ? $wedstrijd->scheidsrechter->name : null,
            'status' => $wedstrijd->status,
            'volgorde' => $wedstrijd->volgorde,
            'mat_id' => $wedstrijd->mat_id,
            // ... alle andere bestaande velden

            // Voeg de nieuwe velden toe
            'eind_optie' => $eindOptie,
            'golden_score_duur' => $goldenScoreDuur,
        ];

        return $formattedMatch;
    }

    /**
     * API endpoint om de huidige wedstrijd voor een gegeven scorebord/mat op te halen.
     * (Dit is een voorbeeld, de logica om de "huidige" wedstrijd te bepalen kan variëren)
     *
     * @param Request $request
     * @param int $toernooiId
     * @param int $matId
     * @return \Illuminate\Http\JsonResponse
     */
    public function currentMatch(Request $request, int $toernooiId, int $matId)
    {
        $currentMatch = Wedstrijd::whereHas('poule', function ($query) use ($toernooiId) {
                $query->where('toernooi_id', $toernooiId);
            })
            ->where('mat_id', $matId)
            ->whereIn('status', ['gepland', 'bezig']) // Of welke status 'huidig' betekent
            ->orderBy('volgorde')
            ->first();

        if (!$currentMatch) {
            return response()->json(['message' => 'Geen actieve wedstrijd gevonden voor dit scorebord.', 'match' => null], 404);
        }

        return response()->json([
            'match' => $this->formatMatch($currentMatch),
        ]);
    }

    /**
     * Voorbeeld methode die een Reverb event zou kunnen dispatch, gebruikmakend van formatMatch.
     * (Ter illustratie van waar formatMatch verder gebruikt kan worden.)
     *
     * @param Wedstrijd $wedstrijd
     * @param int $toernooiId
     * @param int $matId
     */
    public function assignMatchToScoreboard(Wedstrijd $wedstrijd, int $toernooiId, int $matId)
    {
        // ... logica om match's mat_id en status te updaten
        $wedstrijd->update(['mat_id' => $matId, 'status' => 'gepland']);

        $formattedMatch = $this->formatMatch($wedstrijd);

        // Dispatch het Reverb event
        broadcast(new ScoreboardAssignment($toernooiId, $matId, $formattedMatch))->toOthers();

        // ... return respons
    }

    // ... andere methoden
}
```

#### 4. Admin UI (Conceptuele Code)

Dit is een schets van hoe de admin-UI eruit zou kunnen zien om de categorie-instellingen te beheren.

**A. Controller: `app/Http/Controllers/Admin/ToernooiSettingsController.php`**

```php
// app/Http/Controllers/Admin/ToernooiSettingsController.php

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Toernooi;
use Illuminate\Http\Request;

class ToernooiSettingsController extends Controller
{
    /**
     * Toont het formulier voor het bewerken van toernooi categorie-instellingen.
     *
     * @param int $toernooiId
     * @return \Illuminate\View\View
     */
    public function showCategorySettingsForm(int $toernooiId)
    {
        $toernooi = Toernooi::findOrFail($toernooiId);

        // Haal unieke categorie-sleutels op uit bestaande poules voor dit toernooi
        $categoryKeys = $toernooi->getDistinctCategoryKeys();

        // Als er nog geen poules/categorieën zijn, geef dan enkele standaard categorieën op
        // In een echte applicatie komen deze mogelijk uit een config of een globale tabel.
        if ($categoryKeys->isEmpty()) {
            $categoryKeys = collect(['Senioren', 'U21', 'U18', 'U15', 'U13', 'Recreatief']);
        }

        // Bereid gegevens voor de view voor
        $settings = [];
        foreach ($categoryKeys as $key) {
            $settings[$key] = [
                'eind_optie' => $toernooi->getCategorySetting($key, 'eind_optie'),
                'golden_score_duur' => $toernooi->getCategorySetting($key, 'golden_score_duur'),
            ];
        }

        // Voorbeeld IJF defaults voor de UI (worden alleen voorgesteld als er nog geen instellingen zijn)
        $ijfDefaults = [
            'Senioren' => ['eind_optie' => 'golden_score', 'golden_score_duur' => null], // null = onbeperkt
            'U21' => ['eind_optie' => 'golden_score', 'golden_score_duur' => 3],
            'U18' => ['eind_optie' => 'golden_score', 'golden_score_duur' => 3],
            'U15' => ['eind_optie' => 'golden_score', 'golden_score_duur' => 2],
            'U13' => ['eind_optie' => 'hantei', 'golden_score_duur' => null],
            'Recreatief' => ['eind_optie' => 'hikiwake', 'golden_score_duur' => null],
        ];

        return view('admin.toernooien.category_settings', [
            'toernooi' => $toernooi,
            'categoryKeys' => $categoryKeys,
            'settings' => $settings,
            'ijfDefaults' => $ijfDefaults, // Geef defaults door aan frontend voor initiële setup
            'eindOptieOptions' => [ // Opties voor de dropdown
                'golden_score' => 'Golden Score',
                'hantei' => 'Hantei',
                'hikiwake' => 'Gelijkspel (Hikiwake)',
            ],
        ]);
    }

    /**
     * Slaat de toernooi categorie-instellingen op of werkt deze bij.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $toernooiId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateCategorySettings(Request $request, int $toernooiId)
    {
        $toernooi = Toernooi::findOrFail($toernooiId);

        $validatedData = $request->validate([
            'category_settings' => 'array',
            'category_settings.*.eind_optie' => 'nullable|in:golden_score,hantei,hikiwake',
            'category_settings.*.golden_score_duur' => 'nullable|integer|min:0', // 0 of null voor onbeperkt
        ]);

        // Voeg de nieuwe instellingen samen met bestaande om niet-ingediende categorieën te behouden
        $currentSettings = $toernooi->categorie_instellingen ?? [];
        $newSettings = $validatedData['category_settings'] ?? [];

        foreach ($newSettings as $categoryKey => $data) {
            $currentSettings[$categoryKey] = [
                'eind_optie' => $data['eind_optie'] ?? null,
                // Als 'golden_score_duur' leeg is of 0, sla het op als null (onbeperkt)
                'golden_score_duur' => isset($data['golden_score_duur']) && (int)$data['golden_score_duur'] > 0 ? (int)$data['golden_score_duur'] : null,
            ];
        }

        $toernooi->update([
            'categorie_instellingen' => $currentSettings,
        ]);

        return redirect()->back()->with('success', 'Categorie-instellingen succesvol opgeslagen.');
    }
}
```

**B. Routes: `routes/web.php` (of `routes/admin.php`)**

```php
// routes/web.php (of routes/admin.php)

use App\Http\Controllers\Admin\ToernooiSettingsController;

// Voorbeeld routes voor het admin paneel
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // ... bestaande admin routes

    Route::get('toernooien/{toernooi}/settings/categories', [ToernooiSettingsController::class, 'showCategorySettingsForm'])
        ->name('toernooien.settings.categories.show');
    Route::put('toernooien/{toernooi}/settings/categories', [ToernooiSettingsController::class, 'updateCategorySettings'])
        ->name('toernooien.settings.categories.update');
});
```

**C. Blade View: `resources/views/admin/toernooien/category_settings.blade.php`**

```blade
{{-- resources/views/admin/toernooien/category_settings.blade.php --}}

@extends('layouts.admin') {{-- Aanname: een admin layout --}}

@section('content')
    <div class="container">
        <h1>Instellingen voor categorieën - {{ $toernooi->naam }}</h1>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.toernooien.settings.categories.update', $toernooi) }}" method="POST">
            @csrf
            @method('PUT')

            @foreach($categoryKeys as $categoryKey)
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="mb-0">Categorie: {{ $categoryKey }}</h3>
                        <small class="text-muted">
                            @php
                                $currentEindOptie = $settings[$categoryKey]['eind_optie'] ?? null;
                                $currentGoldenScoreDuur = $settings[$categoryKey]['golden_score_duur'] ?? null;

                                // Pas IJF defaults toe als er nog geen instellingen zijn opgeslagen voor deze categorie
                                if ($currentEindOptie === null && isset($ijfDefaults[$categoryKey])) {
                                    $currentEindOptie = $ijfDefaults[$categoryKey]['eind_optie'];
                                    $currentGoldenScoreDuur = $ijfDefaults[$categoryKey]['golden_score_duur'];
                                } elseif ($currentEindOptie === null) {
                                    // Default naar contractuele waarde als er geen IJF default of opgeslagen waarde is
                                    $currentEindOptie = 'golden_score'; // Contractuele default
                                }
                                // De duur voor Golden Score is standaard onbeperkt (null) als niet specifiek ingesteld
                                // De formulierinvoer handelt het weergeven van 'Onbeperkt' of '0' voor null af.
                            @endphp
                            Huidige instelling:
                            @if ($currentEindOptie === 'golden_score')
                                Golden Score (
                                @if ($currentGoldenScoreDuur === null || $currentGoldenScoreDuur === 0)
                                    Onbeperkt
                                @else
                                    {{ $currentGoldenScoreDuur }} min
                                @endif
                                )
                            @elseif ($currentEindOptie === 'hantei')
                                Hantei
                            @elseif ($currentEindOptie === 'hikiwake')
                                Gelijkspel (Hikiwake)
                            @else
                                Niet ingesteld (standaard: Golden Score Onbeperkt)
                            @endif
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="form-group row">
                            <label for="eind_optie_{{ $categoryKey }}" class="col-sm-4 col-form-label">Einde optie:</label>
                            <div class="col-sm-8">
                                <select name="category_settings[{{ $categoryKey }}][eind_optie]" id="eind_optie_{{ $categoryKey }}" class="form-control" onchange="toggleGoldenScoreDuur('{{ $categoryKey }}')">
                                    @foreach($eindOptieOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old("category_settings.$categoryKey.eind_optie", $currentEindOptie) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("category_settings.$categoryKey.eind_optie")
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mt-3 golden-score-duur-group" id="golden_score_duur_group_{{ $categoryKey }}" style="{{ old("category_settings.$categoryKey.eind_optie", $currentEindOptie) == 'golden_score' ? '' : 'display: none;' }}">
                            <label for="golden_score_duur_{{ $categoryKey }}" class="col-sm-4 col-form-label">Golden Score duur (minuten):</label>
                            <div class="col-sm-8">
                                <input type="number" name="category_settings[{{ $categoryKey }}][golden_score_duur]" id="golden_score_duur_{{ $categoryKey }}"
                                       class="form-control" placeholder="0 voor onbeperkt" min="0"
                                       value="{{ old("category_settings.$categoryKey.golden_score_duur", $currentGoldenScoreDuur) }}">
                                <small class="form-text text-muted">Laat leeg of vul 0 in voor onbeperkte Golden Score duur.</small>
                                @error("category_settings.$categoryKey.golden_score_duur")
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary">Instellingen Opslaan</button>
        </form>
    </div>

    <script>
        function toggleGoldenScoreDuur(categoryKey) {
            const eindOptieSelect = document.getElementById(`eind_optie_${categoryKey}`);
            const goldenScoreDuurGroup = document.getElementById(`golden_score_duur_group_${categoryKey}`);

            if (eindOptieSelect && goldenScoreDuurGroup) {
                if (eindOptieSelect.value === 'golden_score') {
                    goldenScoreDuurGroup.style.display = 'flex'; // Of 'block', afhankelijk van je CSS
                } else {
                    goldenScoreDuurGroup.style.display = 'none';
                }
            }
        }

        // Initialiseer bij het laden van de pagina voor alle categorieën
        document.addEventListener('DOMContentLoaded', function() {
            @foreach($categoryKeys as $categoryKey)
                toggleGoldenScoreDuur('{{ $categoryKey }}');
            @endforeach
        });
    </script>
@endsection
```

#### 5. Feature Tests: `tests/Feature/ScoreboardApiTest.php`

Deze tests valideren de API-response van `/api/scoreboard/{toernooiId}/mat/{matId}/current-match` onder verschillende configuraties.

```php
// tests/Feature/ScoreboardApiTest.php

<?php

namespace Tests\Feature;

use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Models\Deelnemer;
use App\Models\Judoka;
use App\Models\User; // Voor scheidsrechter
use App\Models\Mat; // Voor mat
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Maak een gebruiker voor organisator en scheidsrechter
        User::factory()->create(['id' => 1, 'name' => 'Test Organizer', 'email' => 'organizer@test.com']);
        User::factory()->create(['id' => 2, 'name' => 'Test Referee', 'email' => 'referee@test.com']);
    }

    /**
     * Helper functie om een toernooi en wedstrijd te creëren met optionele categorie-instellingen.
     */
    private function createTournamentAndMatch(string $categoryKey, ?array $categorySettings = null): Wedstrijd
    {
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => 1,
            'categorie_instellingen' => $categorySettings,
        ]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => $categoryKey,
        ]);
        $judoka1 = Judoka::factory()->create();
        $judoka2 = Judoka::factory()->create();
        $deelnemer1 = Deelnemer::factory()->create(['judoka_id' => $judoka1->id, 'poule_id' => $poule->id, 'toernooi_id' => $toernooi->id]);
        $deelnemer2 = Deelnemer::factory()->create(['judoka_id' => $judoka2->id, 'poule_id' => $poule->id, 'toernooi_id' => $toernooi->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'deelnemer1_id' => $deelnemer1->id,
            'deelnemer2_id' => $deelnemer2->id,
            'scheidsrechter_id' => 2,
            'mat_id' => $mat->id,
            'status' => 'gepland',
            'volgorde' => 1,
        ]);

        return $wedstrijd;
    }

    /** @test */
    public function it_returns_default_golden_score_and_null_duration_when_no_category_settings_exist()
    {
        $wedstrijd = $this->createTournamentAndMatch('Senioren'); // Geen categorie-instellingen meegegeven

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'golden_score',
                         'golden_score_duur' => null, // null voor onbeperkt
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_configured_golden_score_and_duration_for_a_category()
    {
        $categoryKey = 'U18';
        $categorySettings = [
            $categoryKey => [
                'eind_optie' => 'golden_score',
                'golden_score_duur' => 3, // 3 minuten
            ]
        ];
        $wedstrijd = $this->createTournamentAndMatch($categoryKey, $categorySettings);

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'golden_score',
                         'golden_score_duur' => 3,
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_golden_score_with_null_duration_when_duration_is_set_to_0_or_null_in_settings()
    {
        $categoryKey = 'Senioren';
        // Test met duur ingesteld op 0
        $categorySettings = [
            $categoryKey => [
                'eind_optie' => 'golden_score',
                'golden_score_duur' => 0, // 0 betekent ook onbeperkt
            ]
        ];
        $wedstrijd = $this->createTournamentAndMatch($categoryKey, $categorySettings);

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'golden_score',
                         'golden_score_duur' => null, // Moet null zijn in output
                     ]
                 ]);

        // Test met expliciet null in instellingen
        $categorySettings = [
            $categoryKey => [
                'eind_optie' => 'golden_score',
                'golden_score_duur' => null,
            ]
        ];
        $wedstrijd = $this->createTournamentAndMatch($categoryKey, $categorySettings);

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'golden_score',
                         'golden_score_duur' => null, // Moet null zijn in output
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_hantei_option_and_null_duration_when_configured()
    {
        $categoryKey = 'U13';
        $categorySettings = [
            $categoryKey => [
                'eind_optie' => 'hantei',
                // golden_score_duur is irrelevant en moet null zijn
            ]
        ];
        $wedstrijd = $this->createTournamentAndMatch($categoryKey, $categorySettings);

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'hantei',
                         'golden_score_duur' => null, // Moet altijd null zijn als niet golden_score
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_hikiwake_option_and_null_duration_when_configured()
    {
        $categoryKey = 'Recreatief';
        $categorySettings = [
            $categoryKey => [
                'eind_optie' => 'hikiwake',
            ]
        ];
        $wedstrijd = $this->createTournamentAndMatch($categoryKey, $categorySettings);

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'hikiwake',
                         'golden_score_duur' => null, // Moet altijd null zijn als niet golden_score
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_default_golden_score_when_eind_optie_is_missing_for_category()
    {
        $categoryKey = 'Junioren';
        $categorySettings = [
            $categoryKey => [
                // 'eind_optie' ontbreekt
                'golden_score_duur' => 5, // Deze moet genegeerd worden omdat de default eind_optie 'golden_score' is, en de default duur daarvoor null is
            ]
        ];
        $wedstrijd = $this->createTournamentAndMatch($categoryKey, $categorySettings);

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'golden_score', // Default toegepast
                         'golden_score_duur' => null, // Default voor duur is null als niet expliciet ingesteld voor Golden Score
                     ]
                 ]);
    }

    /** @test */
    public function it_handles_non_existent_category_key_gracefully_with_defaults()
    {
        $wedstrijd = $this->createTournamentAndMatch('NonExistentCategory'); // Categorie-sleutel bestaat niet in instellingen

        $response = $this->getJson("/api/scoreboard/{$wedstrijd->poule->toernooi_id}/mat/{$wedstrijd->mat_id}/current-match");

        $response->assertStatus(200)
                 ->assertJson([
                     'match' => [
                         'eind_optie' => 'golden_score',
                         'golden_score_duur' => null,
                     ]
                 ]);
    }
}
```