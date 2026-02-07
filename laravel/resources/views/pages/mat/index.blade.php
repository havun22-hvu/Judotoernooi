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
        <h2 class="text-xl font-bold mb-4">Mat {{ $mat->nummer }}</h2>

        <div class="space-y-2">
            @foreach($blokken as $blok)
            <a href="{{ route('toernooi.mat.show', $toernooi->routeParamsWith(['mat' => $mat, 'blok' => $blok])) }}"
               class="block p-3 bg-gray-100 hover:bg-gray-200 rounded">
                Blok {{ $blok->nummer }}
                @if($blok->weging_gesloten)
                <span class="text-xs text-red-600">({{ __('gesloten') }})</span>
                @endif
            </a>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endsection
