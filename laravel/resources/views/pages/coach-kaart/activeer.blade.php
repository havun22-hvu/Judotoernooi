<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ __('Coach Kaart Activeren') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <style>
        body {
            -webkit-user-select: none;
            user-select: none;
        }
        .cropper-container {
            max-height: 300px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        {{-- Header --}}
        <div class="bg-purple-700 text-white px-4 py-4 text-center">
            <h1 class="text-xl font-bold">
                @if($isOvername)
                    {{ __('Coach Kaart Overnemen') }}
                @else
                    {{ __('Coach Kaart Activeren') }}
                @endif
            </h1>
            <p class="text-purple-200 text-sm mt-1">{{ $coachKaart->toernooi->naam }}</p>
        </div>

        {{-- BLOCKED: Coach still checked in --}}
        @if($isOvername && !$coachKaart->kanOverdragen())
        <div class="px-4 py-6 bg-red-50 border-b border-red-200">
            <div class="text-center mb-4">
                <span class="text-4xl">🔒</span>
                <h2 class="text-xl font-bold text-red-800 mt-2">{{ __('Kaart nog in gebruik') }}</h2>
            </div>

            <div class="flex items-center gap-3 mb-4 bg-white rounded-lg p-3">
                @if($coachKaart->foto)
                <img src="{{ $coachKaart->getFotoUrl() }}" alt="Huidige coach"
                     class="w-16 h-16 object-cover rounded-lg border-2 border-red-300">
                @endif
                <div>
                    <p class="text-red-900 font-bold">{{ $coachKaart->naam }}</p>
                    <p class="text-red-600 text-sm">{{ __('Ingecheckt sinds') }} {{ $coachKaart->ingecheckt_op?->format('H:i') }}</p>
                </div>
            </div>

            <div class="bg-red-100 rounded-lg p-4 text-red-800">
                <p class="font-medium mb-2">{{ __('Overdracht niet mogelijk') }}</p>
                <p class="text-sm">{{ __('Vraag de huidige coach om uit te checken bij de dojo scanner. Pas daarna kunt u de kaart overnemen.') }}</p>
            </div>

            <a href="{{ route('coach-kaart.show', $coachKaart->qr_code) }}"
               class="block w-full mt-4 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-3 px-4 rounded-lg text-center">
                {{ __('Terug') }}
            </a>
        </div>
        @elseif($isOvername)
        {{-- Takeover info (when allowed) --}}
        <div class="px-4 py-4 bg-amber-50 border-b border-amber-200">
            <div class="flex items-center gap-3">
                @if($coachKaart->foto)
                <img src="{{ $coachKaart->getFotoUrl() }}" alt="Huidige coach"
                     class="w-16 h-16 object-cover rounded-lg border-2 border-amber-300">
                @endif
                <div>
                    <p class="text-amber-800 font-medium">{{ __('Huidige coach:') }}</p>
                    <p class="text-amber-900 font-bold">{{ $coachKaart->naam }}</p>
                    <p class="text-amber-600 text-sm">{{ __('Actief sinds') }} {{ $coachKaart->geactiveerd_op?->format('H:i') }}</p>
                </div>
            </div>
            <p class="text-amber-700 text-sm mt-3">
                {{ __('Door deze kaart over te nemen wordt de toegang van :naam beëindigd.', ['naam' => $coachKaart->naam]) }}
            </p>
        </div>
        @endif

        {{-- Only show form if takeover is allowed --}}
        @if(!$isOvername || $coachKaart->kanOverdragen())
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

            {{-- Pincode input --}}
            <div>
                <label for="pincode" class="block text-gray-700 font-medium mb-1">{{ __('Pincode (4 cijfers)') }}</label>
                <p class="text-gray-500 text-sm mb-2">{{ __('Deze pincode heb je ontvangen van je club.') }}</p>
                <input type="text" name="pincode" id="pincode" required
                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                       value="{{ old('pincode') }}"
                       placeholder="0000"
                       class="w-full border rounded-lg px-4 py-3 text-2xl text-center tracking-widest font-mono focus:ring-2 focus:ring-purple-500 focus:border-purple-500 @error('pincode') border-red-500 @enderror">
            </div>

            {{-- Name input --}}
            <div>
                <label for="naam" class="block text-gray-700 font-medium mb-1">{{ __('Jouw naam') }}</label>
                <input type="text" name="naam" id="naam" required
                       value="{{ old('naam') }}"
                       placeholder="{{ __('Voornaam Achternaam') }}"
                       class="w-full border rounded-lg px-4 py-3 text-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            {{-- Photo upload --}}
            <div>
                <label class="block text-gray-700 font-medium mb-2">{{ __('Pasfoto (selfie)') }}</label>
                <p class="text-gray-500 text-sm mb-3">{{ __('Deze foto wordt getoond bij de ingang van de dojo ter verificatie.') }}</p>

                {{-- Cropper container (hidden until image selected) --}}
                <div id="cropper-container" class="hidden mb-3">
                    <div class="bg-gray-900 rounded-lg overflow-hidden" style="max-height: 300px;">
                        <img id="crop-image" class="max-w-full">
                    </div>
                    <div class="flex justify-center gap-2 mt-2">
                        <button type="button" onclick="cropper.zoom(0.1)" class="px-3 py-1 bg-gray-200 rounded text-sm">➕ Zoom in</button>
                        <button type="button" onclick="cropper.zoom(-0.1)" class="px-3 py-1 bg-gray-200 rounded text-sm">➖ Zoom uit</button>
                        <button type="button" onclick="resetCrop()" class="px-3 py-1 bg-gray-200 rounded text-sm">↺ Reset</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 text-center">{{ __('Sleep om te centreren, knijp/scroll om te zoomen') }}</p>
                </div>

                {{-- Final preview (after crop confirmed) --}}
                <div id="preview-container" class="hidden mb-3">
                    <img id="preview" class="w-32 h-32 object-cover rounded-lg mx-auto border-4 border-purple-200">
                    <button type="button" onclick="editCrop()" class="block mx-auto mt-2 text-purple-600 text-sm underline">{{ __('Aanpassen') }}</button>
                </div>

                {{-- Hidden input for cropped image --}}
                <input type="hidden" name="foto_cropped" id="foto_cropped">

                {{-- Camera/upload buttons --}}
                <div id="upload-buttons" class="flex gap-2">
                    <label class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg cursor-pointer flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ __('Maak Selfie') }}
                        <input type="file" id="foto-input" accept="image/*" capture="user" class="hidden" onchange="loadImage(this)">
                    </label>
                </div>

                <p id="upload-tip" class="text-xs text-gray-400 mt-2 text-center">{{ __('Tip: Gebruik de camera aan de voorkant') }}</p>
            </div>

            {{-- Submit --}}
            <button type="submit" id="submit-btn" disabled
                    class="w-full {{ $isOvername ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700' }} disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-4 px-4 rounded-lg text-lg transition-colors">
                @if($isOvername)
                    {{ __('Neem Coach Kaart Over') }}
                @else
                    {{ __('Activeer Coach Kaart') }}
                @endif
            </button>
        </form>

        {{-- Footer --}}
        <div class="px-4 py-3 bg-gray-50 border-t text-center">
            <p class="text-xs text-gray-500">
                {{ __('Na activatie is deze kaart gekoppeld aan dit apparaat en jouw foto.') }}<br>
                {{ __('De QR-code is alleen zichtbaar op dit apparaat.') }}
            </p>
        </div>
        @endif
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        let cropper = null;
        let fotoSelected = false;

        function loadImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const cropImage = document.getElementById('crop-image');
                    cropImage.src = e.target.result;

                    // Destroy existing cropper
                    if (cropper) {
                        cropper.destroy();
                    }

                    // Show cropper, hide preview and upload button
                    document.getElementById('cropper-container').classList.remove('hidden');
                    document.getElementById('preview-container').classList.add('hidden');
                    document.getElementById('upload-buttons').classList.add('hidden');
                    document.getElementById('upload-tip').classList.add('hidden');

                    // Initialize cropper
                    cropper = new Cropper(cropImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.9,
                        restore: false,
                        guides: false,
                        center: true,
                        highlight: false,
                        cropBoxMovable: false,
                        cropBoxResizable: false,
                        toggleDragModeOnDblclick: false,
                        ready: function() {
                            // Auto-confirm after cropper is ready
                            setTimeout(confirmCrop, 100);
                        }
                    });
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function confirmCrop() {
            if (!cropper) return;

            // Get cropped canvas (square, 400x400)
            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            // Convert to blob and set hidden input
            canvas.toBlob(function(blob) {
                // Store blob for form submission
                window.croppedBlob = blob;
                fotoSelected = true;
                updateSubmitButton();
            }, 'image/jpeg', 0.9);

            // Show preview
            const preview = document.getElementById('preview');
            preview.src = canvas.toDataURL('image/jpeg', 0.9);
            document.getElementById('preview-container').classList.remove('hidden');
        }

        function editCrop() {
            // Hide preview, show cropper
            document.getElementById('preview-container').classList.add('hidden');
            document.getElementById('cropper-container').classList.remove('hidden');
        }

        function resetCrop() {
            if (cropper) {
                cropper.reset();
            }
        }

        function updateSubmitButton() {
            const naam = document.getElementById('naam');
            const pincode = document.getElementById('pincode');
            const submitBtn = document.getElementById('submit-btn');

            const pincodeValid = pincode.value.length === 4 && /^\d{4}$/.test(pincode.value);
            const allValid = fotoSelected && naam.value.trim().length > 0 && pincodeValid;

            submitBtn.disabled = !allValid;
        }

        document.getElementById('naam').addEventListener('input', updateSubmitButton);
        document.getElementById('pincode').addEventListener('input', updateSubmitButton);

        // Handle form submission with cropped image
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!window.croppedBlob) {
                e.preventDefault();
                alert('{{ __('Maak eerst een foto') }}');
                return;
            }

            // Create FormData and append cropped blob
            const formData = new FormData(this);
            formData.delete('foto_cropped');
            formData.append('foto', window.croppedBlob, 'selfie.jpg');

            // Submit via fetch
            e.preventDefault();
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = '{{ __('Bezig...') }}';

            fetch(this.action, {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else if (!response.ok) {
                    return response.text().then(text => { throw new Error(text); });
                }
            }).catch(error => {
                console.error('Error:', error);
                alert('{{ __('Er ging iets mis. Probeer opnieuw.') }}');
                submitBtn.disabled = false;
                submitBtn.textContent = '{{ $isOvername ? __("Neem Coach Kaart Over") : __("Activeer Coach Kaart") }}';
            });
        });
    </script>
</body>
</html>
