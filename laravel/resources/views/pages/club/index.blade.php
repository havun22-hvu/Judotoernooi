@extends('layouts.app')

@section('title', 'Clubs Beheren')

@section('content')
<div x-data="{ copiedUrl: null, editingClub: null }">

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Clubs & Uitnodigingen</h1>
    <form action="{{ route('toernooi.club.verstuur-alle', $toernooi) }}" method="POST" class="inline"
          onsubmit="return confirm('Weet je zeker dat je alle clubs wilt uitnodigen?')">
        @csrf
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Alle Uitnodigen
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
<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm">
    <strong>Deadline:</strong> {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}
    @if($toernooi->isInschrijvingOpen())
    <span class="text-green-600 font-medium">(open)</span>
    @else
    <span class="text-red-600 font-medium">(gesloten)</span>
    @endif
</div>
@endif

<!-- Nieuwe club toevoegen -->
<div class="bg-white rounded-lg shadow p-4 mb-4">
    <form action="{{ route('toernooi.club.store', $toernooi) }}" method="POST" class="flex flex-wrap gap-2 items-center">
        @csrf
        <input type="text" name="naam" placeholder="Clubnaam *" required
               class="border rounded px-3 py-2 w-40 @error('naam') border-red-500 @enderror">
        <input type="email" name="email" placeholder="Email 1" class="border rounded px-3 py-2 w-48">
        <input type="email" name="email2" placeholder="Email 2" class="border rounded px-3 py-2 w-48">
        <input type="text" name="contact_naam" placeholder="Contactpersoon" class="border rounded px-3 py-2 w-36">
        <input type="tel" name="telefoon" placeholder="Telefoon" class="border rounded px-3 py-2 w-32">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
            + Toevoegen
        </button>
    </form>
</div>

<!-- Clubs lijst -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    @forelse($clubs->sortBy('naam') as $club)
    @php $uitnodiging = $uitnodigingen[$club->id] ?? null; @endphp
    <div class="border-b last:border-b-0 hover:bg-gray-50" x-data="{ showEdit: false }">
        <!-- Normale weergave -->
        <div class="flex items-center gap-4 px-4 py-3" x-show="!showEdit">
            <!-- Club naam & badges -->
            <div class="w-56 flex-shrink-0">
                <div class="font-semibold text-gray-800">{{ $club->naam }}</div>
                <div class="flex gap-1 mt-0.5">
                    <span class="px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">{{ $club->judokas_count }}</span>
                    @if($uitnodiging)
                        @if($uitnodiging->isGeregistreerd())
                        <span class="px-1.5 py-0.5 text-xs bg-green-100 text-green-700 rounded">Geregistreerd</span>
                        @else
                        <span class="px-1.5 py-0.5 text-xs bg-yellow-100 text-yellow-700 rounded">{{ $uitnodiging->uitgenodigd_op->format('d-m') }}</span>
                        @endif
                    @else
                        <span class="px-1.5 py-0.5 text-xs bg-gray-100 text-gray-500 rounded">Niet uitgenodigd</span>
                    @endif
                </div>
            </div>

            <!-- Contact info -->
            <div class="flex-1 text-sm text-gray-600 grid grid-cols-2 gap-x-4">
                <div class="truncate">
                    @if($club->email){{ $club->email }}@endif
                    @if($club->email2)<br>{{ $club->email2 }}@endif
                </div>
                <div class="truncate">
                    @if($club->contact_naam){{ $club->contact_naam }}@endif
                    @if($club->telefoon) <span class="text-gray-400">{{ $club->telefoon }}</span>@endif
                </div>
            </div>

            <!-- Acties -->
            <div class="flex gap-2 flex-shrink-0">
                <button @click="showEdit = true" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                    Bewerken
                </button>
                @if($club->email)
                <form action="{{ route('toernooi.club.verstuur', [$toernooi, $club]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                        {{ $uitnodiging ? 'Opnieuw' : 'Uitnodigen' }}
                    </button>
                </form>
                @endif
                @if($uitnodiging)
                <button
                    @click="navigator.clipboard.writeText('{{ route('coach.portal', $uitnodiging->token) }}'); copiedUrl = {{ $club->id }}; setTimeout(() => copiedUrl = null, 2000)"
                    class="px-3 py-1.5 rounded text-sm"
                    :class="copiedUrl === {{ $club->id }} ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200'"
                >
                    <span x-text="copiedUrl === {{ $club->id }} ? '✓ Gekopieerd' : 'Kopieer link'"></span>
                </button>
                <a href="{{ route('coach.portal', $uitnodiging->token) }}" target="_blank"
                   class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-sm">
                    Open
                </a>
                @else
                <a href="{{ route('toernooi.club.coach-url', [$toernooi, $club]) }}"
                   class="px-3 py-1.5 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded text-sm">
                    Maak link
                </a>
                @endif
            </div>
        </div>

        <!-- Edit form -->
        <div class="px-4 py-3 bg-blue-50" x-show="showEdit" x-cloak>
            <form action="{{ route('toernooi.club.update', [$toernooi, $club]) }}" method="POST" class="flex flex-wrap gap-2 items-center">
                @csrf
                @method('PUT')
                <input type="text" name="naam" value="{{ $club->naam }}" placeholder="Clubnaam" class="border rounded px-3 py-2 w-40">
                <input type="email" name="email" value="{{ $club->email }}" placeholder="Email 1" class="border rounded px-3 py-2 w-48">
                <input type="email" name="email2" value="{{ $club->email2 }}" placeholder="Email 2" class="border rounded px-3 py-2 w-48">
                <input type="text" name="contact_naam" value="{{ $club->contact_naam }}" placeholder="Contact" class="border rounded px-3 py-2 w-36">
                <input type="tel" name="telefoon" value="{{ $club->telefoon }}" placeholder="Telefoon" class="border rounded px-3 py-2 w-32">
                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Opslaan</button>
                <button type="button" @click="showEdit = false" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Annuleer</button>
            </form>
        </div>
    </div>
    @empty
    <div class="px-4 py-8 text-center text-gray-500">
        Nog geen clubs. Voeg hierboven een club toe.
    </div>
    @endforelse
</div>

</div>
@endsection
