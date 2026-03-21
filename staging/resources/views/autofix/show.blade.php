<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoFix Voorstel #{{ $proposal->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif
        @if(session('info'))
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
                {{ session('info') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="bg-white rounded-lg shadow-sm border mb-6">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-800">AutoFix Voorstel #{{ $proposal->id }}</h1>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    @if($proposal->status === 'pending') bg-yellow-100 text-yellow-800
                    @elseif($proposal->status === 'applied') bg-green-100 text-green-800
                    @elseif($proposal->status === 'rejected') bg-gray-100 text-gray-800
                    @elseif($proposal->status === 'failed') bg-red-100 text-red-800
                    @else bg-blue-100 text-blue-800
                    @endif">
                    {{ ucfirst($proposal->status) }}
                </span>
            </div>

            {{-- Error Info --}}
            <div class="px-6 py-4 border-b bg-red-50">
                <h2 class="text-sm font-semibold text-red-800 uppercase mb-2">Error</h2>
                <div class="font-mono text-sm">
                    <p class="font-bold text-red-700">{{ $proposal->exception_class }}</p>
                    <p class="text-red-600 mt-1">{{ $proposal->exception_message }}</p>
                    <p class="text-gray-600 mt-2">
                        <span class="font-semibold">File:</span> {{ $proposal->file }}:{{ $proposal->line }}<br>
                        <span class="font-semibold">URL:</span> {{ $proposal->url ?? 'N/A' }}<br>
                        <span class="font-semibold">Tijd:</span> {{ $proposal->created_at->format('d-m-Y H:i:s') }}
                    </p>
                </div>
            </div>

            {{-- Claude Analysis --}}
            <div class="px-6 py-4 border-b">
                <h2 class="text-sm font-semibold text-gray-600 uppercase mb-2">Claude's Analyse & Fix</h2>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-sm whitespace-pre-wrap">{{ $proposal->claude_analysis }}</div>
            </div>

            {{-- Stack Trace --}}
            <div class="px-6 py-4 border-b">
                <details>
                    <summary class="text-sm font-semibold text-gray-600 uppercase cursor-pointer">Stack Trace</summary>
                    <pre class="bg-gray-50 rounded-lg p-4 mt-2 text-xs overflow-x-auto">{{ $proposal->stack_trace }}</pre>
                </details>
            </div>

            {{-- Actions --}}
            @if($proposal->isPending())
                <div class="px-6 py-4 flex items-center justify-center gap-4">
                    <form action="{{ route('autofix.approve', $proposal->approval_token) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-bold"
                            onclick="return confirm('Weet je zeker dat je deze fix wilt toepassen op production?')">
                            Goedkeuren & Toepassen
                        </button>
                    </form>
                    <form action="{{ route('autofix.reject', $proposal->approval_token) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-bold">
                            Afwijzen
                        </button>
                    </form>
                </div>
            @endif

            {{-- Applied info --}}
            @if($proposal->status === 'applied')
                <div class="px-6 py-4 bg-green-50 text-green-800 text-center">
                    Fix toegepast op {{ $proposal->applied_at->format('d-m-Y H:i:s') }}
                </div>
            @endif

            {{-- Failed info --}}
            @if($proposal->status === 'failed')
                <div class="px-6 py-4 bg-red-50 text-red-800">
                    <strong>Toepassen mislukt:</strong> {{ $proposal->apply_error }}
                </div>
            @endif
        </div>

    </div>
</body>
</html>
