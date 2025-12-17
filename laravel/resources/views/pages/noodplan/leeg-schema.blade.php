@extends('layouts.print')

@section('title', "Leeg wedstrijdschema - {$aantal} judoka's")

@section('content')
<div class="mb-6">
    <h2 class="text-xl font-bold mb-2">Wedstrijdschema voor {{ $aantal }} judoka's</h2>
    <p class="text-sm text-gray-600">
        {{ count($schema) }} wedstrijden
        @if($aantal <= 3) (dubbele round-robin) @else (enkelvoudige round-robin) @endif
    </p>
</div>

<!-- Deelnemers invullen -->
<div class="mb-6 p-4 bg-gray-50 border rounded">
    <h3 class="font-bold mb-3">Deelnemers</h3>
    <table class="w-full text-sm">
        @for($i = 1; $i <= $aantal; $i++)
        <tr>
            <td class="py-2 w-12 font-bold">{{ $i }}.</td>
            <td class="py-2 border-b border-dotted">Naam: _________________________________</td>
            <td class="py-2 border-b border-dotted w-32">Club: ______________</td>
        </tr>
        @endfor
    </table>
</div>

<!-- Wedstrijden -->
<table class="w-full text-sm">
    <thead>
        <tr class="bg-gray-200">
            <th class="p-2 text-center w-12">#</th>
            <th class="p-2 text-center w-16">Wit</th>
            <th class="p-2 text-center w-8">-</th>
            <th class="p-2 text-center w-16">Blauw</th>
            <th class="p-2 text-center w-20">WP Wit</th>
            <th class="p-2 text-center w-20">WP Blauw</th>
            <th class="p-2 text-center w-20">Winnaar</th>
            <th class="p-2 text-left">Opmerkingen</th>
        </tr>
    </thead>
    <tbody>
        @foreach($schema as $idx => $wedstrijd)
        <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
            <td class="p-3 text-center font-bold">{{ $idx + 1 }}</td>
            <td class="p-3 text-center font-bold text-lg">{{ $wedstrijd[0] }}</td>
            <td class="p-3 text-center text-gray-400">-</td>
            <td class="p-3 text-center font-bold text-lg text-blue-600">{{ $wedstrijd[1] }}</td>
            <td class="p-3 text-center border-l"></td>
            <td class="p-3 text-center border-l"></td>
            <td class="p-3 text-center border-l"></td>
            <td class="p-3 border-l"></td>
        </tr>
        @endforeach
    </tbody>
</table>

<!-- Klassement -->
<div class="mt-8 p-4 bg-gray-50 border rounded">
    <h3 class="font-bold mb-3">Klassement</h3>
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-200">
                <th class="p-2 text-center w-12">Plaats</th>
                <th class="p-2 text-center w-12">#</th>
                <th class="p-2 text-left">Naam</th>
                <th class="p-2 text-center w-16">Gewonnen</th>
                <th class="p-2 text-center w-16">Verloren</th>
                <th class="p-2 text-center w-16">WP</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 1; $i <= $aantal; $i++)
            <tr>
                <td class="p-2 text-center font-bold">{{ $i }}</td>
                <td class="p-2 text-center"></td>
                <td class="p-2"></td>
                <td class="p-2 text-center"></td>
                <td class="p-2 text-center"></td>
                <td class="p-2 text-center"></td>
            </tr>
            @endfor
        </tbody>
    </table>
</div>

<!-- Meerdere kopieën printen -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        <strong>Tip:</strong> Print meerdere kopieën van dit schema als backup.
        Gebruik Ctrl+P en stel het aantal exemplaren in.
    </p>
</div>
@endsection
