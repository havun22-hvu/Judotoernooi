@extends('layouts.app')

@section('title', 'Scoreboard Errors')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Scoreboard Errors</h1>
        <p class="text-gray-500 mt-1">Foutmeldingen ontvangen van de JudoScoreBoard app</p>
    </div>
    <a href="{{ route('admin.index') }}" class="text-blue-600 hover:text-blue-800">
        &larr; Terug naar Dashboard
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</div>
        <div class="text-sm text-gray-500">Totaal (laatste 500)</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-red-600">{{ $stats['fatal'] }}</div>
        <div class="text-sm text-gray-500">Fataal</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-orange-500">{{ $stats['today'] }}</div>
        <div class="text-sm text-gray-500">Vandaag</div>
    </div>
</div>

@if($logs->isEmpty())
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen foutmeldingen ontvangen.
    </div>
@else
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tijdstip</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Scherm</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Device</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Versie</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Melding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($logs as $log)
                <tr class="hover:bg-gray-50 {{ $log->fatal ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                        {{ $log->created_at->format('d-m H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($log->fatal)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">CRASH</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">fout</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $log->screen ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        @if($log->deviceToegang)
                            Mat {{ $log->deviceToegang->mat_nummer }}
                        @else
                            {{ $log->device ?? '—' }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->app_version ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-800 max-w-md">
                        <div class="truncate" title="{{ $log->message }}">{{ $log->message }}</div>
                        @if($log->stack)
                            <details class="mt-1">
                                <summary class="text-xs text-blue-600 cursor-pointer">Stack trace</summary>
                                <pre class="mt-1 text-xs text-gray-500 whitespace-pre-wrap break-all">{{ $log->stack }}</pre>
                            </details>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
