<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $toegang->getLabel() }} - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Prevent text selection on pin inputs */
        .pin-input {
            -webkit-user-select: none;
            user-select: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-600 to-blue-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">{{ $toernooi->naam }}</h1>
            <p class="text-blue-200 mt-2">{{ $toernooi->datum->format('d F Y') }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-2xl p-8" x-data="{
            pin: ['', '', '', ''],
            filterInput(index) {
                // Only allow single digits
                this.pin[index] = this.pin[index].toString().replace(/[^0-9]/g, '').slice(-1);
            },
            focusNext(index) {
                this.filterInput(index);
                if (this.pin[index] && index < 3) {
                    this.$refs['pin' + (index + 1)].focus();
                }
            },
            focusPrev(index, event) {
                if (event.key === 'Backspace') {
                    if (!this.pin[index] && index > 0) {
                        // Field empty, go to previous
                        this.$refs['pin' + (index - 1)].focus();
                    } else {
                        // Clear current field
                        this.pin[index] = '';
                    }
                }
            },
            handlePaste(event) {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData).getData('text');
                const digits = pasted.replace(/[^0-9]/g, '').slice(0, 4).split('');
                digits.forEach((digit, i) => {
                    this.pin[i] = digit;
                });
                // Focus last filled or next empty
                const focusIndex = Math.min(digits.length, 3);
                this.$refs['pin' + focusIndex].focus();
            },
            get fullPin() {
                return this.pin.join('');
            }
        }">
            <div class="flex items-center justify-center gap-3 mb-6">
                @switch($toegang->rol)
                    @case('hoofdjury')
                        <span class="text-3xl">⚖️</span>
                        @break
                    @case('mat')
                        <span class="text-3xl">🥋</span>
                        @break
                    @case('weging')
                        <span class="text-3xl">⚖️</span>
                        @break
                    @case('spreker')
                        <span class="text-3xl">🎙️</span>
                        @break
                    @case('dojo')
                        <span class="text-3xl">🚪</span>
                        @break
                @endswitch
                <h2 class="text-2xl font-bold text-gray-800">{{ $toegang->getLabel() }}</h2>
            </div>

            <p class="text-gray-600 text-center mb-6">
                Voer de 4-cijferige pincode in om toegang te krijgen.
            </p>

            @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                {{ $errors->first() }}
            </div>
            @endif

            <form action="{{ route('toegang.verify', ['organisator' => $toernooi->organisator->slug, 'toernooi' => $toernooi->slug, 'code' => $toegang->code]) }}" method="POST">
                @csrf
                <input type="hidden" name="pincode" x-bind:value="fullPin">

                <!-- PIN Input -->
                <div class="flex justify-center gap-3 mb-8">
                    @for($i = 0; $i < 4; $i++)
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]"
                        x-ref="pin{{ $i }}"
                        x-model="pin[{{ $i }}]"
                        @input="focusNext({{ $i }})"
                        @keydown="focusPrev({{ $i }}, $event)"
                        @paste="handlePaste($event)"
                        maxlength="1"
                        class="pin-input w-14 h-16 text-center text-2xl font-bold border-2 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                        required
                    >
                    @endfor
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    x-show="fullPin.length === 4"
                    x-transition
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                >
                    Toegang krijgen
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Vraag de pincode aan de organisator of hoofdjury.
            </div>
        </div>

        <div class="text-center mt-6 text-blue-200 text-sm">
            Dit device wordt gekoppeld aan deze toegang.
        </div>
    </div>

    <script>
        // Auto-focus first input on load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('[x-ref="pin0"]').focus();
        });
    </script>
</body>
</html>
