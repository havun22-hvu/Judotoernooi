<div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
    <h3 class="font-semibold text-blue-800 mb-4">Toernooi Informatie</h3>
    <div class="space-y-2 text-blue-700">
        <p><strong>Datum:</strong> {{ $toernooi->datum ? $toernooi->datum->format('d-m-Y') : 'Nog niet bekend' }}</p>
        <p><strong>Locatie:</strong> {{ $toernooi->locatie ?? 'Nog niet bekend' }}</p>
        <p><strong>Deelnemers:</strong> {{ $totaalJudokas }} aangemeld</p>
    </div>
</div>
