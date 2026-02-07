@extends('layouts.print')

@section('title', __('Contactlijst Coaches'))

@section('content')
<table class="w-full text-sm">
    <thead>
        <tr class="bg-gray-200">
            <th class="p-2 text-left">{{ __('Club') }}</th>
            <th class="p-2 text-left">{{ __('Coach') }}</th>
            <th class="p-2 text-left">{{ __('Telefoon') }}</th>
            <th class="p-2 text-left">{{ __('Email') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($clubs as $club)
            @if($club->coaches->isEmpty())
            <tr class="bg-white">
                <td class="p-2 font-medium">{{ $club->naam }}</td>
                <td class="p-2 text-gray-400" colspan="3">{{ __('Geen coaches geregistreerd') }}</td>
            </tr>
            @else
                @foreach($club->coaches as $coach)
                <tr class="{{ $loop->parent->index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                    @if($loop->first)
                    <td class="p-2 font-medium" rowspan="{{ $club->coaches->count() }}">
                        {{ $club->naam }}
                        <span class="text-xs text-gray-500 block">
                            ({{ $club->judokas->where('toernooi_id', $toernooi->id)->count() }} judoka's)
                        </span>
                    </td>
                    @endif
                    <td class="p-2">{{ $coach->naam }}</td>
                    <td class="p-2 font-mono">{{ $coach->telefoon ?? '-' }}</td>
                    <td class="p-2 text-sm">{{ $coach->email ?? '-' }}</td>
                </tr>
                @endforeach
            @endif
        @endforeach
    </tbody>
</table>

@if($clubs->isEmpty())
<p class="text-gray-500 text-center py-8">{{ __('Geen clubs gevonden') }}</p>
@endif

<div class="mt-6 text-sm text-gray-600">
    <p><strong>{{ __('Totaal') }}:</strong> {{ $clubs->count() }} {{ __('clubs') }}</p>
    <p><strong>{{ __('Coaches') }}:</strong> {{ $clubs->sum(fn($c) => $c->coaches->count()) }}</p>
</div>

<div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
    <h3 class="font-bold text-yellow-800 mb-2">{{ __('Privacy') }}</h3>
    <p class="text-sm text-yellow-700">
        {{ __('Deze lijst bevat persoonlijke contactgegevens. Gebruik alleen bij noodgevallen.') }}
        {{ __('Niet delen met derden.') }}
    </p>
</div>
@endsection
