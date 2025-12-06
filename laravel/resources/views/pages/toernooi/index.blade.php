@extends('layouts.app')

@section('title', 'Toernooien')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Toernooien</h1>
    <a href="{{ route('toernooi.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        + Nieuw Toernooi
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organisatie</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($toernooien as $toernooi)
            <tr>
                <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $toernooi->naam }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $toernooi->datum->format('d-m-Y') }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $toernooi->organisatie }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    @if($toernooi->is_actief)
                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Actief</span>
                    @else
                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Inactief</span>
                    @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800 mr-3">Bekijk</a>
                    <a href="{{ route('toernooi.edit', $toernooi) }}" class="text-gray-600 hover:text-gray-800">Bewerk</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    Nog geen toernooien. <a href="{{ route('toernooi.create') }}" class="text-blue-600">Maak er een aan</a>.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $toernooien->links() }}
</div>
@endsection
