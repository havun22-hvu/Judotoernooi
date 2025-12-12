@extends('layouts.app')

@section('title', 'Clubs Beheren')

@section('content')
<div x-data="{ copiedUrl: null, editingClub: null }">

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Clubs & Uitnodigingen</h1>
    <form action="{{ route('toernooi.club.verstuur-alle', $toernooi) }}" method="POST" class="inline"
          onsubmit="return confirm('Weet je zeker dat je alle clubs wilt uitnodigen?')">
        @csrf
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Alle Clubs Uitnodigen
        </button>
    </form>
</div>

{{-- Coach URL modal --}}
@if(session('coach_url'))
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full mx-4" onclick="event.stopPropagation()">
        <h3 class="text-lg font-bold mb-2">Coach Link voor {{ session('coach_url_club') }}</h3>
        <p class="text-gray-600 text-sm mb-4">Kopieer deze link en stuur hem naar de coach:</p>
        <div class="flex gap-2">
            <input type="text" value="{{ session('coach_url') }}" readonly
                   class="flex-1 border rounded px-3 py-2 text-sm font-mono bg-gray-50" id="coach-url-input">
            <button onclick="copyCoachUrl()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                Kopieer
            </button>
        </div>
        <div class="mt-4 flex justify-between">
            <a href="{{ session('coach_url') }}" target="_blank" class="text-blue-600 hover:underline text-sm">
                Open in nieuw tabblad
            </a>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700 text-sm">
                Sluiten
            </button>
        </div>
    </div>
</div>
<script>
function copyCoachUrl() {
    const input = document.getElementById('coach-url-input');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert('Link gekopieerd!');
}
</script>
@endif

@if($toernooi->inschrijving_deadline)
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <p class="text-blue-800">
        <strong>Inschrijving deadline:</strong> {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}
        @if($toernooi->isInschrijvingOpen())
        <span class="text-green-600 font-medium">(nog open)</span>
        @else
        <span class="text-red-600 font-medium">(gesloten)</span>
        @endif
    </p>
</div>
@endif

<!-- Nieuwe club toevoegen -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-xl font-bold mb-4">Nieuwe Club Toevoegen</h2>
    <form action="{{ route('toernooi.club.store', $toernooi) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        @csrf
        <input type="text" name="naam" placeholder="Clubnaam *" required
               class="border rounded px-3 py-2 @error('naam') border-red-500 @enderror">
        <input type="email" name="email" placeholder="Email 1"
               class="border rounded px-3 py-2">
        <input type="email" name="email2" placeholder="Email 2"
               class="border rounded px-3 py-2">
        <input type="text" name="contact_naam" placeholder="Contactpersoon"
               class="border rounded px-3 py-2">
        <input type="tel" name="telefoon" placeholder="Telefoon"
               class="border rounded px-3 py-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Toevoegen
        </button>
    </form>
</div>

<!-- Clubs grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($clubs as $club)
    @php $uitnodiging = $uitnodigingen[$club->id] ?? null; @endphp
    <div class="bg-white rounded-lg shadow overflow-hidden" x-data="{ showEdit: false }">
        <!-- Club header -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-bold text-lg text-gray-800">{{ $club->naam }}</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">
                            {{ $club->judokas_count }} judoka's
                        </span>
                        @if($uitnodiging)
                            @if($uitnodiging->isGeregistreerd())
                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded-full">Geregistreerd</span>
                            @else
                            <span class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                                Uitgenodigd {{ $uitnodiging->uitgenodigd_op->format('d-m') }}
                            </span>
                            @endif
                        @else
                            <span class="px-2 py-0.5 text-xs bg-gray-200 text-gray-600 rounded-full">Niet uitgenodigd</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Club info -->
        <div class="p-4 text-sm" x-show="!showEdit">
            @if($club->email || $club->email2)
            <div class="mb-2">
                <span class="text-gray-500">Email:</span>
                <div class="text-gray-700">
                    @if($club->email) <div>{{ $club->email }}</div> @endif
                    @if($club->email2) <div>{{ $club->email2 }}</div> @endif
                </div>
            </div>
            @endif
            @if($club->contact_naam || $club->telefoon)
            <div>
                <span class="text-gray-500">Contact:</span>
                <div class="text-gray-700">
                    @if($club->contact_naam) <span>{{ $club->contact_naam }}</span> @endif
                    @if($club->telefoon) <span class="text-gray-500">{{ $club->telefoon }}</span> @endif
                </div>
            </div>
            @endif
            @if(!$club->email && !$club->email2 && !$club->contact_naam && !$club->telefoon)
            <p class="text-gray-400 italic">Geen contactgegevens</p>
            @endif
        </div>

        <!-- Edit form -->
        <div class="p-4 bg-blue-50 border-t border-blue-200" x-show="showEdit" x-cloak>
            <form action="{{ route('toernooi.club.update', [$toernooi, $club]) }}" method="POST" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="text-xs text-gray-600">Clubnaam</label>
                    <input type="text" name="naam" value="{{ $club->naam }}" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-gray-600">Email 1</label>
                        <input type="email" name="email" value="{{ $club->email }}" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Email 2</label>
                        <input type="email" name="email2" value="{{ $club->email2 }}" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-gray-600">Contactpersoon</label>
                        <input type="text" name="contact_naam" value="{{ $club->contact_naam }}" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Telefoon</label>
                        <input type="tel" name="telefoon" value="{{ $club->telefoon }}" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded text-sm font-medium">
                        Opslaan
                    </button>
                    <button type="button" @click="showEdit = false" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded text-sm">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>

        <!-- Actions -->
        <div class="p-3 bg-gray-50 border-t flex flex-wrap gap-2" x-show="!showEdit">
            <button @click="showEdit = true" class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Bewerken
            </button>

            @if($club->email)
            <form action="{{ route('toernooi.club.verstuur', [$toernooi, $club]) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="flex items-center gap-1 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    {{ $uitnodiging ? 'Opnieuw uitnodigen' : 'Uitnodigen' }}
                </button>
            </form>
            @endif

            @if($uitnodiging)
            <button
                @click="navigator.clipboard.writeText('{{ route('coach.portal', $uitnodiging->token) }}'); copiedUrl = {{ $club->id }}; setTimeout(() => copiedUrl = null, 2000)"
                class="flex items-center gap-1 px-3 py-1.5 rounded text-sm transition-colors"
                :class="copiedUrl === {{ $club->id }} ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200'"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                </svg>
                <span x-text="copiedUrl === {{ $club->id }} ? 'Gekopieerd!' : 'Kopieer link'"></span>
            </button>
            <a href="{{ route('coach.portal', $uitnodiging->token) }}" target="_blank"
               class="flex items-center gap-1 px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Open
            </a>
            @else
            <a href="{{ route('toernooi.club.coach-url', [$toernooi, $club]) }}"
               class="flex items-center gap-1 px-3 py-1.5 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                Maak link
            </a>
            @endif
        </div>
    </div>
    @empty
    <div class="col-span-full bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Nog geen clubs. Voeg hierboven een club toe.
    </div>
    @endforelse
</div>

</div>{{-- End x-data --}}
@endsection
