@extends('layouts.app')

@section('title', __('Mat Interface'))

@push('styles')
<style>
    input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .sortable-bracket-ghost { opacity: 0.4; background: #dbeafe !important; }
    .sortable-bracket-chosen { opacity: 0.3; }
    /* Drop target highlight */
    .sortable-drop-highlight { outline: 3px solid #a855f7; outline-offset: -1px; background: #f3e8ff !important; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-800">🥋 {{ __('Mat Interface') }}</h1>
</div>

@include('pages.mat.partials._content')

<!-- Pusher for Reverb WebSocket -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<!-- SortableJS for touch drag & drop in bracket -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

@include('partials.mat-updates-listener', [
    'toernooi' => $toernooi,
    'matId' => $matNummer ?? null
])
@endsection
