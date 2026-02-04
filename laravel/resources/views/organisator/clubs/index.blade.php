@extends('layouts.app')

@section('title', 'Mijn Clubs - ' . $organisator->naam)

@section('content')
<div class="max-w-6xl mx-auto" x-data="clubsPage()">
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
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plaats</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefoon</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($clubs as $club)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">{{ $club->naam }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $club->plaats ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $club->contact_naam ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $club->email ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $club->telefoon ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">{{ $club->judokas_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button @click="openEdit({{ $club->id }}, {{ json_encode([
                                            'naam' => $club->naam,
                                            'plaats' => $club->plaats,
                                            'contact_naam' => $club->contact_naam,
                                            'email' => $club->email,
                                            'telefoon' => $club->telefoon,
                                            'website' => $club->website,
                                        ]) }})" class="text-blue-600 hover:text-blue-800 text-sm">
                                            Bewerken
                                        </button>
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="text-gray-400 hover:text-gray-600 px-1">⋮</button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded shadow-lg z-10 min-w-[140px]">
                                                <form action="{{ route('organisator.clubs.destroy', [$organisator, $club]) }}" method="POST"
                                                      onsubmit="return confirm('{{ $club->judokas_count > 0 ? "LET OP: Deze club heeft {$club->judokas_count} judoka(s). Deze worden ook verwijderd! Doorgaan?" : "Weet je zeker dat je deze club wilt verwijderen?" }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if(request('back'))
                                                        <input type="hidden" name="back" value="{{ request('back') }}">
                                                    @endif
                                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        Verwijderen
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Edit Modal --}}
    <div x-show="editModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div x-show="editModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="editModal = false"></div>

            <div x-show="editModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full">
                <form :action="'{{ route('organisator.clubs.update', [$organisator, '__ID__']) }}'.replace('__ID__', editClubId)" method="POST">
                    @csrf
                    @method('PUT')
                    @if(request('back'))
                        <input type="hidden" name="back" value="{{ request('back') }}">
                    @endif
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Club Bewerken</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naam *</label>
                                <input type="text" name="naam" x-model="editData.naam" required class="w-full border rounded px-3 py-2">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Plaats</label>
                                    <input type="text" name="plaats" x-model="editData.plaats" class="w-full border rounded px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contactpersoon</label>
                                    <input type="text" name="contact_naam" x-model="editData.contact_naam" class="w-full border rounded px-3 py-2">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" x-model="editData.email" class="w-full border rounded px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefoon</label>
                                    <input type="text" name="telefoon" x-model="editData.telefoon" class="w-full border rounded px-3 py-2">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                                <input type="url" name="website" x-model="editData.website" class="w-full border rounded px-3 py-2">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Opslaan
                        </button>
                        <button type="button" @click="editModal = false" class="mt-3 sm:mt-0 w-full sm:w-auto bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 border border-gray-300 rounded">
                            Annuleren
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function clubsPage() {
    return {
        editModal: false,
        editClubId: null,
        editData: {
            naam: '',
            plaats: '',
            contact_naam: '',
            email: '',
            telefoon: '',
            website: ''
        },
        openEdit(id, data) {
            this.editClubId = id;
            this.editData = { ...data };
            this.editModal = true;
        }
    }
}
</script>
@endsection
