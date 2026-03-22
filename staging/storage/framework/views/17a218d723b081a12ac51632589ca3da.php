
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name' => 'locatie', 'value' => '', 'placeholder' => null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['name' => 'locatie', 'value' => '', 'placeholder' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<div x-data="locationAutocomplete_<?php echo e($name); ?>()" class="relative">
    <div class="flex gap-2">
        <div class="flex-1 relative">
            <input type="text"
                   name="<?php echo e($name); ?>"
                   id="<?php echo e($name); ?>"
                   x-model="query"
                   @input.debounce.300ms="search()"
                   @focus="showResults = results.length > 0"
                   @click.away="showResults = false"
                   placeholder="<?php echo e($placeholder ?? __('Zoek adres...')); ?>"
                   autocomplete="off"
                   <?php echo e($attributes->merge(['class' => 'w-full border rounded px-3 py-2 pr-10'])); ?>>
            
            <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        
        <a x-show="query && query.length > 3"
           x-cloak
           :href="'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(query)"
           target="_blank"
           class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded flex items-center gap-1 text-sm whitespace-nowrap"
           title="<?php echo e(__('Route plannen')); ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <?php echo e(__('Route')); ?>

        </a>
    </div>

    
    <div x-show="showResults && results.length > 0"
         x-cloak
         class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
        <template x-for="(result, index) in results" :key="index">
            <button type="button"
                    @click="selectResult(result)"
                    class="w-full px-4 py-2 text-left hover:bg-blue-50 border-b last:border-b-0 text-sm">
                <span x-text="result.display_name" class="block truncate"></span>
            </button>
        </template>
    </div>

    
    <div x-show="showResults && results.length === 0 && query.length >= 3 && !loading"
         x-cloak
         class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg p-3 text-sm text-gray-500">
        <?php echo e(__('Geen resultaten gevonden')); ?>

    </div>
</div>

<script>
function locationAutocomplete_<?php echo e($name); ?>() {
    return {
        query: '<?php echo e(addslashes($value)); ?>',
        results: [],
        showResults: false,
        loading: false,

        async search() {
            if (this.query.length < 3) {
                this.results = [];
                this.showResults = false;
                return;
            }

            this.loading = true;
            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.query)}&countrycodes=nl,be,de&limit=5&addressdetails=1`,
                    {
                        headers: {
                            'Accept-Language': 'nl'
                        }
                    }
                );
                this.results = await response.json();
                this.showResults = true;
            } catch (error) {
                console.error('Location search error:', error);
                this.results = [];
            }
            this.loading = false;
        },

        selectResult(result) {
            this.query = result.display_name;
            this.showResults = false;
            this.results = [];
        }
    };
}
</script>
<?php /**PATH /var/www/judotoernooi/staging/resources/views/components/location-autocomplete.blade.php ENDPATH**/ ?>