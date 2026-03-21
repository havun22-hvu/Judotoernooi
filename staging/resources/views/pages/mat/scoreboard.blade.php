@extends('layouts.app')

@section('title', __('Scorebord'))

@section('content')
<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">🥋 {{ __('Scorebord') }}</h1>
        @if($wedstrijd)
            <span class="text-gray-600">Wedstrijd #{{ $wedstrijd->id }}</span>
        @endif
    </div>

    <x-scoreboard :wedstrijd="$wedstrijd" />
</div>
@endsection
