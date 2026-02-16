@extends('layouts.app')

@section('title', __('Clubs uitnodigen'))

@section('content')
<div x-data="{ copiedUrl: null }">

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Clubs Uitnodigen') }}</h1>
        <p class="text-gray-600 mt-1">{{ __('Selecteer welke clubs je wilt uitnodigen voor dit toernooi') }}</p>
        <div class="flex gap-2 mt-2">
            <button type="button" onclick="toggleAlleClubs(true)" class="text-sm bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded">
                {{ __('Alles aan') }}
            </button>
            <button type="button" onclick="toggleAlleClubs(false)" class="text-sm bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded">
                {{ __('Alles uit') }}
            </button>
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
                $heeftJudokas = $club->judokas_count > 0;
                $kanUitschakelen = !$heeftJudokas;
            @endphp
            <tr class="hover:bg-gray-50 {{ $isUitgenodigd ? 'bg-green-50' : '' }}">
                <td class="px-4 py-3">
                    <input type="checkbox"
                           class="club-toggle w-5 h-5 rounded border-gray-300 text-green-500 focus:ring-green-500 cursor-pointer"
                           data-club-id="{{ $club->id }}"
                           data-club-naam="{{ $club->naam }}"
                           data-judokas="{{ $club->judokas_count }}"
                           {{ $isUitgenodigd ? 'checked' : '' }}>
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
                    @if($isUitgenodigd)
                    @php
                        $whatsappTekst = __('Uitnodiging :naam', ['naam' => $toernooi->naam]) . "\n\n" . __('Inschrijflink:') . " {$portalUrl}\nPIN: {$pivotPincode}";
                        // Telefoon opschonen: alleen cijfers, 06 → 316
                        $telefoon = preg_replace('/[^0-9]/', '', $club->telefoon ?? '');
                        if (str_starts_with($telefoon, '06')) {
                            $telefoon = '31' . substr($telefoon, 1);
                        } elseif (str_starts_with($telefoon, '0')) {
                            $telefoon = '31' . substr($telefoon, 1);
                        }
                        $whatsappUrl = $telefoon
                            ? 'https://wa.me/' . $telefoon . '?text=' . urlencode($whatsappTekst)
                            : 'https://wa.me/?text=' . urlencode($whatsappTekst);
                    @endphp
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
                            <span class="text-xs font-mono bg-amber-50 px-1.5 py-0.5 rounded text-amber-800">PIN: {{ $pivotPincode }}</span>
                            <button @click="navigator.clipboard.writeText('{{ $pivotPincode }}'); copiedUrl = 'pin-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                    class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                    :class="copiedUrl === 'pin-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'"
                                    title="{{ __('Kopieer PIN') }}">
                                <span x-text="copiedUrl === 'pin-{{ $club->id }}' ? '~' : '~'"></span>
                            </button>
                            <a href="{{ $whatsappUrl }}" target="_blank"
                               class="px-1.5 py-0.5 text-xs rounded {{ $telefoon ? 'bg-green-500 hover:bg-green-600' : 'bg-green-300 hover:bg-green-400' }} text-white flex-shrink-0"
                               title="{{ $telefoon ? __('WhatsApp naar :telefoon', ['telefoon' => $club->telefoon]) : __('WhatsApp (geen telefoon, kies zelf)') }}">
                                <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>
                        </div>
                    </div>
                    @else
                    <span class="text-gray-400 text-sm">{{ __('Eerst selecteren') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    @if($isUitgenodigd && $club->email)
                    <form action="{{ route('toernooi.club.verstuur', $toernooi->routeParamsWith(['club' => $club])) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1 text-sm bg-green-100 text-green-700 hover:bg-green-200 rounded">
                            {{ __('Verstuur') }}
                        </button>
                    </form>
                    @elseif(!$club->email)
                    <span class="text-gray-400 text-sm">{{ __('Geen email') }}</span>
                    @else
                    <span class="text-gray-400 text-sm">-</span>
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
document.querySelectorAll('.club-toggle').forEach(cb => {
    cb.addEventListener('change', async function() {
        const clubId = this.dataset.clubId;
        const clubNaam = this.dataset.clubNaam;
        const judokas = parseInt(this.dataset.judokas || 0);
        const wantChecked = this.checked;

        // Warn if unchecking club with judokas
        if (!wantChecked && judokas > 0) {
            if (!confirm(`${clubNaam} heeft nog ${judokas} judoka's. Toch deselecteren?`)) {
                this.checked = true;
                return;
            }
        }

        try {
            const toggleBase = '{{ route("toernooi.club.toggle", ["organisator" => $organisator->slug, "toernooi" => $toernooi->slug, "club" => "__CLUB__"]) }}'.replace('__CLUB__', clubId);
            const res = await fetch(toggleBase, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            const data = await res.json();
            if (!data.success) {
                this.checked = !wantChecked; // revert
                return;
            }
            // Reload to update portal URLs and other state
            location.reload();
        } catch (e) {
            this.checked = !wantChecked; // revert on error
        }
    });
});

async function toggleAlleClubs(selecteren) {
    const url = selecteren
        ? '{{ route("toernooi.club.select-all", $toernooi->routeParams()) }}'
        : '{{ route("toernooi.club.deselect-all", $toernooi->routeParams()) }}';

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        location.reload();
    } catch (e) {
        location.reload();
    }
}
</script>
@endsection
