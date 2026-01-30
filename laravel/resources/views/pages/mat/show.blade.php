@extends('layouts.app')

@section('title', 'Mat ' . $mat->nummer)

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Mat {{ $mat->nummer }} - Blok {{ $blok?->nummer ?? '?' }}</h1>
</div>

@forelse($schema as $pouleSchema)
<div class="bg-white rounded-lg shadow mb-6">
    <div class="bg-blue-800 text-white px-6 py-3 rounded-t-lg">
        <h2 class="text-lg font-bold">{{ $pouleSchema['titel'] }}</h2>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-bold mb-2">Judoka's</h3>
                @foreach($pouleSchema['judokas'] as $judoka)
                <div class="py-1">
                    {{ $judoka['naam'] }}
                    <span class="text-gray-500 text-sm">({{ $judoka['club'] ?? '-' }})</span>
                </div>
                @endforeach
            </div>

            <div>
                <h3 class="font-bold mb-2">Wedstrijden</h3>
                @foreach($pouleSchema['wedstrijden'] as $w)
                <div class="py-1 border-b last:border-0 flex justify-between items-center">
                    <span>
                        {{ $w['volgorde'] }}.
                        <span class="text-blue-600">{{ $w['wit']['naam'] }}</span>
                        vs
                        <span class="text-red-600">{{ $w['blauw']['naam'] }}</span>
                    </span>
                    @if($w['is_gespeeld'])
                    <span class="text-green-600">✓</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@empty
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Geen poules op deze mat in dit blok
</div>
@endforelse

@if(request()->routeIs('rol.*'))
<a href="{{ route('rol.mat') }}" class="text-blue-600 hover:text-blue-800">
    ← Terug naar matten
</a>
@else
<a href="{{ route('toernooi.mat.index', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
    ← Terug naar matten
</a>
@endif
@endsection
