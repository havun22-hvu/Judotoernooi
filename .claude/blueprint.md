> **Blueprint** — JUDOTOERNOOI | Gegenereerd: 2026-06-14 20:27 | Model: gemini-2.5-flash

Bedankt voor de duidelijke beschrijving van de bug en de gewenste oplossing! Dit is een belangrijk punt om de consistentie en bruikbaarheid van het zaaloverzicht te waarborgen.

De analyse is correct: de nieuwe `Poule` objecten erven wel `blok_id` maar missen de `mat_id`, waardoor ze onzichtbaar worden op de matten in het zaaloverzicht.

Hieronder de antwoorden op je vragen:

---

### 1) `mat_id` meegeven bij ALLE `Poule::create()` in `zetOmNaarPoules` (voorronde regel 547 EN kruisfinale regel 599)? `b_mat_id` nodig?

**Ja, de `mat_id` moet meegegeven worden bij alle `Poule::create()` calls die voortkomen uit de omzetting, voor zowel voorronde- als kruisfinale-poules.** De `mat_id` van de originele `$elimPoule` is leidend.

Hier zijn de voorgestelde aanpassingen in `WedstrijddagController::zetOmNaarPoules()`:

**Aanpassing 1: Voorronde-poules**

Rond regel 547 (binnen de loop die de voorronde-poules creëert, waarschijnlijk via `Poule::create`):

```php
// Huidig (vereenvoudigd voorbeeld, exacte implementatie kan variëren)
Poule::create([
    'wedstrijddag_id' => $elimPoule->wedstrijddag_id,
    'categorie_id' => $elimPoule->categorie_id,
    'type' => 'voorronde',
    'blok_id' => $elimPoule->blok_id,
    // ... andere velden ...
]);

// Voorgestelde aanpassing:
Poule::create([
    'wedstrijddag_id' => $elimPoule->wedstrijddag_id,
    'categorie_id' => $elimPoule->categorie_id,
    'type' => 'voorronde',
    'blok_id' => $elimPoule->blok_id,
    'mat_id' => $elimPoule->mat_id, // <-- VOEG DEZE REGEL TOE
    // ... andere velden ...
]);
```

**Aanpassing 2: Kruisfinale-poule**

Rond regel 599 (waar de `kruisfinale` poule wordt aangemaakt):

```php
// Huidig (vereenvoudigd voorbeeld)
$kruisfinalePoule = Poule::create([
    'wedstrijddag_id' => $elimPoule->wedstrijddag_id,
    'categorie_id' => $elimPoule->categorie_id,
    'type' => 'kruisfinale',
    'blok_id' => $elimPoule->blok_id,
    // ... andere velden ...
]);

// Voorgestelde aanpassing:
$kruisfinalePoule = Poule::create([
    'wedstrijddag_id' => $elimPoule->wedstrijddag_id,
    'categorie_id' => $elimPoule->categorie_id,
    'type' => 'kruisfinale',
    'blok_id' => $elimPoule->blok_id,
    'mat_id' => $elimPoule->mat_id, // <-- VOEG DEZE REGEL TOE
    // ... andere velden ...
]);
```

**Over `b_mat_id`:**
Nee, `b_mat_id` is waarschijnlijk niet nodig. De instructie is duidelijk dat de *mat* hetzelfde moet blijven. `mat_id` op de `Poule` is de directe link naar de `matten` tabel en bepaalt op welke mat een poule verschijnt. Als `b_mat_id` een ander concept zou zijn (bijv. een backup mat of een mat binnen een sub-blok), dan zou de bugbeschrijving anders zijn geweest. Blijf bij `mat_id` zoals dit nu op de `elimPoule` staat.

---

### 2) Data-reparatie voor bestaande poules met `mat_id=NULL`

Dit is het meest delicate deel, aangezien de originele `$elimPoule` is verwijderd. De 'gewenste oplossing' stelt dat de mat hetzelfde moet blijven. Dit betekent dat we moeten proberen de *oorspronkelijke* `mat_id` te achterhalen.

**Reperatie Strategie:**

De meest logische benadering is om de `mat_id` te infereren van andere poules in hetzelfde `blok_id`. Aangenomen wordt dat een `blok_id` (op een bepaalde wedstrijddag) consistent aan één `mat_id` gekoppeld is als er al een matverdeling heeft plaatsgevonden.

1.  **Identificeer getroffen poules:** Zoek alle `Poule`s die `mat_id = NULL` hebben en van het type `voorronde` of `kruisfinale` zijn. Beperk dit tot de toernooien/wedstrijddagen waar de bug zich heeft voorgedaan.

    ```sql
    SELECT p.id, p.blok_id, p.type, p.wedstrijddag_id, c.naam as categorie_naam
    FROM poules p
    JOIN categories c ON p.categorie_id = c.id
    WHERE p.mat_id IS NULL
    AND p.type IN ('voorronde', 'kruisfinale');
    ```

2.  **Groepeer per `blok_id` en `wedstrijddag_id`:** Loop door deze poules en groepeer ze per unieke combinatie van `blok_id` en `wedstrijddag_id`.

3.  **Vind de beoogde `mat_id`:** Voor elke groep:
    *   Zoek naar een *andere* `Poule` in diezelfde `blok_id` en `wedstrijddag_id` die *wel* een `mat_id` heeft. Dit is de meest waarschijnlijke `mat_id` die de originele `$elimPoule` had.
    *   **Fallback:** Als er geen enkele andere `Poule` met een `mat_id` in dat `blok_id` kan worden gevonden (dit zou betekenen dat *alle* poules in dat blok door de bug zijn getroffen, of dat het blok nooit een mat toegewezen had), dan is het moeilijk om de "oorspronkelijke" mat te bepalen. In dit geval kan:
        *   Een `mat_id` willekeurig worden toegewezen (minst gewenst, want niet consistent).
        *   Handmatige interventie nodig zijn via het zaaloverzicht om deze blokken alsnog toe te wijzen.
        *   De `BlokMatVerdelingService::verdeel()` opnieuw worden aangeroepen voor die specifieke `wedstrijddag_id` om alle on-toegewezen blokken een mat te geven. **Let op:** dit kan de matverdeling voor *alle* poules op die wedstrijddag opnieuw shufflen, wat tegen het "mat blijft hetzelfde" principe ingaat voor de *niet-getroffen* poules. Dit zou alleen een optie zijn als er geen andere manier is.

**Concreet Reparatie Script (voorbeeld in PHP/Laravel Artisan command):**

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Poule;
use Illuminate\Support\Facades\Log;

class FixNullMatIdsOnConvertedPoules extends Command
{
    protected $signature = 'fix:poules-mat-ids {--wedstrijddag= : Specifieke wedstrijddag_id om te repareren}';
    protected $description = 'Repairs mat_id for converted poules that have mat_id=NULL by inferring from other poules in the same block.';

    public function handle()
    {
        $this->info('Starting mat_id repair for converted poules...');

        $query = Poule::whereNull('mat_id')
                      ->whereIn('type', ['voorronde', 'kruisfinale']);

        if ($this->option('wedstrijddag')) {
            $wedstrijddagId = $this->option('wedstrijddag');
            $query->where('wedstrijddag_id', $wedstrijddagId);
            $this->info("Filtering for wedstrijddag_id: {$wedstrijddagId}");
        }

        $nullMatPoules = $query->get();

        if ($nullMatPoules->isEmpty()) {
            $this->info('No poules found with mat_id=NULL that are of type voorronde or kruisfinale.');
            return 0;
        }

        $this->info("Found {$nullMatPoules->count()} poules with mat_id=NULL.");

        $processedBloks = [];

        foreach ($nullMatPoules->groupBy(['wedstrijddag_id', 'blok_id']) as $wedstrijddagId => $blokGroups) {
            foreach ($blokGroups as $blokId => $poulesInBlok) {
                if (isset($processedBloks[$wedstrijddagId][$blokId])) {
                    continue; // Already processed this block
                }

                $this->line("Processing blok_id {$blokId} on wedstrijddag_id {$wedstrijddagId}...");

                // Try to find a mat_id from another poule in the same block and wedstrijddag
                $existingMatPouleInBlok = Poule::where('wedstrijddag_id', $wedstrijddagId)
                                            ->where('blok_id', $blokId)
                                            ->whereNotNull('mat_id')
                                            ->first();

                $matToAssign = null;
                if ($existingMatPouleInBlok) {
                    $matToAssign = $existingMatPouleInBlok->mat_id;
                    $this->info("  Found mat_id {$matToAssign} from poule {$existingMatPouleInBlok->id} in the same block.");
                } else {
                    $this->warn("  Could not find any existing mat_id for blok_id {$blokId} on wedstrijddag_id {$wedstrijddagId}.");
                    $this->warn("  Poule IDs affected in this block: " . $poulesInBlok->pluck('id')->implode(', '));
                    Log::warning("Data repair: Blok {$blokId} on wedstrijddag {$wedstrijddagId} has no existing mat_id to infer from. Manual review needed.");
                    // Skip this block for automatic repair, or implement fallback logic here
                    continue;
                }

                foreach ($poulesInBlok as $poule) {
                    if ($poule->mat_id === null) { // Double check in case it was fixed by another poule in the loop
                        $poule->mat_id = $matToAssign;
                        $poule->save();
                        $this->line("  Repaired poule {$poule->id} (type: {$poule->type}) to mat_id {$matToAssign}.");
                        Log::info("Data repair: Repaired poule {$poule->id}, blok {$blokId}, assigned mat {$matToAssign}");
                    }
                }
                $processedBloks[$wedstrijddagId][$blokId] = true;
            }
        }

        $this->info('Mat_id repair completed.');
        return 0;
    }
}
```

**Belangrijke overwegingen voor de reparatie:**

*   **Test het script grondig op een staging-omgeving** voordat je het op productie draait.
*   **Maak een database backup** voordat je de reparatie uitvoert.
*   **Voeg logging toe** om te kunnen traceren welke poules zijn aangepast en welke blokken problemen opleverden.
*   De genoemde `fixKruisfinaleMatten` is inderdaad niet geschikt voor `voorronde` poules. Het bovenstaande script pakt beide types aan.

---

### 3) Welke test(s)?

Om deze bug en de fix te testen, zijn zowel unit- als feature/integratietests cruciaal.

**Unit Test: `WedstrijddagController::zetOmNaarPoules()`**

*   **Setup:**
    *   Maak een mock `EliminatiePoule` object aan met een specifieke `id`, `blok_id`, `mat_id`, `wedstrijddag_id`, `categorie_id`.
    *   Mock de `Poule::create()` methode om de argumenten die worden doorgegeven te inspecteren, in plaats van daadwerkelijk een database-entry te maken.
*   **Actie:** Roep de `zetOmNaarPoules()` methode aan met de gemockte `$elimPoule`.
*   **Asserties:**
    *   Controleer of `Poule::create()` minstens één keer (voor de kruisfinale) en N keer (voor de voorrondes) wordt aangeroepen.
    *   Voor elke aanroep van `Poule::create()`, assert dat de `mat_id` in de doorgegeven attributen exact overeenkomt met de `mat_id` van de originele gemockte `$elimPoule`.
    *   Assert dat de `blok_id` ook correct wordt overgenomen.

**Feature/Integratie Test: End-to-end scenario**

*   **Setup:**
    *   Creëer een toernooi, een wedstrijddag, een categorie van het type 'eliminatie'.
    *   Maak een `EliminatiePoule` aan voor deze categorie.
    *   Zorg dat deze `EliminatiePoule` een `mat_id` toegewezen krijgt via de `BlokMatVerdelingService` of een mock daarvan (simuleer het proces van matverdeling via het zaaloverzicht).
    *   Log de `mat_id` van de `EliminatiePoule` voordat deze wordt omgezet.
*   **Actie:**
    *   Roep de functionaliteit aan die `zetOmNaarPoules()` triggert (bijv. via een HTTP-request naar de juiste endpoint, of door de methode direct aan te roepen in een test die ook de database gebruikt).
    *   De originele `EliminatiePoule` wordt nu verwijderd, en nieuwe `voorronde` en `kruisfinale` poules worden aangemaakt.
*   **Asserties:**
    *   Controleer in de database of de `EliminatiePoule` inderdaad is verwijderd.
    *   Haal de zojuist aangemaakte `voorronde` en `kruisfinale` poules op (bijv. door te filteren op `categorie_id` en `wedstrijddag_id` en de types `voorronde`/`kruisfinale`).
    *   Assert dat al deze nieuwe poules de *exacte `mat_id`* hebben die de originele `EliminatiePoule` had.
    *   **UI check (optioneel, maar goed voor bevestiging):** Als er een test-browser beschikbaar is (bijv. met Dusk), navigeer dan naar het zaaloverzicht en controleer of de nieuwe poules op de verwachte mat verschijnen.

---

### 4) Andere plekken met `blok_id` erven zonder `mat_id` (`PouleController` etc.)?

Dit vereist een code-audit van alle plekken waar `Poule::create()` wordt aangeroepen, met speciale aandacht voor situaties waarin een nieuwe `Poule` wordt gemaakt op basis van een bestaande `Poule` of `Blok` die al een `mat_id` heeft.

**Mogelijke plaatsen om te controleren:**

*   **`PouleController.php`**: Als er routes zijn voor het handmatig aanmaken of dupliceren van poules.
*   **Category creatie/update processen**: Als het aanmaken van een categorie direct leidt tot het aanmaken van standaard poules, en deze matten moeten erven van een hogere entiteit of alvast een mat-suggestie krijgen.
*   **Migratiescripts**: Controleer oude migraties die poules aanmaken. Dit is minder waarschijnlijk een plek waar `mat_id` *geërfd* zou moeten worden, eerder een plek waar het aanvankelijk *niet* is ingesteld.
*   **Andere conversie- of splitsingslogica**: Zijn er andere scenario's waarbij een bestaande `Poule` wordt getransformeerd of opgesplitst in nieuwe `Poule`s?

**Algemeen principe:**
Telkens wanneer een `Poule` wordt gecreëerd, moet overwogen worden:
1.  Moet deze poule direct een `mat_id` hebben?
2.  Wordt de `mat_id` geërfd van een oudere/hogere entiteit (zoals in dit geval)?
3.  Wordt de `mat_id` later toegewezen door een service (zoals `BlokMatVerdelingService`)?

Als de intentie is om de mat-toewijzing van een bron-entiteit te behouden, dan moet de `mat_id` actief worden meegenomen in de creatie van de nieuwe `Poule`. Zo niet, dan moet er een duidelijk proces zijn om de `mat_id` later correct toe te wijzen.

Dit bugrapport identificeert een heel specifiek geval van erfenis tijdens een transformatie. Het is goed mogelijk dat dit de enige plek is waar dit probleem zich voordoet, maar een snelle search op `Poule::create` in combinatie met `blok_id` toewijzing kan geen kwaad.

---

Door deze stappen te volgen, zou de bug structureel opgelost moeten zijn, inclusief een strategie voor datareparatie en robuuste tests.