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
                        {{ __('Gratis Tier') }}
                    </span>
                    <span class="text-sm text-gray-600">{{ $huidige }}/{{ $max }} {{ __("judoka's") }}</span>
                </div>

                {{-- Progress bar --}}
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full {{ $percentage >= 90 ? 'bg-red-500' : ($percentage >= 70 ? 'bg-yellow-500' : 'bg-green-500') }}"
                         style="width: {{ min($percentage, 100) }}%"></div>
                </div>

                @if($percentage >= 80)
                    <p class="mt-2 text-sm text-orange-700">
                        <strong>{{ __('Let op:') }}</strong> {{ __("Je nadert de limiet van :max judoka's.", ['max' => $max]) }}
                        <a href="{{ route('toernooi.upgrade', $toernooi->routeParams()) }}" class="underline font-medium">{{ __('Upgrade nu') }}</a> {{ __('voor meer ruimte.') }}
                    </p>
                @endif

                <p class="mt-2 text-xs text-gray-500">
                    {{ __('Print/Noodplan functies zijn geblokkeerd.') }}
                    <a href="{{ route('toernooi.upgrade', $toernooi->routeParams()) }}" class="text-blue-600 hover:underline">{{ __('Bekijk upgrade opties') }}</a>
                </p>
            </div>

            <a href="{{ route('toernooi.upgrade', $toernooi->routeParams()) }}"
               class="ml-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                {{ __('Upgrade') }}
            </a>
        </div>
    </div>
@elseif($toernooi->isWimpelAbo())
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                    {{ __('Wimpel Abonnement') }}
                </span>
                <span class="text-sm text-gray-600">{{ __('Onbeperkt puntencompetitie') }}</span>
            </div>
            @if($toernooi->organisator?->wimpelAboBijnaVerlopen())
                <span class="text-xs text-orange-600 font-medium">
                    {{ __('Abo verloopt :datum', ['datum' => $toernooi->organisator->wimpel_abo_einde->format('d-m-Y')]) }}
                </span>
            @else
                <span class="text-xs text-blue-600">{{ __('Alle functies actief') }}</span>
            @endif
        </div>
    </div>
@elseif($toernooi->isPaidTier())
    <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">
                    {{ __('Betaald') }}
                </span>
                <span class="text-sm text-gray-600">
                    {{ __(':tier staffel - max :max judoka\'s', ['tier' => $toernooi->paid_tier, 'max' => $toernooi->paid_max_judokas]) }}
                </span>
            </div>
            <span class="text-xs text-green-600">{{ __('Alle functies actief') }}</span>
        </div>
    </div>
@endif
