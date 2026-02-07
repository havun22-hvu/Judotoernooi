<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Betaling Simulatie') }} - iDEAL</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- iDEAL-achtige header -->
            <div class="bg-gradient-to-r from-pink-500 to-pink-600 p-6 text-white">
                <div class="flex items-center justify-between">
                    <div class="text-2xl font-bold">iDEAL</div>
                    <div class="text-sm opacity-75">{{ __('Simulatie') }}</div>
                </div>
            </div>

            <div class="p-6">
                <div class="text-center mb-6">
                    <p class="text-gray-600 mb-2">{{ __('Betaling voor:') }}</p>
                    <p class="font-bold text-lg">{{ $betaling?->toernooi?->naam ?? __('Onbekend toernooi') }}</p>
                    @if($betaling)
                    <p class="text-3xl font-bold text-gray-800 mt-4">
                        &euro;{{ number_format($betaling->bedrag, 2, ',', '.') }}
                    </p>
                    <p class="text-sm text-gray-500 mt-1">{{ $betaling->aantal_judokas }} judoka('s)</p>
                    @endif
                </div>

                <div class="border-t pt-6">
                    <p class="text-sm text-gray-600 mb-4 text-center">
                        {{ __('Dit is een') }} <strong>{{ __('simulatie') }}</strong>. {{ __('In productie wordt je doorgestuurd naar je bank.') }}
                    </p>

                    <form action="{{ route('betaling.simulate.complete') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="payment_id" value="{{ $paymentId }}">

                        <button type="submit" name="status" value="paid"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                            {{ __('Betaling Voltooien') }}
                        </button>

                        <button type="submit" name="status" value="canceled"
                                class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-3 px-6 rounded-lg">
                            {{ __('Annuleren') }}
                        </button>

                        <button type="submit" name="status" value="failed"
                                class="w-full bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-6 rounded text-sm">
                            {{ __('Simuleer Fout') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-3 text-center text-xs text-gray-500">
                JudoToernooi - {{ __('Staging Betaling Simulatie') }}
            </div>
        </div>
    </div>
</body>
</html>
