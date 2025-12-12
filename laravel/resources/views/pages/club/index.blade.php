@extends('layouts.app')

@section('title', 'Clubs Beheren')

@section('content')
<div x-data="{ copiedUrl: null, openClub: null }">

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

{{-- New coach modal --}}
@if(session('new_coach_pin'))
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4" onclick="event.stopPropagation()">
        <h3 class="text-lg font-bold mb-4 text-green-600">Coach Toegevoegd!</h3>
        <div class="space-y-3">
            <div>
                <label class="text-sm text-gray-600">PIN code:</label>
                <div class="text-2xl font-mono font-bold tracking-widest">{{ session('new_coach_pin') }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-600">Portal URL:</label>
                <div class="flex gap-2 mt-1">
                    <input type="text" value="{{ session('new_coach_url') }}" readonly
                           class="flex-1 border rounded px-3 py-2 text-sm font-mono bg-gray-50" id="new-coach-url">
                    <button onclick="navigator.clipboard.writeText(document.getElementById('new-coach-url').value); this.textContent='✓'"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">
                        Kopieer
                    </button>
                </div>
            </div>
        </div>
        <p class="mt-4 text-sm text-gray-500">Bewaar deze gegevens! De PIN wordt niet meer getoond.</p>
        <button onclick="this.closest('.fixed').remove()" class="mt-4 w-full bg-gray-200 hover:bg-gray-300 py-2 rounded">
            Sluiten
        </button>
    </div>
</div>
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
    @php
        $uitnodiging = $uitnodigingen[$club->id] ?? null;
        $coaches = $club->coaches ?? collect();
        $eersteCoach = $coaches->first();
    @endphp
    <div class="border-b last:border-b-0">
        <!-- Club row -->
        <div class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 cursor-pointer" @click="openClub = openClub === {{ $club->id }} ? null : {{ $club->id }}">
            <!-- Expand icon -->
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-90': openClub === {{ $club->id }} }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>

            <!-- Club naam & badges -->
            <div class="w-48 flex-shrink-0">
                <div class="font-semibold text-gray-800">{{ $club->naam }}</div>
                <div class="flex gap-1 mt-0.5">
                    <span class="px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">{{ $club->judokas_count }} judoka's</span>
                    <span class="px-1.5 py-0.5 text-xs bg-purple-100 text-purple-700 rounded">{{ $coaches->count() }}/3 coaches</span>
                </div>
            </div>

            <!-- Eerste coach info -->
            <div class="flex-1 text-sm text-gray-600">
                @if($eersteCoach)
                <span class="font-medium">{{ $eersteCoach->naam }}</span>
                @if($eersteCoach->email) <span class="text-gray-400 ml-2">{{ $eersteCoach->email }}</span> @endif
                @else
                <span class="text-gray-400 italic">Geen coaches</span>
                @endif
            </div>

            <!-- Quick actions -->
            <div class="flex gap-2 flex-shrink-0" @click.stop>
                @if($eersteCoach)
                <button
                    @click="navigator.clipboard.writeText('{{ $eersteCoach->getPortalUrl() }}'); copiedUrl = {{ $eersteCoach->id }}; setTimeout(() => copiedUrl = null, 2000)"
                    class="px-3 py-1.5 rounded text-sm"
                    :class="copiedUrl === {{ $eersteCoach->id }} ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200'"
                >
                    <span x-text="copiedUrl === {{ $eersteCoach->id }} ? '✓ Gekopieerd' : 'Kopieer link'"></span>
                </button>
                @endif
            </div>
        </div>

        <!-- Expanded club panel -->
        <div x-show="openClub === {{ $club->id }}" x-collapse class="bg-gray-50 border-t px-4 py-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Club gegevens -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Club Gegevens</h4>
                    <form action="{{ route('toernooi.club.update', [$toernooi, $club]) }}" method="POST" class="space-y-2">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="naam" value="{{ $club->naam }}" placeholder="Clubnaam" class="border rounded px-3 py-2 text-sm">
                            <input type="text" name="contact_naam" value="{{ $club->contact_naam }}" placeholder="Contactpersoon" class="border rounded px-3 py-2 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="email" name="email" value="{{ $club->email }}" placeholder="Email 1" class="border rounded px-3 py-2 text-sm">
                            <input type="email" name="email2" value="{{ $club->email2 }}" placeholder="Email 2" class="border rounded px-3 py-2 text-sm">
                        </div>
                        <div class="flex gap-2">
                            <input type="tel" name="telefoon" value="{{ $club->telefoon }}" placeholder="Telefoon" class="border rounded px-3 py-2 text-sm flex-1">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Opslaan</button>
                        </div>
                    </form>

                    @if($club->email)
                    <div class="mt-3 pt-3 border-t">
                        <form action="{{ route('toernooi.club.verstuur', [$toernooi, $club]) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                                {{ $uitnodiging ? 'Opnieuw uitnodigen' : 'Email uitnodiging versturen' }}
                            </button>
                        </form>
                        @if($uitnodiging)
                        <span class="text-sm text-gray-500 ml-2">
                            Laatst: {{ $uitnodiging->uitgenodigd_op->format('d-m-Y H:i') }}
                        </span>
                        @endif
                    </div>
                    @endif
                </div>

                <!-- Coaches -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Coaches (max 3)</h4>

                    <!-- Bestaande coaches -->
                    <div class="space-y-2 mb-3">
                        @forelse($coaches as $coach)
                        <div class="flex items-center gap-2 bg-white rounded border p-2">
                            <div class="flex-1">
                                <div class="font-medium text-sm">{{ $coach->naam }}</div>
                                <div class="text-xs text-gray-500">
                                    @if($coach->email){{ $coach->email }}@endif
                                    @if($coach->telefoon) · {{ $coach->telefoon }}@endif
                                </div>
                            </div>
                            <button
                                @click="navigator.clipboard.writeText('{{ $coach->getPortalUrl() }}'); copiedUrl = {{ $coach->id }}; setTimeout(() => copiedUrl = null, 2000)"
                                class="px-2 py-1 rounded text-xs"
                                :class="copiedUrl === {{ $coach->id }} ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            >
                                <span x-text="copiedUrl === {{ $coach->id }} ? '✓' : 'URL'"></span>
                            </button>
                            <form action="{{ route('toernooi.club.coach.regenerate-pin', [$toernooi, $coach]) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-2 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded text-xs"
                                        onclick="return confirm('Nieuwe PIN genereren voor {{ $coach->naam }}?')">
                                    Nieuwe PIN
                                </button>
                            </form>
                            <form action="{{ route('toernooi.club.coach.destroy', [$toernooi, $coach]) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded text-xs"
                                        onclick="return confirm('Coach {{ $coach->naam }} verwijderen?')">
                                    ×
                                </button>
                            </form>
                        </div>
                        @empty
                        <p class="text-sm text-gray-400 italic">Nog geen coaches</p>
                        @endforelse
                    </div>

                    <!-- Nieuwe coach toevoegen -->
                    @if($coaches->count() < 3)
                    <form action="{{ route('toernooi.club.coach.store', [$toernooi, $club]) }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="text" name="naam" placeholder="Coach naam *" required class="border rounded px-2 py-1 text-sm flex-1">
                        <input type="email" name="email" placeholder="Email" class="border rounded px-2 py-1 text-sm w-36">
                        <input type="tel" name="telefoon" placeholder="Tel" class="border rounded px-2 py-1 text-sm w-24">
                        <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm">+</button>
                    </form>
                    @else
                    <p class="text-sm text-orange-600">Maximum aantal coaches bereikt</p>
                    @endif
                </div>
            </div>
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
