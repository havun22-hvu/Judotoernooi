@extends('layouts.app')

@section('title', __('Mat Interface'))

@push('styles')
<style>
    input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-800">🥋 {{ __('Mat Interface') }}</h1>
</div>

@include('pages.mat.partials._content')
@endsection
