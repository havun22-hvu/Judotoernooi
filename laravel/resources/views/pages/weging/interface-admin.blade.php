@extends('layouts.app')

@section('title', 'Weging Interface')

@push('styles')
<style>
    #qr-reader { width: 100%; }
    #qr-reader video { border-radius: 0.5rem; }
    #qr-reader__dashboard, #qr-reader__scan_region > img { display: none !important; }
    .numpad-btn { font-size: 1.25rem; font-weight: bold; min-height: 50px; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-800">⚖️ Weging Interface</h1>
</div>

<div class="bg-blue-900 text-white rounded-lg p-3 relative" style="min-height: 600px;">
    @include('pages.weging.partials._content')
</div>
@endsection
