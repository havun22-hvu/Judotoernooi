<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord vergeten - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">JudoToernooi</h1>
            <p class="text-gray-600">Wachtwoord vergeten</p>
        </div>

        <p class="text-gray-600 mb-6 text-sm">
            Vul uw e-mailadres in en we sturen u een link om uw wachtwoord te resetten.
        </p>

        @if(session('status'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('status') }}
        </div>
        @endif

        <form action="{{ route('organisator.password.email') }}" method="POST">
            @csrf

            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    E-mailadres
                </label>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       autofocus
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                Verstuur reset link
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('organisator.login') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                Terug naar inloggen
            </a>
        </div>
    </div>
</body>
</html>
