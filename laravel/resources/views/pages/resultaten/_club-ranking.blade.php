<!-- Club Ranking - gesorteerd op gemiddelde WP/JP per judoka -->
<div class="bg-white rounded-lg shadow overflow-hidden avoid-break">
    <div class="bg-blue-600 text-white px-4 py-3">
        <h3 class="font-bold text-lg">Club Klassement</h3>
        <p class="text-blue-100 text-sm">Gemiddelde WP en JP per judoka</p>
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
                    <th class="px-3 py-2 text-center w-16">Judoka's</th>
                    <th class="px-3 py-2 text-center w-20">Gem WP</th>
                    <th class="px-3 py-2 text-center w-20">Gem JP</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($clubRanking['absoluut'] as $index => $club)
                <tr class="{{ $index < 3 ? 'bg-blue-50' : '' }}">
                    <td class="px-3 py-2 font-bold {{ $index === 0 ? 'text-yellow-600' : ($index === 1 ? 'text-gray-500' : ($index === 2 ? 'text-orange-600' : 'text-gray-600')) }}">
                        @if($index === 0) 🥇
                        @elseif($index === 1) 🥈
                        @elseif($index === 2) 🥉
                        @else {{ $index + 1 }}
                        @endif
                    </td>
                    <td class="px-3 py-2 font-medium">{{ $club['naam'] }}</td>
                    <td class="px-3 py-2 text-center bg-yellow-50 font-bold text-yellow-700">{{ $club['goud'] ?: '-' }}</td>
                    <td class="px-3 py-2 text-center bg-gray-50 font-bold text-gray-600">{{ $club['zilver'] ?: '-' }}</td>
                    <td class="px-3 py-2 text-center bg-orange-50 font-bold text-orange-700">{{ $club['brons'] ?: '-' }}</td>
                    <td class="px-3 py-2 text-center text-gray-600">{{ $club['totaal_judokas'] }}</td>
                    <td class="px-3 py-2 text-center font-bold text-blue-600">{{ number_format($club['gem_wp'], 1) }}</td>
                    <td class="px-3 py-2 text-center text-gray-600">{{ number_format($club['gem_jp'], 1) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-3 py-8 text-center text-gray-500">Nog geen resultaten</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Legend -->
<div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
    <strong>Ranking uitleg:</strong>
    <ul class="space-y-1 mt-2">
        <li><strong>Gem WP:</strong> Totaal wedstrijdpunten gedeeld door aantal ingeschreven judoka's</li>
        <li><strong>Gem JP:</strong> Totaal judopunten gedeeld door aantal ingeschreven judoka's</li>
        <li>Sortering: eerst op gemiddelde WP, bij gelijke stand op gemiddelde JP</li>
    </ul>
</div>
