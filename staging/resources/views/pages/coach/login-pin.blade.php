<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - Coach Portal</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $club->naam }}</h1>
            <p class="text-gray-600">{{ __('Coach Portal') }}</p>
            <div class="mt-2">@include('partials.coach-locale-switcher')</div>
            <p class="text-sm text-gray-500 mt-1">{{ $toernooi->naam }}</p>
        </div>

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
        @endif

        <form action="{{ route('coach.portal.login', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}" method="POST">
            @csrf
            <div class="mb-6">
                <label for="pincode" class="block text-sm font-medium text-gray-700 mb-2">
                    {{ __('PIN code (5 cijfers)') }}
                </label>
                <input type="text"
                       id="pincode"
                       name="pincode"
                       maxlength="5"
                       pattern="[0-9]{5}"
                       inputmode="numeric"
                       autocomplete="off"
                       required
                       class="w-full text-center text-3xl tracking-[0.4em] font-mono border-2 border-gray-300 rounded-lg px-4 py-4 focus:border-blue-500 focus:ring-blue-500"
                       placeholder="_____">
                @error('pincode')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                {{ __('Inloggen') }}
            </button>
        </form>

        <p class="mt-6 text-xs text-gray-500 text-center">
            {{ __('PIN vergeten? Neem contact op met de organisatie.') }}
        </p>

        <!-- Security warning -->
        <div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex items-start gap-2">
                <span class="text-amber-600 text-lg">⚠️</span>
                <div class="text-xs text-amber-800">
                    <p class="font-bold mb-1">{{ __('Beveiligingswaarschuwing') }}</p>
                    <p>{{ __('Deze PIN geeft toegang om judoka\'s aan te melden en te wijzigen. Deel deze code niet met anderen en bewaar hem veilig.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus and auto-advance
        const pinInput = document.getElementById('pincode');
        pinInput.focus();

        // Only allow numbers
        pinInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
