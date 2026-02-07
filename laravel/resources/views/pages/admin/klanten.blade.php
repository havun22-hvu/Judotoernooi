@extends('layouts.app')

@section('title', __('Havun Admin - Klantenbeheer'))

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Klantenbeheer') }}</h1>
        <p class="text-gray-500 mt-1">{{ __('Beheer alle organisatoren en hun gegevens') }}</p>
    </div>
    <a href="{{ route('admin.index') }}" class="text-blue-600 hover:text-blue-800">
        &larr; {{ __('Terug naar Dashboard') }}
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
    {{ session('error') }}
</div>
@endif

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Organisator') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Contact') }}</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Toernooien') }}</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Laatste login') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Acties') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($klanten as $klant)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">{{ $klant->naam }}</div>
                    @if($klant->organisatie_naam && $klant->organisatie_naam !== $klant->naam)
                        <div class="text-sm text-gray-500">{{ $klant->organisatie_naam }}</div>
                    @endif
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900">{{ $klant->email }}</div>
                    @if($klant->telefoon)
                        <div class="text-sm text-gray-500">{{ $klant->telefoon }}</div>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="font-medium text-blue-600">{{ $klant->toernooien_count }}</span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex justify-center gap-1 flex-wrap">
                        @if($klant->is_test)
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">{{ __('Test') }}</span>
                        @endif
                        @if($klant->kortingsregeling)
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">{{ __('Korting') }}</span>
                        @endif
                        @if($klant->kyc_compleet)
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">KYC</span>
                        @endif
                        @if(!$klant->is_test && !$klant->kortingsregeling && !$klant->kyc_compleet)
                            <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </div>
                </td>
                <td class="px-6 py-4 text-sm">
                    @if($klant->laatste_login)
                        <span class="{{ $klant->laatste_login->diffInDays() > 30 ? 'text-orange-600' : 'text-gray-600' }}">
                            {{ $klant->laatste_login->diffForHumans() }}
                        </span>
                    @else
                        <span class="text-gray-400">{{ __('Nooit') }}</span>
                    @endif
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.klanten.edit', $klant) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            {{ __('Bewerken') }}
                        </a>
                        <form action="{{ route('admin.klanten.destroy', $klant) }}" method="POST"
                              onsubmit="return confirm('Weet je zeker dat je {{ addslashes($klant->naam) }} wilt verwijderen?\n\n{{ $klant->toernooien_count }} toernooi(en), {{ $klant->clubs_count }} club(s)\n\nALLE data wordt permanent verwijderd!')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                    {{ __('Geen klanten gevonden') }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
