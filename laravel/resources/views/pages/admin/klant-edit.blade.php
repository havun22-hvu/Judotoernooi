@extends('layouts.app')

@section('title', 'Havun Admin - ' . $klant->naam . ' bewerken')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ $klant->naam }}</h1>
        <p class="text-gray-500 mt-1">Klantgegevens bewerken</p>
    </div>
    <a href="{{ route('admin.klanten') }}" class="text-blue-600 hover:text-blue-800">
        &larr; Terug naar Klanten
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Klantgegevens formulier --}}
    <div class="lg:col-span-2">
        <form action="{{ route('admin.klanten.update', $klant) }}" method="POST" class="bg-white rounded-lg shadow p-6">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold text-gray-800 mb-4">Basisgegevens</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Naam *</label>
                    <input type="text" name="naam" value="{{ old('naam', $klant->naam) }}" required
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $klant->email) }}" required
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefoon</label>
                    <input type="text" name="telefoon" value="{{ old('telefoon', $klant->telefoon) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website', $klant->website) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <h2 class="text-lg font-semibold text-gray-800 mb-4 mt-6">Facturatiegegevens (KYC)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Organisatie naam</label>
                    <input type="text" name="organisatie_naam" value="{{ old('organisatie_naam', $klant->organisatie_naam) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contactpersoon</label>
                    <input type="text" name="contactpersoon" value="{{ old('contactpersoon', $klant->contactpersoon) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">KVK nummer</label>
                    <input type="text" name="kvk_nummer" value="{{ old('kvk_nummer', $klant->kvk_nummer) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">BTW nummer</label>
                    <input type="text" name="btw_nummer" value="{{ old('btw_nummer', $klant->btw_nummer) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Straat + huisnummer</label>
                    <input type="text" name="straat" value="{{ old('straat', $klant->straat) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                    <input type="text" name="postcode" value="{{ old('postcode', $klant->postcode) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plaats</label>
                    <input type="text" name="plaats" value="{{ old('plaats', $klant->plaats) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Land</label>
                    <input type="text" name="land" value="{{ old('land', $klant->land ?? 'Nederland') }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Factuur email</label>
                    <input type="email" name="factuur_email" value="{{ old('factuur_email', $klant->factuur_email) }}"
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <h2 class="text-lg font-semibold text-gray-800 mb-4 mt-6">Status & Features</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <label class="flex items-center p-3 border rounded hover:bg-gray-50">
                    <input type="checkbox" name="is_test" value="1" {{ old('is_test', $klant->is_test) ? 'checked' : '' }}
                           class="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Test account</span>
                        <p class="text-xs text-gray-500">Geen betalingen vereist</p>
                    </div>
                </label>
                <label class="flex items-center p-3 border rounded hover:bg-gray-50">
                    <input type="checkbox" name="kortingsregeling" value="1" {{ old('kortingsregeling', $klant->kortingsregeling) ? 'checked' : '' }}
                           class="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Kortingsregeling</span>
                        <p class="text-xs text-gray-500">Speciale kortingstarieven</p>
                    </div>
                </label>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                    Opslaan
                </button>
            </div>
        </form>
    </div>

    {{-- Sidebar met statistieken --}}
    <div class="space-y-6">
        {{-- Statistieken --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Statistieken</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Toernooien</span>
                    <span class="font-medium">{{ $klant->toernooien_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Clubs</span>
                    <span class="font-medium">{{ $klant->clubs_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Templates</span>
                    <span class="font-medium">{{ $klant->toernooi_templates_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Klant sinds</span>
                    <span class="font-medium">{{ $klant->created_at?->format('d-m-Y') ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Laatste login</span>
                    <span class="font-medium {{ $klant->laatste_login && $klant->laatste_login->diffInDays() > 30 ? 'text-orange-600' : '' }}">
                        {{ $klant->laatste_login?->diffForHumans() ?? 'Nooit' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Quick links --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick links</h2>
            <div class="space-y-2">
                <a href="{{ route('organisator.dashboard', $klant) }}" class="block text-blue-600 hover:text-blue-800">
                    → Open dashboard
                </a>
                <a href="{{ route('organisator.clubs.index', $klant) }}" class="block text-blue-600 hover:text-blue-800">
                    → Bekijk clubs ({{ $klant->clubs_count }})
                </a>
                <a href="{{ route('organisator.templates.index', $klant) }}" class="block text-blue-600 hover:text-blue-800">
                    → Bekijk templates ({{ $klant->toernooi_templates_count }})
                </a>
            </div>
        </div>

        {{-- Recente toernooien --}}
        @if($klant->toernooien->count() > 0)
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recente toernooien</h2>
            <div class="space-y-3">
                @foreach($klant->toernooien->take(5) as $toernooi)
                <div class="border-b border-gray-100 pb-2 last:border-0">
                    <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                        {{ $toernooi->naam }}
                    </a>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $toernooi->datum?->format('d-m-Y') ?? '-' }} · {{ $toernooi->judokas_count }} judoka's
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Betalingen sectie --}}
<div class="mt-8">
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Toernooi betalingen</h2>
        </div>
        @if($betalingen->count() > 0)
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Toernooi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bedrag</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Betaald op</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($betalingen as $betaling)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-600">
                        {{ $betaling->created_at->format('d-m-Y H:i') }}
                    </td>
                    <td class="px-6 py-3">
                        @if($betaling->toernooi)
                            <a href="{{ route('toernooi.show', $betaling->toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                {{ $betaling->toernooi->naam }}
                            </a>
                        @else
                            <span class="text-gray-400 text-sm">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm">
                        {{ ucfirst($betaling->tier ?? '-') }}
                        @if($betaling->max_judokas)
                            <span class="text-gray-500">(max {{ $betaling->max_judokas }})</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-right font-medium">
                        &euro; {{ number_format($betaling->bedrag, 2, ',', '.') }}
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($betaling->status === 'paid')
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Betaald</span>
                        @elseif($betaling->status === 'open')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Open</span>
                        @elseif($betaling->status === 'expired')
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">Verlopen</span>
                        @elseif($betaling->status === 'failed')
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">Mislukt</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">{{ $betaling->status }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600">
                        {{ $betaling->betaald_op?->format('d-m-Y H:i') ?? '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-sm font-medium text-gray-700">
                        Totaal betaald ({{ $betalingen->where('status', 'paid')->count() }} betalingen)
                    </td>
                    <td class="px-6 py-3 text-right font-bold text-green-600">
                        &euro; {{ number_format($betalingen->where('status', 'paid')->sum('bedrag'), 2, ',', '.') }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        @else
        <div class="px-6 py-8 text-center text-gray-500">
            Nog geen betalingen gevonden voor deze klant
        </div>
        @endif
    </div>
</div>
@endsection
