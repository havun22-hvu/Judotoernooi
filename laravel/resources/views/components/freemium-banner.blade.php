@props(['toernooi'])

@if($toernooi->isFreeTier())
    @php
        $huidige = $toernooi->judokas()->count();
        $max = $toernooi->getEffectiveMaxJudokas();
        $percentage = $max > 0 ? round(($huidige / $max) * 100) : 0;
    @endphp

    <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                        Gratis Tier
                    </span>
                    <span class="text-sm text-gray-600">{{ $huidige }}/{{ $max }} judoka's</span>
                </div>

                {{-- Progress bar --}}
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full {{ $percentage >= 90 ? 'bg-red-500' : ($percentage >= 70 ? 'bg-yellow-500' : 'bg-green-500') }}"
                         style="width: {{ min($percentage, 100) }}%"></div>
                </div>

                @if($percentage >= 80)
                    <p class="mt-2 text-sm text-orange-700">
                        <strong>Let op:</strong> Je nadert de limiet van {{ $max }} judoka's.
                        <a href="{{ route('toernooi.upgrade', $toernooi) }}" class="underline font-medium">Upgrade nu</a> voor meer ruimte.
                    </p>
                @endif

                <p class="mt-2 text-xs text-gray-500">
                    Print/Noodplan functies zijn geblokkeerd.
                    <a href="{{ route('toernooi.upgrade', $toernooi) }}" class="text-blue-600 hover:underline">Bekijk upgrade opties</a>
                </p>
            </div>

            <a href="{{ route('toernooi.upgrade', $toernooi) }}"
               class="ml-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                Upgrade
            </a>
        </div>
    </div>
@elseif($toernooi->isPaidTier())
    <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">
                    Betaald
                </span>
                <span class="text-sm text-gray-600">
                    {{ $toernooi->paid_tier }} staffel - max {{ $toernooi->paid_max_judokas }} judoka's
                </span>
            </div>
            <span class="text-xs text-green-600">Alle functies actief</span>
        </div>
    </div>
@endif
