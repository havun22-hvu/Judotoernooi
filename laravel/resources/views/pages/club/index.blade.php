@extends('layouts.app')

@section('title', 'Clubs Beheren')

@section('content')
<div x-data="{ copiedUrl: null, showUrlModal: false, modalUrl: '', modalClub: '' }">

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Clubs & Uitnodigingen</h1>
    <div class="flex space-x-2">
        <form action="{{ route('toernooi.club.verstuur-alle', $toernooi) }}" method="POST" class="inline"
              onsubmit="return confirm('Weet je zeker dat je alle clubs wilt uitnodigen?')">
            @csrf
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Alle Uitnodigen
            </button>
        </form>
    </div>
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
        <span class="text-green-600">(nog open)</span>
        @else
        <span class="text-red-600">(gesloten)</span>
        @endif
    </p>
</div>
@endif

<!-- Nieuwe club toevoegen -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-xl font-bold mb-4">Nieuwe Club Toevoegen</h2>
    <form action="{{ route('toernooi.club.store', $toernooi) }}" method="POST" class="grid grid-cols-6 gap-4">
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

<!-- Clubs lijst -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Club</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emails</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact / Tel</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($clubs as $club)
            @php $uitnodiging = $uitnodigingen[$club->id] ?? null; @endphp
            <tr class="hover:bg-gray-50" x-data="{ editing: false }">
                <!-- Normal view -->
                <template x-if="!editing">
                    <td class="px-4 py-3 font-medium">{{ $club->naam }}</td>
                </template>
                <template x-if="!editing">
                    <td class="px-4 py-3 text-gray-600 text-sm">
                        @if($club->email)
                        <div>{{ $club->email }}</div>
                        @endif
                        @if($club->email2)
                        <div>{{ $club->email2 }}</div>
                        @endif
                        @if(!$club->email && !$club->email2)
                        <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </template>
                <template x-if="!editing">
                    <td class="px-4 py-3 text-gray-600 text-sm">
                        @if($club->contact_naam)
                        <div>{{ $club->contact_naam }}</div>
                        @endif
                        @if($club->telefoon)
                        <div class="text-gray-500">{{ $club->telefoon }}</div>
                        @endif
                        @if(!$club->contact_naam && !$club->telefoon)
                        <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </template>

                <!-- Edit view -->
                <template x-if="editing">
                    <td colspan="3" class="px-4 py-3">
                        <form action="{{ route('toernooi.club.update', [$toernooi, $club]) }}" method="POST" class="flex flex-wrap gap-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="naam" value="{{ $club->naam }}" placeholder="Clubnaam" class="border rounded px-2 py-1 w-28">
                            <input type="email" name="email" value="{{ $club->email }}" placeholder="Email 1" class="border rounded px-2 py-1 w-36">
                            <input type="email" name="email2" value="{{ $club->email2 }}" placeholder="Email 2" class="border rounded px-2 py-1 w-36">
                            <input type="text" name="contact_naam" value="{{ $club->contact_naam }}" placeholder="Contact" class="border rounded px-2 py-1 w-28">
                            <input type="tel" name="telefoon" value="{{ $club->telefoon }}" placeholder="Telefoon" class="border rounded px-2 py-1 w-28">
                            <button type="submit" class="bg-green-500 text-white px-2 py-1 rounded text-sm">Opslaan</button>
                            <button type="button" @click="editing = false" class="bg-gray-300 px-2 py-1 rounded text-sm">Annuleer</button>
                        </form>
                    </td>
                </template>

                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs bg-gray-100 rounded-full">{{ $club->judokas_count }}</span>
                </td>

                <td class="px-4 py-3">
                    @if($uitnodiging)
                        @if($uitnodiging->isGeregistreerd())
                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Geregistreerd</span>
                        @else
                        <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                            Uitgenodigd {{ $uitnodiging->uitgenodigd_op->format('d-m') }}
                        </span>
                        @endif
                    @else
                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">Niet uitgenodigd</span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        <button @click="editing = !editing" class="text-blue-600 hover:text-blue-800 text-sm">
                            Bewerk
                        </button>

                        @if($club->email)
                        <form action="{{ route('toernooi.club.verstuur', [$toernooi, $club]) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-green-600 hover:text-green-800 text-sm">
                                {{ $uitnodiging ? 'Opnieuw' : 'Uitnodigen' }}
                            </button>
                        </form>
                        @endif

                        @if($uitnodiging)
                        <button
                            @click="navigator.clipboard.writeText('{{ route('coach.portal', $uitnodiging->token) }}'); copiedUrl = {{ $club->id }}; setTimeout(() => copiedUrl = null, 2000)"
                            class="text-sm"
                            :class="copiedUrl === {{ $club->id }} ? 'text-green-600' : 'text-purple-600 hover:text-purple-800'"
                        >
                            <span x-show="copiedUrl !== {{ $club->id }}">Kopieer link</span>
                            <span x-show="copiedUrl === {{ $club->id }}">Gekopieerd!</span>
                        </button>
                        <a href="{{ route('coach.portal', $uitnodiging->token) }}" target="_blank"
                           class="text-gray-500 hover:text-gray-700 text-sm" title="Open coach portal">
                            Open
                        </a>
                        @else
                        <a href="{{ route('toernooi.club.coach-url', [$toernooi, $club]) }}"
                           class="text-purple-600 hover:text-purple-800 text-sm">
                            Maak link
                        </a>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    Nog geen clubs. Voeg hierboven een club toe.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

</div>{{-- End x-data --}}
@endsection
