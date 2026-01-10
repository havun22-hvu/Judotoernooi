<?php

namespace App\Console\Commands;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseToernooi extends Command
{
    protected $signature = 'toernooi:diagnose {zoek?}';

    protected $description = 'Diagnose toernooi toegangsproblemen';

    public function handle(): int
    {
        $this->info('=== Toernooi Diagnose Tool ===');
        $this->newLine();

        // Toon alle toernooien
        $this->info('Alle toernooien:');
        $toernooien = Toernooi::select('id', 'naam', 'slug', 'is_actief')->get();

        $this->table(
            ['ID', 'Slug', 'Actief', 'Naam'],
            $toernooien->map(fn($t) => [
                $t->id,
                $t->slug ?? '(GEEN SLUG!)',
                $t->is_actief ? '✓' : '✗',
                $t->naam,
            ])
        );

        // Toon organisatoren
        $this->newLine();
        $this->info('Organisatoren:');
        $organisatoren = Organisator::with('toernooien')->get();

        foreach ($organisatoren as $org) {
            $gekoppeld = $org->toernooien->pluck('naam')->join(', ') ?: '(geen)';
            $badge = $org->isSitebeheerder() ? ' <bg=magenta;fg=white> SITEBEHEERDER </>' : '';
            $this->line("  <fg=cyan>{$org->naam}</> <{$org->email}>{$badge}");
            $this->line("    → {$gekoppeld}");
        }

        // Zoek specifiek toernooi
        $zoek = $this->argument('zoek');
        if ($zoek) {
            $this->newLine();
            $this->info("Zoeken naar: '$zoek'");

            $toernooi = Toernooi::where('naam', 'LIKE', "%$zoek%")
                ->orWhere('slug', 'LIKE', "%$zoek%")
                ->first();

            if ($toernooi) {
                $this->line("  <fg=green>Gevonden:</> {$toernooi->naam}");
                $this->line("  ID: {$toernooi->id}");
                $this->line("  Slug: " . ($toernooi->slug ?? '<fg=red>(GEEN!)</>'));
                $this->line("  Import URL: /toernooi/{$toernooi->slug}/judoka/import");

                // Check koppelingen
                $eigenaren = DB::table('organisator_toernooi')
                    ->where('toernooi_id', $toernooi->id)
                    ->get();

                if ($eigenaren->isEmpty()) {
                    $this->newLine();
                    $this->error('⚠️  PROBLEEM: Geen organisatoren gekoppeld!');
                    $this->warn('Dit verklaart waarom niemand het toernooi kan zien.');
                    $this->newLine();

                    if ($this->confirm('Wil je de sitebeheerder koppelen aan dit toernooi?')) {
                        $sitebeheerder = Organisator::where('is_sitebeheerder', true)->first();
                        if ($sitebeheerder) {
                            DB::table('organisator_toernooi')->insert([
                                'organisator_id' => $sitebeheerder->id,
                                'toernooi_id' => $toernooi->id,
                                'rol' => 'eigenaar',
                            ]);
                            $this->info("✓ {$sitebeheerder->naam} gekoppeld als eigenaar!");
                        } else {
                            $this->error('Geen sitebeheerder gevonden.');
                        }
                    }
                } else {
                    $this->line("  Gekoppeld aan:");
                    foreach ($eigenaren as $e) {
                        $org = Organisator::find($e->organisator_id);
                        $this->line("    - {$org->naam} <{$org->email}> ({$e->rol})");
                    }
                }
            } else {
                $this->error("Toernooi niet gevonden: $zoek");
            }
        }

        return Command::SUCCESS;
    }
}
