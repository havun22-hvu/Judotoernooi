@forelse($uitslagen as $leeftijdsklasse => $poules)
<div class="mb-8 avoid-break">
    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded">{{ $leeftijdsklasse }}</span>
        <span class="text-gray-400 text-sm font-normal">{{ count($poules) }} poule(s)</span>
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($poules as $poule)
        <div class="bg-white rounded-lg shadow overflow-hidden avoid-break">
            <div class="bg-purple-600 text-white px-4 py-2">
                @if($poule->gewichtsklasse && $poule->gewichtsklasse !== 'onbekend')
                <span class="font-bold">{{ $poule->gewichtsklasse }}</span> -
                @endif
                <span class="text-purple-200 text-sm">Poule {{ $poule->nummer }}</span>
            </div>
            <div class="divide-y">
                @foreach($poule->standings as $index => $standing)
                @php $plaats = $index + 1; @endphp
                <div class="px-3 py-2 flex justify-between items-center
                    @if($plaats === 1) bg-yellow-50
                    @elseif($plaats === 2) bg-gray-50
                    @elseif($plaats === 3) bg-orange-50
                    @endif">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold
                            @if($plaats === 1) bg-yellow-400 text-yellow-900
                            @elseif($plaats === 2) bg-gray-300 text-gray-800
                            @elseif($plaats === 3) bg-orange-300 text-orange-900
                            @else bg-gray-200 text-gray-600
                            @endif">
                            @if($plaats === 1) 🥇
                            @elseif($plaats === 2) 🥈
                            @elseif($plaats === 3) 🥉
                            @else {{ $plaats }}
                            @endif
                        </span>
                        <div>
                            <span class="font-medium text-gray-800 text-sm">{{ $standing['judoka']->naam }}</span>
                            <span class="text-gray-500 text-xs block">{{ $standing['judoka']->club?->naam ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="text-right text-xs">
                        @if($poule->is_punten_competitie ?? false)
                        <span class="font-bold text-green-600">{{ $standing['gewonnen'] }}</span>
                        <span class="text-gray-400">W</span>
                        @else
                        <span class="font-bold text-blue-600">{{ $standing['wp'] }}</span>
                        <span class="text-gray-400">WP</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>
@empty
<div class="text-center py-12 text-gray-500">
    <div class="text-6xl mb-4">🏆</div>
    <p class="text-xl">Nog geen uitslagen</p>
    <p class="text-sm mt-2">Uitslagen verschijnen hier zodra poules zijn afgerond.</p>
</div>
@endforelse
