@extends('layouts.app')

@section('title', 'Poules')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Poules ({{ $poules->total() }})</h1>
    <form action="{{ route('toernooi.poule.genereer', $toernooi) }}" method="POST">
        @csrf
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            🎯 Herindelen
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nr</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blok</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mat</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wedstrijden</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($poules as $poule)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ $poule->nummer }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('toernooi.poule.show', [$toernooi, $poule]) }}" class="text-blue-600 hover:text-blue-800">
                        {{ $poule->titel }}
                    </a>
                </td>
                <td class="px-4 py-3">{{ $poule->blok?->nummer ?? '-' }}</td>
                <td class="px-4 py-3">{{ $poule->mat?->nummer ?? '-' }}</td>
                <td class="px-4 py-3">{{ $poule->judokas_count }}</td>
                <td class="px-4 py-3">{{ $poule->aantal_wedstrijden }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    Nog geen poules. Genereer eerst de poule-indeling.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $poules->links() }}
</div>
@endsection
