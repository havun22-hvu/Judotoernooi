<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Coach Kaart Activeren</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            -webkit-user-select: none;
            user-select: none;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        {{-- Header --}}
        <div class="bg-purple-700 text-white px-4 py-4 text-center">
            <h1 class="text-xl font-bold">Coach Kaart Activeren</h1>
            <p class="text-purple-200 text-sm mt-1">{{ $coachKaart->toernooi->naam }}</p>
        </div>

        {{-- Club info --}}
        <div class="px-4 py-3 bg-purple-50 border-b">
            <p class="text-center text-purple-900 font-semibold">{{ $coachKaart->club->naam }}</p>
            @if($coachKaart->club->plaats)
            <p class="text-center text-purple-600 text-sm">{{ $coachKaart->club->plaats }}</p>
            @endif
        </div>

        {{-- Activation form --}}
        <form action="{{ route('coach-kaart.activeer.opslaan', $coachKaart->qr_code) }}" method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
            @csrf

            {{-- Error messages --}}
            @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded text-sm">
                @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
                @endforeach
            </div>
            @endif

            {{-- Name input --}}
            <div>
                <label for="naam" class="block text-gray-700 font-medium mb-1">Jouw naam</label>
                <input type="text" name="naam" id="naam" required
                       value="{{ old('naam') }}"
                       placeholder="Voornaam Achternaam"
                       class="w-full border rounded-lg px-4 py-3 text-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            {{-- Photo upload --}}
            <div>
                <label class="block text-gray-700 font-medium mb-2">Pasfoto (selfie)</label>
                <p class="text-gray-500 text-sm mb-3">Deze foto wordt getoond bij de ingang van de dojo ter verificatie.</p>

                {{-- Preview --}}
                <div id="preview-container" class="hidden mb-3">
                    <img id="preview" class="w-32 h-32 object-cover rounded-lg mx-auto border-4 border-purple-200">
                </div>

                {{-- Camera/upload buttons --}}
                <div class="flex gap-2">
                    <label class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg cursor-pointer flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Maak Selfie
                        <input type="file" name="foto" id="foto" accept="image/*" capture="user" required class="hidden" onchange="previewImage(this)">
                    </label>
                </div>

                <p class="text-xs text-gray-400 mt-2 text-center">Tip: Gebruik de camera aan de voorkant</p>
            </div>

            {{-- Submit --}}
            <button type="submit" id="submit-btn" disabled
                    class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-4 px-4 rounded-lg text-lg transition-colors">
                Activeer Coach Kaart
            </button>
        </form>

        {{-- Footer --}}
        <div class="px-4 py-3 bg-gray-50 border-t text-center">
            <p class="text-xs text-gray-500">
                Na activatie is deze kaart gekoppeld aan jouw foto.<br>
                Delen van de kaart zonder foto heeft geen zin.
            </p>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const container = document.getElementById('preview-container');
            const submitBtn = document.getElementById('submit-btn');
            const nameInput = document.getElementById('naam');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                    updateSubmitButton();
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updateSubmitButton() {
            const foto = document.getElementById('foto');
            const naam = document.getElementById('naam');
            const submitBtn = document.getElementById('submit-btn');

            submitBtn.disabled = !(foto.files.length > 0 && naam.value.trim().length > 0);
        }

        document.getElementById('naam').addEventListener('input', updateSubmitButton);
    </script>
</body>
</html>
