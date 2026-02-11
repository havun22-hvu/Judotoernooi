@if($blokken->count() > 0)
<div class="bg-white rounded-xl shadow p-6">
    <h3 class="font-semibold text-lg mb-4">Dagprogramma</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ min($blokken->count(), 4) }} gap-4">
        @foreach($blokken as $tijdblok)
            <div class="bg-gray-50 rounded-lg p-4 border">
                <div class="font-bold text-blue-600 mb-2">{{ __('Blok') }} {{ $tijdblok->nummer }}</div>
                @if($tijdblok->weging_start && $tijdblok->weging_einde)
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600">Weging:</span>
                    <span class="font-medium">{{ \Carbon\Carbon::parse($tijdblok->weging_start)->format('H:i') }} - {{ \Carbon\Carbon::parse($tijdblok->weging_einde)->format('H:i') }}</span>
                </div>
                @endif
                @if($tijdblok->starttijd)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Start wedstrijden:</span>
                    <span class="font-medium text-green-600">{{ \Carbon\Carbon::parse($tijdblok->starttijd)->format('H:i') }}</span>
                </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
