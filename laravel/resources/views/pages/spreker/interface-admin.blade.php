@extends('layouts.app')

@section('title', 'Spreker Interface')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-800">📢 Spreker Interface</h1>
</div>

@include('pages.spreker.partials._content')
@endsection
