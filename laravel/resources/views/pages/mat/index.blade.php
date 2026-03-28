@extends('layouts.app')

@section('title', __('Matten'))

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('Matten') }}</h1>
    <a href="{{ route('toernooi.mat.interface', $toernooi->routeParams()) }}" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
        🥋 {{ __('Mat Interface') }}
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($matten as $mat)
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Mat {{ $mat->nummer }}</h2>
            <a href="{{ route('mat.scoreboard-live', ['organisator' => $toernooi->organisator->slug, 'toernooi' => $toernooi->slug, 'mat' => $mat->id]) }}" target="_blank"
               class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded flex items-center gap-1"
               title="{{ __('Scorebord Display openen op TV/LCD') }}">
                📺 {{ __('Display') }}
            </a>
        </div>

        <div class="space-y-2">
            @if($blokken->count() === 1)
            <a href="{{ route('toernooi.mat.show', $toernooi->routeParamsWith(['mat' => $mat, 'blok' => $blokken->first()])) }}"
               class="block p-3 bg-blue-100 hover:bg-blue-200 rounded font-medium text-blue-800">
                Blok 1 — {{ __('Bekijk schema') }}
            </a>
            @else
            @foreach($blokken as $blok)
            <a href="{{ route('toernooi.mat.show', $toernooi->routeParamsWith(['mat' => $mat, 'blok' => $blok])) }}"
               class="block p-3 bg-gray-100 hover:bg-gray-200 rounded">
                Blok {{ $blok->nummer }}
                @if($blok->weging_gesloten)
                <span class="text-xs text-red-600">({{ __('gesloten') }})</span>
                @endif
            </a>
            @endforeach
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
