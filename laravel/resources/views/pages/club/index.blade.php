@extends('layouts.app')

@section('title', 'Clubs Beheren')

@section('content')
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
    <form action="{{ route('toernooi.club.store', $toernooi) }}" method="POST" class="grid grid-cols-4 gap-4">
        @csrf
        <input type="text" name="naam" placeholder="Clubnaam *" required
               class="border rounded px-3 py-2 @error('naam') border-red-500 @enderror">
        <input type="email" name="email" placeholder="Email"
               class="border rounded px-3 py-2">
        <input type="text" name="contact_naam" placeholder="Contactpersoon"
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
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
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
                    <td class="px-4 py-3 text-gray-600">{{ $club->email ?? '-' }}</td>
                </template>
                <template x-if="!editing">
                    <td class="px-4 py-3 text-gray-600">{{ $club->contact_naam ?? '-' }}</td>
                </template>

                <!-- Edit view -->
                <template x-if="editing">
                    <td colspan="3" class="px-4 py-3">
                        <form action="{{ route('toernooi.club.update', [$toernooi, $club]) }}" method="POST" class="flex space-x-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="naam" value="{{ $club->naam }}" class="border rounded px-2 py-1 w-32">
                            <input type="email" name="email" value="{{ $club->email }}" placeholder="Email" class="border rounded px-2 py-1 w-40">
                            <input type="text" name="contact_naam" value="{{ $club->contact_naam }}" placeholder="Contact" class="border rounded px-2 py-1 w-32">
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
                    <div class="flex space-x-2">
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
                        <a href="{{ route('coach.portal', $uitnodiging->token) }}" target="_blank"
                           class="text-purple-600 hover:text-purple-800 text-sm">
                            Link
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
@endsection
