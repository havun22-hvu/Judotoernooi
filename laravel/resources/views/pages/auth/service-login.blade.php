<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Login - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gradient-to-br from-purple-600 to-purple-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">{{ $toernooi->naam }}</h1>
            <p class="text-purple-200 mt-2">{{ $toernooi->datum->format('d F Y') }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-2xl p-8" x-data="{
            rol: '',
            showMatSelect: false
        }">
            <div class="flex items-center justify-center gap-2 mb-6">
                <span class="text-2xl">🔧</span>
                <h2 class="text-2xl font-bold text-gray-800">Service Login</h2>
            </div>

            <div class="bg-purple-50 border border-purple-200 text-purple-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <strong>Sitebeheerder modus</strong><br>
                Inloggen zonder wachtwoord voor troubleshooting.
            </div>

            @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
            @endif

            <form action="{{ route('toernooi.auth.service.post', $toernooi) }}" method="POST">
                @csrf

                <!-- Rol selectie -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-3">Inloggen als:</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative">
                            <input type="radio" name="rol" value="admin" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-purple-500 peer-checked:bg-purple-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">👑</div>
                                <div class="font-medium">Admin</div>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" name="rol" value="jury" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-purple-500 peer-checked:bg-purple-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">⚖️</div>
                                <div class="font-medium">Jury</div>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" name="rol" value="weging" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-purple-500 peer-checked:bg-purple-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">⚖️</div>
                                <div class="font-medium">Weging</div>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" name="rol" value="mat" x-model="rol" @change="showMatSelect = true"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-purple-500 peer-checked:bg-purple-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">🥋</div>
                                <div class="font-medium">Mat</div>
                            </div>
                        </label>

                        <label class="relative col-span-2">
                            <input type="radio" name="rol" value="spreker" x-model="rol" @change="showMatSelect = false"
                                   class="peer sr-only">
                            <div class="p-4 border-2 rounded-lg cursor-pointer text-center transition-all
                                        peer-checked:border-purple-500 peer-checked:bg-purple-50
                                        hover:border-gray-300">
                                <div class="text-2xl mb-1">🎙️</div>
                                <div class="font-medium">Spreker</div>
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

                <!-- Submit -->
                <button type="submit" x-show="rol !== ''" x-collapse
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    Service Login
                </button>
            </form>

            <!-- Back link -->
            <div class="mt-6 text-center">
                <a href="{{ route('toernooi.show', $toernooi) }}" class="text-purple-600 hover:text-purple-800 text-sm">
                    Terug naar toernooi
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-purple-200 text-sm">
            Ingelogd als: {{ auth('organisator')->user()->naam }} (Sitebeheerder)
        </div>
    </div>
</body>
</html>
