@extends('layouts.app')

@section('title', 'Wimpeltoernooi Instellingen - ' . $organisator->naam)

@section('content')
<div class="max-w-4xl mx-auto" x-data="milestonesPage()">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Wimpeltoernooi Instellingen</h1>
            <p class="text-gray-500">Configureer milestones en prijsjes</p>
        </div>
        <a href="{{ route('organisator.wimpel.index', $organisator) }}"
           class="text-blue-600 hover:text-blue-800">
            &larr; Terug naar overzicht
        </a>
    </div>

    {{-- Feedback --}}
    <div x-show="feedback" x-transition x-cloak
         :class="feedbackType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'"
         class="border rounded px-4 py-3 mb-4">
        <span x-text="feedback"></span>
    </div>

    {{-- Milestone toevoegen --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Milestone toevoegen</h2>
        <form @submit.prevent="addMilestone()" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Punten</label>
                <input type="number" x-model="nieuwPunten" min="1" required
                       class="w-24 border rounded px-3 py-2" placeholder="10">
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prijsje / omschrijving</label>
                <input type="text" x-model="nieuwOmschrijving" required
                       class="w-full border rounded px-3 py-2" placeholder="bijv. Wimpeltje, Beeldje, Kleur bandje">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                    :disabled="saving">
                Toevoegen
            </button>
        </form>
    </div>

    {{-- Milestones lijst --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Milestones (<span x-text="milestones.length"></span>)</h2>
        </div>

        <template x-if="milestones.length === 0">
            <div class="p-6 text-center text-gray-500">
                Nog geen milestones. Voeg hierboven je eerste milestone toe.
            </div>
        </template>

        <div class="divide-y divide-gray-200">
            <template x-for="(ms, index) in milestones" :key="ms.id">
                <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50">
                    <div class="bg-yellow-100 text-yellow-800 font-bold text-lg w-16 h-10 flex items-center justify-center rounded">
                        <span x-text="ms.punten"></span>
                    </div>
                    <template x-if="editId !== ms.id">
                        <div class="flex-1 flex items-center gap-4">
                            <span class="text-gray-800" x-text="ms.omschrijving"></span>
                            <div class="ml-auto flex gap-2">
                                <button @click="startEdit(ms)"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 text-xs font-medium px-3 py-1 rounded">
                                    Bewerken
                                </button>
                                <button @click="deleteMilestone(ms)"
                                        class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium px-3 py-1 rounded">
                                    Verwijderen
                                </button>
                            </div>
                        </div>
                    </template>
                    <template x-if="editId === ms.id">
                        <form @submit.prevent="saveMilestone(ms)" class="flex-1 flex items-center gap-2">
                            <input type="number" x-model="editPunten" min="1" class="w-20 border rounded px-2 py-1 text-sm">
                            <input type="text" x-model="editOmschrijving" class="flex-1 border rounded px-2 py-1 text-sm">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-1 rounded">
                                Opslaan
                            </button>
                            <button type="button" @click="editId = null"
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1 rounded">
                                Annuleer
                            </button>
                        </form>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function milestonesPage() {
    return {
        milestones: @json($milestones),
        nieuwPunten: '',
        nieuwOmschrijving: '',
        editId: null,
        editPunten: '',
        editOmschrijving: '',
        saving: false,
        feedback: '',
        feedbackType: 'success',

        async addMilestone() {
            this.saving = true;
            try {
                const res = await fetch('{{ route("organisator.wimpel.milestones.store", $organisator) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        punten: parseInt(this.nieuwPunten),
                        omschrijving: this.nieuwOmschrijving,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this.milestones.push(data.milestone);
                    this.milestones.sort((a, b) => a.punten - b.punten);
                    this.nieuwPunten = '';
                    this.nieuwOmschrijving = '';
                    this.showFeedback('Milestone toegevoegd', 'success');
                }
            } catch (e) {
                this.showFeedback('Fout bij toevoegen', 'error');
            }
            this.saving = false;
        },

        startEdit(ms) {
            this.editId = ms.id;
            this.editPunten = ms.punten;
            this.editOmschrijving = ms.omschrijving;
        },

        async saveMilestone(ms) {
            try {
                const url = '{{ route("organisator.wimpel.milestones.update", [$organisator, "__ID__"]) }}'.replace('__ID__', ms.id);
                const res = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        punten: parseInt(this.editPunten),
                        omschrijving: this.editOmschrijving,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    ms.punten = data.milestone.punten;
                    ms.omschrijving = data.milestone.omschrijving;
                    this.milestones.sort((a, b) => a.punten - b.punten);
                    this.editId = null;
                    this.showFeedback('Milestone bijgewerkt', 'success');
                }
            } catch (e) {
                this.showFeedback('Fout bij opslaan', 'error');
            }
        },

        async deleteMilestone(ms) {
            if (!confirm(`Milestone "${ms.punten} pt - ${ms.omschrijving}" verwijderen?`)) return;
            try {
                const url = '{{ route("organisator.wimpel.milestones.destroy", [$organisator, "__ID__"]) }}'.replace('__ID__', ms.id);
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.success) {
                    this.milestones = this.milestones.filter(m => m.id !== ms.id);
                    this.showFeedback('Milestone verwijderd', 'success');
                }
            } catch (e) {
                this.showFeedback('Fout bij verwijderen', 'error');
            }
        },

        showFeedback(msg, type) {
            this.feedback = msg;
            this.feedbackType = type;
            setTimeout(() => this.feedback = '', 3000);
        }
    }
}
</script>
@endsection
