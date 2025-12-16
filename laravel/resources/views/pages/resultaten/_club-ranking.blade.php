<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Absolute Ranking -->
    <div class="bg-white rounded-lg shadow overflow-hidden avoid-break">
        <div class="bg-yellow-500 text-white px-4 py-3">
            <h3 class="font-bold text-lg">Absoluut Klassement</h3>
            <p class="text-yellow-100 text-sm">Totaal aantal medailles</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Club</th>
                        <th class="px-3 py-2 text-center w-12" title="Goud">🥇</th>
                        <th class="px-3 py-2 text-center w-12" title="Zilver">🥈</th>
                        <th class="px-3 py-2 text-center w-12" title="Brons">🥉</th>
                        <th class="px-3 py-2 text-center w-16">Totaal</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($clubRanking['absoluut'] as $index => $club)
                    <tr class="{{ $index < 3 ? 'bg-yellow-50' : '' }}">
                        <td class="px-3 py-2 font-bold {{ $index === 0 ? 'text-yellow-600' : ($index === 1 ? 'text-gray-500' : ($index === 2 ? 'text-orange-600' : 'text-gray-600')) }}">
                            {{ $index + 1 }}
                        </td>
                        <td class="px-3 py-2 font-medium">{{ $club['naam'] }}</td>
                        <td class="px-3 py-2 text-center bg-yellow-50 font-bold text-yellow-700">{{ $club['goud'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-center bg-gray-50 font-bold text-gray-600">{{ $club['zilver'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-center bg-orange-50 font-bold text-orange-700">{{ $club['brons'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-center font-bold text-blue-600">{{ $club['totaal_medailles'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-gray-500">Nog geen resultaten</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Relative Ranking -->
    <div class="bg-white rounded-lg shadow overflow-hidden avoid-break">
        <div class="bg-green-500 text-white px-4 py-3">
            <h3 class="font-bold text-lg">Relatief Klassement</h3>
            <p class="text-green-100 text-sm">Punten per aangemelde judoka</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Club</th>
                        <th class="px-3 py-2 text-center w-16">Judoka's</th>
                        <th class="px-3 py-2 text-center w-16">Punten</th>
                        <th class="px-3 py-2 text-center w-20">Gem.</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($clubRanking['relatief'] as $index => $club)
                    <tr class="{{ $index < 3 ? 'bg-green-50' : '' }}">
                        <td class="px-3 py-2 font-bold {{ $index === 0 ? 'text-green-600' : ($index === 1 ? 'text-gray-500' : ($index === 2 ? 'text-orange-600' : 'text-gray-600')) }}">
                            {{ $index + 1 }}
                        </td>
                        <td class="px-3 py-2 font-medium">{{ $club['naam'] }}</td>
                        <td class="px-3 py-2 text-center text-gray-600">{{ $club['totaal_judokas'] }}</td>
                        <td class="px-3 py-2 text-center text-gray-600">{{ $club['punten'] }}</td>
                        <td class="px-3 py-2 text-center font-bold text-green-600">{{ $club['relatief'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-gray-500">Nog geen resultaten</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
    <strong>Puntentelling:</strong>
    <div class="flex gap-4 mt-2 mb-3">
        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">🥇 Goud = 3 punten</span>
        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded">🥈 Zilver = 2 punten</span>
        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded">🥉 Brons = 1 punt</span>
    </div>
    <ul class="space-y-1">
        <li><strong>Absoluut:</strong> Totaal punten van alle medailles bij elkaar opgeteld</li>
        <li><strong>Relatief:</strong> Totaal punten gedeeld door aantal aangemelde judoka's. Zo maken kleine clubs ook kans!</li>
    </ul>
</div>
