@extends('layouts.app')

@section('title', 'Email Log')

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Email Log</h1>
            <p class="text-gray-600 mt-1">Overzicht van alle verstuurde emails voor {{ $toernooi->naam }}</p>
        </div>
        <a href="{{ route('toernooi.club.index', $toernooi->routeParams()) }}"
           class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
            &larr; Terug naar Clubs
        </a>
    </div>
</div>

@if($emails->isEmpty())
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
        </svg>
        <p class="text-lg">Nog geen emails verstuurd</p>
        <p class="text-sm mt-2">Verstuur uitnodigingen of correctie verzoeken om ze hier te zien.</p>
    </div>
@else
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum/tijd</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Club</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ontvangers</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Onderwerp</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($emails as $email)
                <tr class="{{ $email->isSuccessful() ? '' : 'bg-red-50' }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $email->created_at->format('d-m-Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full
                            {{ $email->type === 'uitnodiging' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $email->type === 'correctie' ? 'bg-orange-100 text-orange-800' : '' }}
                            {{ $email->type === 'herinnering' ? 'bg-purple-100 text-purple-800' : '' }}
                        ">
                            {{ $email->type_naam }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $email->club?->naam ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $email->recipients }}">
                        {{ $email->recipients }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $email->subject }}
                        @if($email->summary)
                            <br><span class="text-xs text-gray-400">{{ $email->summary }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($email->isSuccessful())
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Verzonden</span>
                        @else
                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full" title="{{ $email->error_message }}">
                                Mislukt
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-sm text-gray-500">
        Totaal: {{ $emails->count() }} email(s) |
        Verzonden: {{ $emails->where('status', 'sent')->count() }} |
        Mislukt: {{ $emails->where('status', 'failed')->count() }}
    </div>
@endif
@endsection
