> **Blueprint** — JUDOTOERNOOI | Gegenereerd: 2026-07-22 16:56 | Model: gemini-2.5-flash

Oké, hier is de herstructurering van de deelnemers-tab naar geneste inklap-accordions, rekening houdend met alle eisen van Henk en de strikte CSP-restricties van de Alpine.js build.

De belangrijkste aanpak is:
1.  **Eén hoofd `x-data`:** Alle open/dicht-statussen worden beheerd in één plat object (`openStates`) binnen de hoofd `x-data` voor de hele deelnemerssectie. Dit vermijdt geneste `x-data` en `x-model` problemen.
2.  **Unieke sleutels:** Elke categorie en elke gewichtsklasse krijgt een unieke sleutel (`category_ID` of `weightclass_CATEGORYID_WEIGHTCLASSID`) voor de `openStates` object.
3.  **CSP-veilige methoden:** Toggle-functionaliteit gebeurt via simpele methoden (`toggle(key)`) die de boolean-waarde van een sleutel in `openStates` omdraaien. Dit voorkomt verboden property-assignments.
4.  **`x-show` en `x-collapse`:** Gecombineerd gebruik van `x-show` en `x-collapse` voor robuuste in/uitklapfunctionaliteit, wat de "H-15/D-15-klapbug" problemen moet verhelpen door een correcte hoogtemeting en rendering.
5.  **Conditionele rendering:** Gebruik van `@if` om te bepalen of een categorie gewichtsklassen heeft, of direct de judoka's toont.
6.  **"Alles dichtklappen"-knop:** Een methode (`closeAllAccordions()`) die alle waarden in `openStates` naar `false` zet.
7.  **Initiële staat:** `openStates` begint als een leeg object, waardoor alles standaard dicht is.

**Plaats de volgende code ter vervanging van de regels 572-701 in `resources/views/pages/publiek/index.blade.php`.**

```blade
{{-- START Deelnemers-tab (replaces lines 572-701) --}}
{{-- 
    Dit Alpine.js x-data blok beheert de open/dicht-staat van alle accordions.
    Het is essentieel dat dit blok de root is voor de deelnemerslijst.
    Als er al een groter x-data blok voor de hele pagina/tab bestaat,
    moeten de 'openStates', 'isExpanded', 'toggle' en 'closeAllAccordions'
    methoden daar aan worden toegevoegd.
    De 'searchQuery' en 'judokaMatches' zijn placeholders; pas aan indien deze
    elders worden beheerd in een parent x-data.
--}}
<div x-data="{
    // Object om de open/dicht-staat van elke accordion bij te houden.
    // Sleutels zijn unieke identifiers (bijv. 'category_1' of 'weightclass_1_101').
    // Waarden zijn booleans (true = open, false = dicht).
    openStates: {}, 

    // Placeholder voor zoekfunctionaliteit; integreer met bestaande logica.
    searchQuery: '', 
    judokaMatches: [], 
    // isSearching: false, // indien nodig voor zoekstatus

    // Controleert of een accordion item open is.
    isExpanded(key) {
        // Standaard gesloten als de sleutel niet bestaat of false is.
        return this.openStates[key] === true;
    },

    // Schakelt de open/dicht-staat van een accordion item om.
    toggle(key) {
        this.openStates[key] = !this.openStates[key];
    },

    // Sluit alle open categorieën en gewichtsklassen.
    closeAllAccordions() {
        // Itereer over alle sleutels en zet de staat op 'false'.
        // Dit zorgt ervoor dat Alpine de veranderingen opmerkt en de UI bijwerkt.
        for (const key in this.openStates) {
            if (Object.prototype.hasOwnProperty.call(this.openStates, key)) {
                this.openStates[key] = false;
            }
        }
    },
}" id="deelnemers-accordions-container"> {{-- Unieke ID voor de hoofdcontainer --}}

    {{-- "Alles dichtklappen" knop en eventuele zoekbalk --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button type="button" @click="closeAllAccordions()" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chevron-up me-2"></i> Alles dichtklappen
        </button>
        {{-- Voeg hier eventueel de zoekbalk in als deze bij deze component hoort --}}
        {{-- <input type="text" x-model="searchQuery" placeholder="Zoek judoka..." class="form-control w-auto ms-auto"> --}}
    </div>

    {{-- Zoekresultaten (indien geactiveerd en gevuld) --}}
    {{-- Pas deze sectie aan om je bestaande zoekresultaten te tonen --}}
    <div x-show="judokaMatches.length > 0 && searchQuery.length > 0" class="mb-4">
        <h4 class="mb-2">Zoekresultaten voor "<span x-text="searchQuery"></span>":</h4>
        <ul class="list-group">
            <template x-for="judoka in judokaMatches" :key="judoka.id"> {{-- Vereist een 'id' op je judoka-object --}}
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span x-text="judoka.naam"></span>
                    {{-- Voeg hier meer details toe, bijv. club, gewichtsklasse --}}
                    {{-- <span x-text="judoka.club"></span> --}}
                </li>
            </template>
        </ul>
        <hr> {{-- Separator tussen zoekresultaten en accordions --}}
    </div>

    {{-- Hoofdloop voor categorieën (bijv. 'Senioren Heren', 'Jeugd Gemengd') --}}
    @forelse ($categories as $category)
        @php
            // Genereer een unieke ID voor de categorie voor Alpine's openStates.
            // Gebruik $category->id als deze betrouwbaar uniek is, anders Str::slug($category->name).
            $categoryId = $category->id ?? \Illuminate\Support\Str::slug($category->name);
            $categoryKey = 'category_' . $categoryId;

            // Bepaal of deze categorie gewichtsklassen heeft.
            // Pas de conditie aan op basis van de daadwerkelijke structuur van je $category object.
            // Bijv: $category->has_weight_classes of $category->gewichtsklassen->isNotEmpty()
            $hasWeightClasses = isset($category->weightClasses) && $category->weightClasses->isNotEmpty();
        @endphp

        <div class="card border mb-2 shadow-sm rounded">
            {{-- Categorie header --}}
            <div class="card-header p-0 bg-light" :id="'heading-{{ $categoryId }}'">
                <h2 class="mb-0">
                    <button class="btn btn-link btn-block text-left py-2 px-3 d-flex justify-content-between align-items-center w-100 text-decoration-none text-dark"
                            type="button"
                            @click="toggle('{{ $categoryKey }}')"
                            :aria-expanded="isExpanded('{{ $categoryKey }}') ? 'true' : 'false'"
                            :aria-controls="'collapse-{{ $categoryId }}'">
                        <span>
                            {{ $category->name }}
                            ({{ 
                                $hasWeightClasses 
                                ? $category->weightClasses->sum(fn($wc) => $wc->judokas->count()) 
                                : ($category->judokas->count() ?? 0) 
                            }} judoka's)
                        </span>
                        <i class="fas fs-6" :class="isExpanded('{{ $categoryKey }}') ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                </h2>
            </div>

            {{-- Categorie content (ingeklapt/uitgeklapt) --}}
            <div :id="'collapse-{{ $categoryId }}'"
                 x-show="isExpanded('{{ $categoryKey }}')"
                 x-collapse.duration.300ms {{-- Alpine's collapse plugin met duur --}}
                 class="collapse" {{-- Boostrap class (display: none door x-show) --}}
                 :aria-labelledby="'heading-{{ $categoryId }}'">
                <div class="card-body p-0">

                    @if ($hasWeightClasses)
                        {{-- DEEP NESTED: Gewichtsklasse accordions binnen de categorie --}}
                        @foreach ($category->weightClasses as $weightClass)
                            @php
                                // Genereer een unieke ID voor de gewichtsklasse.
                                $weightClassId = $weightClass->id ?? \Illuminate\Support\Str::slug($weightClass->name);
                                $weightClassKey = 'weightclass_' . $categoryId . '_' . $weightClassId;
                            @endphp
                            <div class="card border-0 border-top mt-1 mx-2 bg-white rounded shadow-sm">
                                {{-- Gewichtsklasse header --}}
                                <div class="card-header p-0 bg-white" :id="'heading-{{ $categoryId }}-{{ $weightClassId }}'">
                                    <h3 class="mb-0">
                                        <button class="btn btn-link btn-block text-left py-2 px-3 d-flex justify-content-between align-items-center w-100 text-decoration-none text-muted"
                                                type="button"
                                                @click="toggle('{{ $weightClassKey }}')"
                                                :aria-expanded="isExpanded('{{ $weightClassKey }}') ? 'true' : 'false'"
                                                :aria-controls="'collapse-{{ $categoryId }}-{{ $weightClassId }}'">
                                            <span>{{ $weightClass->name }} ({{ $weightClass->judokas->count() }} judoka's)</span>
                                            <i class="fas fs-6" :class="isExpanded('{{ $weightClassKey }}') ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                        </button>
                                    </h3>
                                </div>

                                {{-- Gewichtsklasse content --}}
                                <div :id="'collapse-{{ $categoryId }}-{{ $weightClassId }}'"
                                     x-show="isExpanded('{{ $weightClassKey }}')"
                                     x-collapse.duration.200ms {{-- Iets sneller inklappen --}}
                                     class="collapse"
                                     :aria-labelledby="'heading-{{ $categoryId }}-{{ $weightClassId }}'">
                                    <div class="card-body p-2 bg-light">
                                        {{-- Lijst van judoka's in deze gewichtsklasse --}}
                                        <ul class="list-group list-group-flush border-0">
                                            @forelse ($weightClass->judokas as $judoka)
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 border-0 bg-light">
                                                    <span>{{ $judoka->naam }} <small class="text-muted">({{ $judoka->club ?? 'Onbekend' }})</small></span>
                                                    {{-- Voeg hier andere judoka details toe --}}
                                                </li>
                                            @empty
                                                <li class="list-group-item py-1 px-2 border-0 bg-light text-muted">Geen judoka's in deze gewichtsklasse.</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        {{-- Platte lijst van judoka's voor dynamische/variabele categorieën --}}
                        <div class="card-body p-2 bg-light rounded-bottom">
                            <ul class="list-group list-group-flush border-0">
                                @forelse ($category->judokas as $judoka) {{-- Aanname dat judoka's direct onder $category staan --}}
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 border-0 bg-light">
                                        <span>{{ $judoka->naam }} <small class="text-muted">({{ $judoka->club ?? 'Onbekend' }})</small></span>
                                        {{-- Voeg hier andere judoka details toe --}}
                                    </li>
                                @empty
                                    <li class="list-group-item py-1 px-2 border-0 bg-light text-muted">Geen judoka's in deze categorie.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    @empty
        <div class="alert alert-info" role="alert">
            Geen deelnemers gevonden.
        </div>
    @endforelse

</div>
{{-- EINDE Deelnemers-tab --}}
```

**Belangrijke aandachtspunten voor implementatie:**

1.  **Alpine.js en `x-collapse` installatie:** Zorg ervoor dat Alpine.js en de `x-collapse` plugin correct zijn geïnstalleerd en geladen op de pagina. Voor een CSP-build moet dit via een bundler gebeuren.
2.  **CSS:** De meegeleverde code gebruikt Bootstrap 5-klassen (`card`, `btn`, `shadow-sm`, `rounded`, `list-group`, `d-flex`, etc.) en Font Awesome 6 (`fas`, `me-2`, `fs-6`). Zorg ervoor dat deze CSS-frameworks geladen zijn. Er is wat lichte styling toegevoegd om de geneste accordions visueel te scheiden.
3.  **Data Structuur (`$categories`):**
    *   De code gaat ervan uit dat `$categories` een collectie is.
    *   Elke `$category` moet ten minste een `name` en bij voorkeur een unieke `id` hebben.
    *   `$category->weightClasses` moet een collectie zijn (ook als die leeg is) of `null`/`undefined`. Gebruik `->isNotEmpty()` of `count() > 0` om te controleren.
    *   Als een categorie geen gewichtsklassen heeft, moet `$category->judokas` de directe judoka's bevatten.
    *   Elke `$weightClass` moet ten minste een `name` en bij voorkeur een unieke `id` hebben, en een `judokas` collectie.
    *   Elke `$judoka` moet een `naam` en optioneel een `club` hebben.
4.  **Zoekfunctionaliteit:** De `searchQuery` en `judokaMatches` zijn als placeholders opgenomen. Als je bestaande zoeklogica al in een Alpine `x-data` op de pagina zit, moet je deze nieuwe `x-data` daarbinnen plaatsen óf de `openStates` logica combineren met de bestaande zoek `x-data`. Zorg ervoor dat de zoekfunctionaliteit de `openStates` niet direct beïnvloedt, maar dat de zoekresultaten apart worden getoond.

Deze oplossing moet robuuster zijn tegen de eerder waargenomen Alpine/CSP-bugs en voldoet aan alle gestelde eisen.