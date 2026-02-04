@extends('layouts.app')

@section('title', 'Mijn Clubs - ' . $organisator->naam)

@section('content')
<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Mijn Clubs</h1>
            <p class="text-gray-500">Clubs blijven bewaard en kunnen voor elk toernooi uitgenodigd worden</p>
        </div>
        <a href="{{ request('back') ?? route('organisator.dashboard', $organisator) }}" class="text-blue-600 hover:text-blue-800">
            &larr; Terug
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Add Club Form --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Club Toevoegen</h2>
        <form action="{{ route('organisator.clubs.store', $organisator) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            @if(request('back'))
                <input type="hidden" name="back" value="{{ request('back') }}">
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Naam *</label>
                <input type="text" name="naam" required class="w-full border rounded px-3 py-2" placeholder="Budoschool naam">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" class="w-full border rounded px-3 py-2" placeholder="info@budoschool.nl">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plaats</label>
                <input type="text" name="plaats" class="w-full border rounded px-3 py-2" placeholder="Amsterdam">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contactpersoon</label>
                <input type="text" name="contact_naam" class="w-full border rounded px-3 py-2" placeholder="Jan Jansen">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Telefoon</label>
                <input type="text" name="telefoon" class="w-full border rounded px-3 py-2" placeholder="06-12345678">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                <input type="text" name="website" class="w-full border rounded px-3 py-2" placeholder="https://budoschool.nl">
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Club Toevoegen
                </button>
            </div>
        </form>
    </div>

    {{-- Clubs List --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Mijn Clubs ({{ $clubs->count() }})</h2>
        </div>

        @if($clubs->isEmpty())
            <div class="p-6 text-center text-gray-500">
                Je hebt nog geen clubs toegevoegd. Voeg hierboven je eerste club toe.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full table-fixed">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-[15%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                            <th class="w-[10%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plaats</th>
                            <th class="w-[10%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="w-[18%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="w-[12%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefoon</th>
                            <th class="w-[15%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Website</th>
                            <th class="w-[8%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                            <th class="w-[12%] px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($clubs as $club)
                            <tr x-data="{ editing: false }">
                                {{-- Naam --}}
                                <td class="px-4 py-3">
                                    <template x-if="!editing">
                                        <span class="font-medium truncate block">{{ $club->naam }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input form="edit-club-{{ $club->id }}" type="text" name="naam" value="{{ $club->naam }}" required class="w-full border rounded px-2 py-1 text-sm">
                                    </template>
                                </td>
                                {{-- Plaats --}}
                                <td class="px-4 py-3">
                                    <template x-if="!editing">
                                        <span class="text-gray-600 truncate block">{{ $club->plaats ?? '-' }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input form="edit-club-{{ $club->id }}" type="text" name="plaats" value="{{ $club->plaats }}" class="w-full border rounded px-2 py-1 text-sm">
                                    </template>
                                </td>
                                {{-- Contact --}}
                                <td class="px-4 py-3">
                                    <template x-if="!editing">
                                        <span class="text-gray-600 truncate block">{{ $club->contact_naam ?? '-' }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input form="edit-club-{{ $club->id }}" type="text" name="contact_naam" value="{{ $club->contact_naam }}" class="w-full border rounded px-2 py-1 text-sm">
                                    </template>
                                </td>
                                {{-- Email --}}
                                <td class="px-4 py-3">
                                    <template x-if="!editing">
                                        <span class="text-gray-600 truncate block">{{ $club->email ?? '-' }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input form="edit-club-{{ $club->id }}" type="email" name="email" value="{{ $club->email }}" class="w-full border rounded px-2 py-1 text-sm">
                                    </template>
                                </td>
                                {{-- Telefoon --}}
                                <td class="px-4 py-3">
                                    <template x-if="!editing">
                                        <span class="text-gray-600 truncate block">{{ $club->telefoon ?? '-' }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input form="edit-club-{{ $club->id }}" type="text" name="telefoon" value="{{ $club->telefoon }}" class="w-full border rounded px-2 py-1 text-sm">
                                    </template>
                                </td>
                                {{-- Website --}}
                                <td class="px-4 py-3">
                                    <template x-if="!editing">
                                        <span class="truncate block">
                                            @if($club->website)
                                                <a href="{{ $club->website }}" target="_blank" class="text-blue-600 hover:underline">{{ Str::limit($club->website, 20) }}</a>
                                            @else
                                                <span class="text-gray-600">-</span>
                                            @endif
                                        </span>
                                    </template>
                                    <template x-if="editing">
                                        <input form="edit-club-{{ $club->id }}" type="url" name="website" value="{{ $club->website }}" class="w-full border rounded px-2 py-1 text-sm">
                                    </template>
                                </td>
                                {{-- Judoka's --}}
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">{{ $club->judokas_count }}</span>
                                </td>
                                {{-- Acties --}}
                                <td class="px-4 py-3 text-right">
                                    <template x-if="!editing">
                                        <div class="space-x-2">
                                            <button @click="editing = true" class="text-blue-600 hover:text-blue-800 text-sm">Bewerken</button>
                                            @if($club->judokas_count === 0)
                                                <form action="{{ route('organisator.clubs.destroy', [$organisator, $club]) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('Weet je zeker dat je deze club wilt verwijderen?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if(request('back'))
                                                        <input type="hidden" name="back" value="{{ request('back') }}">
                                                    @endif
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Verwijderen</button>
                                                </form>
                                            @endif
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <div class="space-x-2">
                                            <button type="submit" form="edit-club-{{ $club->id }}" class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1 rounded">Opslaan</button>
                                            <button type="button" @click="editing = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 text-sm px-3 py-1 rounded">Annuleren</button>
                                        </div>
                                    </template>
                                    {{-- Hidden form for edit --}}
                                    <form id="edit-club-{{ $club->id }}" action="{{ route('organisator.clubs.update', [$organisator, $club]) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('PUT')
                                        @if(request('back'))
                                            <input type="hidden" name="back" value="{{ request('back') }}">
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
