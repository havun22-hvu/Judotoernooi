@extends('layouts.app')

@section('title', __('Clubs uitnodigen'))

@section('content')
<div x-data="clubToggle()" x-init="init()">

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Clubs Uitnodigen') }}</h1>
        <p class="text-gray-600 mt-1">{{ __('Selecteer welke clubs je wilt uitnodigen voor dit toernooi') }}</p>
        <div class="flex gap-2 mt-2">
            <form action="{{ route('toernooi.club.select-all', $toernooi->routeParams()) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-sm bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded">
                    {{ __('Alles aan') }}
                </button>
            </form>
            <form action="{{ route('toernooi.club.deselect-all', $toernooi->routeParams()) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-sm bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded">
                    {{ __('Alles uit') }}
                </button>
            </form>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('toernooi.email-log', $toernooi->routeParams()) }}"
           class="bg-blue-100 hover:bg-blue-200 text-blue-800 font-bold py-2 px-4 rounded flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            {{ __('Email Log') }}
        </a>
        <a href="{{ route('organisator.clubs.index', [$organisator, 'back' => url()->current()]) }}"
           class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded flex items-center gap-2">
            {{ __('Clubs Beheren') }}
        </a>
        <form action="{{ route('toernooi.coach-kaart.genereer', $toernooi->routeParams()) }}" method="POST" class="inline"
              onsubmit="return confirm('{{ __('Coachkaarten genereren voor alle geselecteerde clubs?') }}')">
            @csrf
            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
                {{ __('Genereer Coachkaarten') }}
            </button>
        </form>
        <form action="{{ route('toernooi.club.verstuur-alle', $toernooi->routeParams()) }}" method="POST" class="inline"
              onsubmit="return confirm('{{ __('Alle geselecteerde clubs met email uitnodigen?') }}')">
            @csrf
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                {{ __('Alle Uitnodigen') }}
            </button>
        </form>
    </div>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    {{ session('error') }}
</div>
@endif

@if($toernooi->inschrijving_deadline)
<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm">
    <strong>{{ __('Deadline:') }}</strong> {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}
    @if($toernooi->isInschrijvingOpen())
    <span class="text-green-600 font-medium">({{ __('open') }})</span>
    @else
    <span class="text-red-600 font-medium">({{ __('gesloten') }})</span>
    @endif
</div>
@endif

@if($clubs->isEmpty())
<div class="bg-white rounded-lg shadow p-8 text-center">
    <p class="text-gray-500 mb-4">{{ __('Je hebt nog geen clubs in je clublijst.') }}</p>
    <a href="{{ route('organisator.clubs.index', [$organisator, 'back' => url()->current()]) }}"
       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
        {{ __('Clubs Toevoegen') }}
    </a>
</div>
@else

<!-- Clubs tabel -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 w-16">{{ __('Actief') }}</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">{{ __('Club') }}</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">{{ __('Plaats') }}</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">{{ __('Email') }}</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">{{ __("Judoka's") }}</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">{{ __('Coach Portal') }}</th>
                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-600">{{ __('Email') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($clubs->sortBy('naam') as $club)
            @php
                $isUitgenodigd = in_array($club->id, $uitgenodigdeClubIds);
                $portalUrl = $club->getPortalUrl($toernooi);
                $pivotPincode = $uitgenodigdeClubs[$club->id]->pivot->pincode ?? null;
                $telefoon = preg_replace('/[^0-9]/', '', $club->telefoon ?? '');
                if (str_starts_with($telefoon, '06')) {
                    $telefoon = '31' . substr($telefoon, 1);
                } elseif (str_starts_with($telefoon, '0')) {
                    $telefoon = '31' . substr($telefoon, 1);
                }
            @endphp
            <tr class="hover:bg-gray-50 transition-colors"
                :class="clubs[{{ $club->id }}] ? 'bg-green-50' : ''">
                <td class="px-4 py-3">
                    <button @click="toggle({{ $club->id }}, '{{ addslashes($club->naam) }}', {{ $club->judokas_count }})"
                            class="w-6 h-6 rounded border-2 flex items-center justify-center transition-colors cursor-pointer"
                            :class="clubs[{{ $club->id }}] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400'">
                        <svg x-show="clubs[{{ $club->id }}]" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </td>
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-800">{{ $club->naam }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->plaats ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->email ?? '-' }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs {{ $club->judokas_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }} rounded">
                        {{ $club->judokas_count }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <template x-if="clubs[{{ $club->id }}]">
                        <div class="space-y-1">
                            <div class="flex items-center gap-1">
                                <code class="text-xs bg-gray-100 px-1 py-0.5 rounded text-gray-600 max-w-[180px] truncate" title="{{ $portalUrl }}">
                                    {{ $portalUrl }}
                                </code>
                                <button @click="navigator.clipboard.writeText('{{ $portalUrl }}'); copiedUrl = 'url-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                        class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                        :class="copiedUrl === 'url-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-600 hover:bg-blue-200'"
                                        title="{{ __('Kopieer URL') }}">
                                    <span x-text="copiedUrl === 'url-{{ $club->id }}' ? '~' : '~'"></span>
                                </button>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-xs font-mono bg-amber-50 px-1.5 py-0.5 rounded text-amber-800">PIN: {{ $pivotPincode ?? $club->pincode }}</span>
                                <button @click="navigator.clipboard.writeText('{{ $pivotPincode ?? $club->pincode }}'); copiedUrl = 'pin-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                        class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                        :class="copiedUrl === 'pin-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'"
                                        title="{{ __('Kopieer PIN') }}">
                                    <span x-text="copiedUrl === 'pin-{{ $club->id }}' ? '~' : '~'"></span>
                                </button>
                                @php
                                    $whatsappTekst = __('Uitnodiging :naam', ['naam' => $toernooi->naam]) . "\n\n" . __('Inschrijflink:') . " {$portalUrl}\nPIN: " . ($pivotPincode ?? $club->pincode);
                                    $whatsappUrl = $telefoon
                                        ? 'https://wa.me/' . $telefoon . '?text=' . urlencode($whatsappTekst)
                                        : 'https://wa.me/?text=' . urlencode($whatsappTekst);
                                @endphp
                                <a href="{{ $whatsappUrl }}" target="_blank"
                                   class="px-1.5 py-0.5 text-xs rounded {{ $telefoon ? 'bg-green-500 hover:bg-green-600' : 'bg-green-300 hover:bg-green-400' }} text-white flex-shrink-0"
                                   title="{{ $telefoon ? __('WhatsApp naar :telefoon', ['telefoon' => $club->telefoon]) : __('WhatsApp (geen telefoon, kies zelf)') }}">
                                    <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </a>
                            </div>
                        </div>
                    </template>
                    <template x-if="!clubs[{{ $club->id }}]">
                        <span class="text-gray-400 text-sm">{{ __('Eerst selecteren') }}</span>
                    </template>
                </td>
                <td class="px-4 py-3 text-right">
                    @if($club->email)
                    <template x-if="clubs[{{ $club->id }}]">
                        <form action="{{ route('toernooi.club.verstuur', $toernooi->routeParamsWith(['club' => $club])) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-1 text-sm bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                {{ __('Verstuur') }}
                            </button>
                        </form>
                    </template>
                    <template x-if="!clubs[{{ $club->id }}]">
                        <span class="text-gray-400 text-sm">-</span>
                    </template>
                    @else
                    <span class="text-gray-400 text-sm">{{ __('Geen email') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4 text-sm text-gray-500">
    {!! __(':selected van :total clubs geselecteerd voor dit toernooi', ['selected' => '<strong>' . count($uitgenodigdeClubIds) . '</strong>', 'total' => '<strong>' . $clubs->count() . '</strong>']) !!}
</div>

@endif

</div>

<script>
function clubToggle() {
    return {
        clubs: @json(array_fill_keys($uitgenodigdeClubIds, true)),
        copiedUrl: null,

        init() {},

        async toggle(clubId, clubNaam, judokasCount) {
            if (this.clubs[clubId] && judokasCount > 0) {
                if (!confirm(clubNaam + ' heeft nog ' + judokasCount + " judoka's. Toch deselecteren?")) {
                    return;
                }
            }

            try {
                const response = await fetch(
                    '{{ url($organisator->slug . '/toernooi/' . $toernooi->slug) }}/club/' + clubId + '/toggle',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                    }
                );

                const data = await response.json();

                if (data.success) {
                    if (data.is_uitgenodigd) {
                        this.clubs[clubId] = true;
                    } else {
                        delete this.clubs[clubId];
                    }

                    if (data.warning) {
                        alert(data.warning);
                    }
                }
            } catch (e) {
                console.error('Toggle failed:', e);
                alert('Er ging iets mis. Probeer het opnieuw.');
            }
        }
    };
}
</script>

@endsection
