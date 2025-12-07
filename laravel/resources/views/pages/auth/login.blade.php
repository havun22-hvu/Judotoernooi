<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-600 to-blue-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">{{ $toernooi->naam }}</h1>
            <p class="text-blue-200 mt-2">{{ $toernooi->datum->format('d F Y') }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-2xl p-8" x-data="{
            rol: '',
            showMatSelect: false,
            wachtwoordVereist: {{ Js::from($wachtwoordVereist) }},
            needsPassword() {
                return this.rol && this.wachtwoordVereist[this.rol];
            }
        }">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Inloggen</h2>

            @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
            @endif

            @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                {{ session('success') }}
            </div>
            @endif

            <form action="{{ route('toernooi.auth.login.post', $toernooi) }}" method="POST">
                @csrf

                <!-- Rol selectie -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-3">Ik ben:</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative">
                            <input type="radio" name="rol" value="admin" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">👑</div>
                                <div class="font-medium">Admin</div>
                                <div class="text-xs text-gray-500">Volledig beheer</div>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" name="rol" value="jury" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">⚖️</div>
                                <div class="font-medium">Jury</div>
                                <div class="text-xs text-gray-500">Hoofdtafel</div>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" name="rol" value="weging" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">⚖️</div>
                                <div class="font-medium">Weging</div>
                                <div class="text-xs text-gray-500">Weeglijst</div>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" name="rol" value="mat" x-model="rol" @change="showMatSelect = true"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">🥋</div>
                                <div class="font-medium">Mat</div>
                                <div class="text-xs text-gray-500">Wedstrijden</div>
                            </div>
                        </label>

                        <label class="relative col-span-2">
                            <input type="radio" name="rol" value="spreker" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">🎙️</div>
                                <div class="font-medium">Spreker</div>
                                <div class="text-xs text-gray-500">Omroepen wedstrijden</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Mat nummer selectie -->
                <div class="mb-6" x-show="showMatSelect" x-collapse>
                    <label for="mat_nummer" class="block text-gray-700 font-medium mb-2">Welke mat?</label>
                    <select name="mat_nummer" id="mat_nummer" class="w-full border-2 rounded-lg px-4 py-3">
                        @for($i = 1; $i <= $toernooi->aantal_matten; $i++)
                        <option value="{{ $i }}">Mat {{ $i }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Wachtwoord (alleen als vereist) -->
                <div class="mb-6" x-show="needsPassword()" x-collapse>
                    <label for="wachtwoord" class="block text-gray-700 font-medium mb-2">Wachtwoord</label>
                    <input type="password" name="wachtwoord" id="wachtwoord"
                           class="w-full border-2 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                </div>

                <!-- Submit -->
                <button type="submit" x-show="rol !== ''" x-collapse
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    <span x-text="needsPassword() ? 'Inloggen' : 'Doorgaan'"></span>
                </button>
            </form>

            <!-- Coach link -->
            <div class="mt-8 pt-6 border-t text-center">
                <p class="text-gray-600 text-sm">
                    Ben je coach van een judoschool?
                </p>
                <p class="text-gray-500 text-xs mt-1">
                    Gebruik de uitnodigingslink die je per email hebt ontvangen.
                </p>
            </div>
        </div>

        <div class="text-center mt-6 text-blue-200 text-sm">
            &copy; {{ date('Y') }} Havun
        </div>
    </div>
</body>
</html>
