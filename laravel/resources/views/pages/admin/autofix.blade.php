@extends('layouts.app')

@section('title', 'AutoFix Overzicht')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">AutoFix Overzicht</h1>
        <p class="text-gray-500 mt-1">AI-gestuurde error analyse en automatische fixes</p>
    </div>
    <a href="{{ route('admin.index') }}" class="text-blue-600 hover:text-blue-800">
        &larr; Terug naar Dashboard
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</div>
        <div class="text-sm text-gray-500">Totaal</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-green-600">{{ $stats['applied'] }}</div>
        <div class="text-sm text-gray-500">Toegepast</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-red-600">{{ $stats['failed'] }}</div>
        <div class="text-sm text-gray-500">Mislukt</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
        <div class="text-sm text-gray-500">In behandeling</div>
    </div>
</div>

{{-- Proposals table --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    @if($proposals->isEmpty())
        <div class="p-8 text-center text-gray-500">
            Nog geen AutoFix proposals. Errors worden automatisch geanalyseerd wanneer ze optreden.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exception</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bestand</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tijdstip</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($proposals as $proposal)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $proposal->id }}</td>
                        <td class="px-4 py-3">
                            @if($proposal->status === 'applied')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Toegepast
                                </span>
                            @elseif($proposal->status === 'failed')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Mislukt
                                </span>
                            @elseif($proposal->status === 'pending')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    In behandeling
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $proposal->status }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ class_basename($proposal->exception_class) }}</div>
                            <div class="text-xs text-gray-500 truncate max-w-xs">{{ Str::limit($proposal->exception_message, 80) }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-700 font-mono">{{ $proposal->file }}:{{ $proposal->line }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                            {{ $proposal->created_at->format('d-m H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <button onclick="toggleDetail({{ $proposal->id }})" class="text-blue-600 hover:text-blue-800 text-sm">
                                Bekijk
                            </button>
                        </td>
                    </tr>
                    <tr id="detail-{{ $proposal->id }}" class="hidden bg-gray-50">
                        <td colspan="6" class="px-4 py-4">
                            <div class="space-y-3">
                                @if($proposal->url)
                                <div>
                                    <span class="text-xs font-medium text-gray-500">URL:</span>
                                    <span class="text-sm text-gray-700">{{ $proposal->url }}</span>
                                </div>
                                @endif

                                @if($proposal->apply_error)
                                <div>
                                    <span class="text-xs font-medium text-red-500">Apply error:</span>
                                    <span class="text-sm text-red-700">{{ $proposal->apply_error }}</span>
                                </div>
                                @endif

                                @if($proposal->applied_at)
                                <div>
                                    <span class="text-xs font-medium text-green-500">Toegepast op:</span>
                                    <span class="text-sm text-green-700">{{ $proposal->applied_at }}</span>
                                </div>
                                @endif

                                <div>
                                    <span class="text-xs font-medium text-gray-500">Claude analyse:</span>
                                    <pre class="mt-1 text-xs bg-white border rounded p-3 overflow-x-auto whitespace-pre-wrap max-h-64 overflow-y-auto">{{ $proposal->claude_analysis }}</pre>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
function toggleDetail(id) {
    const el = document.getElementById('detail-' + id);
    el.classList.toggle('hidden');
}
</script>
@endsection
