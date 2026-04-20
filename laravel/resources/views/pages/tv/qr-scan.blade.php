@extends('layouts.app')

@section('title', __('TV Koppelen'))

@section('content')
<div class="max-w-xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-2">{{ __('TV Koppelen') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('Code') }}: <span class="font-mono font-bold">{{ $code }}</span></p>

    @if($status === 'expired')
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">
            <p class="font-medium">{{ __('Code verlopen of onbekend') }}</p>
            <p class="text-sm mt-1">{{ __('Ververs de TV-pagina voor een nieuwe code.') }}</p>
        </div>
    @elseif($status === 'already-linked')
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4">
            <p class="font-medium">{{ __('Deze code is al gekoppeld') }}</p>
            <p class="text-sm mt-1">{{ __('Ververs de TV-pagina voor een nieuwe code.') }}</p>
        </div>
    @elseif($status === 'ready')
        <div x-data="tvQrScan()" x-cloak>
            @if($toernooien->isEmpty())
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4">
                    <p>{{ __('Geen actieve toernooien gevonden.') }}</p>
                </div>
            @else
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Toernooi') }}</label>
                <select x-model="toernooiId" @change="loadMatten()"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4">
                    <option value="">{{ __('Kies een toernooi...') }}</option>
                    @foreach($toernooien as $t)
                        <option value="{{ $t->id }}" data-matten="{{ $t->aantal_matten ?? 1 }}">
                            {{ $t->naam }} ({{ optional($t->datum)->format('d-m-Y') }})
                        </option>
                    @endforeach
                </select>

                <template x-if="toernooiId">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mat') }}</label>
                        <div class="grid grid-cols-3 gap-2 mb-6">
                            <template x-for="n in aantalMatten" :key="n">
                                <button type="button" @click="link(n)"
                                        class="bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-lg text-lg font-semibold">
                                    {{ __('Mat') }} <span x-text="n"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <p x-show="status === 'linking'" class="text-gray-600 text-sm">{{ __('Koppelen...') }}</p>
                <p x-show="status === 'success'" class="text-green-700 font-medium">{{ __('Gekoppeld! TV wijst zichzelf door.') }}</p>
                <p x-show="status === 'error'" class="text-red-700 font-medium" x-text="error"></p>
            @endif
        </div>

        <script @nonce>
            function tvQrScan() {
                return {
                    toernooiId: '',
                    aantalMatten: 1,
                    status: null,
                    error: '',
                    loadMatten() {
                        const opt = this.$el.querySelector('select').selectedOptions[0];
                        this.aantalMatten = parseInt(opt?.dataset.matten || '1', 10) || 1;
                    },
                    async link(matNummer) {
                        this.status = 'linking';
                        this.error = '';
                        try {
                            const res = await fetch('{{ route('tv.link') }}', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({
                                    code: '{{ $code }}',
                                    toernooi_id: this.toernooiId,
                                    mat_nummer: matNummer,
                                }),
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) {
                                this.status = 'error';
                                this.error = data.message || '{{ __('Koppelen mislukt') }}';
                                return;
                            }
                            this.status = 'success';
                        } catch (e) {
                            this.status = 'error';
                            this.error = '{{ __('Netwerkfout') }}';
                        }
                    },
                };
            }
        </script>
    @endif
</div>
@endsection
