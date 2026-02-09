@extends('layouts.app')

@section('title', __('Mat Interface'))

@push('styles')
<style>
    input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .elim-sel-groen { outline: 3px solid #22c55e; outline-offset: -3px; }
    .elim-sel-geel { outline: 3px solid #eab308; outline-offset: -3px; }
    .elim-sel-blauw { outline: 3px solid #3b82f6; outline-offset: -3px; }
    .sortable-bracket-ghost { opacity: 0.4; background: #dbeafe !important; }
    .sortable-bracket-chosen { opacity: 0.3; }
    /* Drag chip styling - SortableJS fallback clone */
    .sortable-fallback {
        padding: 4px 10px !important;
        background: white !important;
        border: 2px solid #9ca3af !important;
        border-radius: 6px !important;
        font-size: 13px !important;
        white-space: nowrap !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
        width: auto !important;
        height: auto !important;
        min-height: 0 !important;
    }
    .sortable-fallback span:not(.truncate) { display: none !important; }
    /* Drop target highlight */
    .sortable-drop-highlight { outline: 3px solid #a855f7; outline-offset: -1px; background: #f3e8ff !important; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-800">🥋 {{ __('Mat Interface') }}</h1>
</div>

@include('pages.mat.partials._content')

<!-- SortableJS for touch drag & drop in bracket -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endsection
