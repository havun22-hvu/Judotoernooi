<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">JudoToernooi</h1>
            <p class="text-gray-600">Organisator Registratie</p>
        </div>

        <form action="{{ route('organisator.register.submit') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="naam" class="block text-sm font-medium text-gray-700 mb-1">
                    Naam
                </label>
                <input type="text"
                       id="naam"
                       name="naam"
                       value="{{ old('naam') }}"
                       required
                       autofocus
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('naam') border-red-500 @enderror">
                @error('naam')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    E-mailadres
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
                <input type="password"
                       id="password"
                       name="password"
                       required
                       minlength="8"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('password') border-red-500 @enderror">
                @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    Wachtwoord bevestigen
                </label>
                <input type="password"
                       id="password_confirmation"
                       name="password_confirmation"
                       required
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                Account aanmaken
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Al een account?
                <a href="{{ route('organisator.login') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                    Log hier in
                </a>
            </p>
        </div>
    </div>
</body>
</html>
