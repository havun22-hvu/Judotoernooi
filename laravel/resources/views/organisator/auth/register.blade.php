<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Registreren') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ __('JudoToernooi') }}</h1>
            <p class="text-gray-600">{{ __('Organisator Registratie') }}</p>
        </div>

        @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('register.submit') }}" method="POST" id="registerForm">
            @csrf

            <div class="mb-4">
                <label for="organisatie_naam" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Naam judoschool / organisatie') }}
                </label>
                <input type="text"
                       id="organisatie_naam"
                       name="organisatie_naam"
                       value="{{ old('organisatie_naam') }}"
                       required
                       autofocus
                       placeholder="{{ __('Naam van uw judoschool') }}"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('organisatie_naam') border-red-500 @enderror">
                @error('organisatie_naam')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="naam" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Uw naam') }} <span class="text-gray-400">({{ __('contactpersoon') }})</span>
                </label>
                <input type="text"
                       id="naam"
                       name="naam"
                       value="{{ old('naam') }}"
                       required
                       placeholder="{{ __('bijv. Jan Jansen') }}"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('naam') border-red-500 @enderror">
                @error('naam')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('E-mailadres') }}
                </label>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="telefoon" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefoonnummer <span class="text-gray-400">(optioneel)</span>
                </label>
                <input type="tel"
                       id="telefoon"
                       name="telefoon"
                       value="{{ old('telefoon') }}"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Wachtwoord <span class="text-gray-400">(min. 8 tekens)</span>
                </label>
                <div class="relative">
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           minlength="8"
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 pr-10 focus:border-blue-500 focus:ring-blue-500 @error('password') border-red-500 @enderror">
                    <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700">
                        <svg id="eye-open-password" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="eye-closed-password" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    Wachtwoord bevestigen
                </label>
                <div class="relative">
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           required
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 pr-10 focus:border-blue-500 focus:ring-blue-500">
                    <button type="button" onclick="togglePassword('password_confirmation')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700">
                        <svg id="eye-open-password_confirmation" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="eye-closed-password_confirmation" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
            </div>
            <script>
            function togglePassword(fieldId) {
                const input = document.getElementById(fieldId);
                const eyeOpen = document.getElementById('eye-open-' + fieldId);
                const eyeClosed = document.getElementById('eye-closed-' + fieldId);
                if (input.type === 'password') {
                    input.type = 'text';
                    eyeOpen.classList.remove('hidden');
                    eyeClosed.classList.add('hidden');
                } else {
                    input.type = 'password';
                    eyeOpen.classList.add('hidden');
                    eyeClosed.classList.remove('hidden');
                }
            }
            document.getElementById('registerForm').addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.textContent = 'Bezig met registreren...';
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            });
            </script>

            {{-- DO NOT REMOVE: Registration submit button --}}
            <button type="submit" id="submitBtn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                Account aanmaken
            </button>
        </form>

        {{-- DO NOT REMOVE: Login link for existing users --}}
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Al een account?
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                    Log hier in
                </a>
            </p>
        </div>
    </div>
</body>
</html>
