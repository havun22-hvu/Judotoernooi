<?php

namespace App\Console\Commands;

use App\Services\DynamischeIndelingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class TestDynamischeIndeling extends Command
{
    protected $signature = 'test:dynamische-indeling {aantal=100}';
    protected $description = 'Test de DynamischeIndelingService met dummy data';

    private const BANDEN = ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart'];

    public function handle(): int
    {
        $aantal = (int) $this->argument('aantal');

        $this->info("=== DYNAMISCHE INDELING TEST ===");
        $this->info("Genereer {$aantal} test judoka's...");

        $judokas = $this->genereerTestJudokas($aantal);

        $this->info("Leeftijden: " . $judokas->min('leeftijd') . "-" . $judokas->max('leeftijd') . " jaar");
        $this->info("Gewichten: " . $judokas->min('gewicht') . "-" . $judokas->max('gewicht') . " kg");

        $service = new DynamischeIndelingService();

        $this->newLine();
        $this->info("Genereer varianten...");

        $result = $service->genereerVarianten($judokas);

        $this->info("Tijd: {$result['tijdMs']}ms");
        $this->newLine();

        foreach ($result['varianten'] as $i => $variant) {
            $marker = $i === 0 ? ' ✓ BESTE' : '';
            $this->info("Variant " . ($i + 1) . ": Score {$variant['score']}{$marker}");
            $this->line("  - Max leeftijd: {$variant['params']['max_leeftijd_verschil']} jaar");
            $this->line("  - Max gewicht: {$variant['params']['max_kg_verschil']} kg");
            $this->line("  - Poules: {$variant['aantal_poules']}, Judoka's: {$variant['totaal_ingedeeld']}/{$variant['totaal_judokas']}");
            $this->line("  - Stats: L gem={$variant['stats']['leeftijd_gem']} max={$variant['stats']['leeftijd_max']}, G gem={$variant['stats']['gewicht_gem']} max={$variant['stats']['gewicht_max']}");
        }

        // Toon voorbeeld poules van beste variant
        $beste = $result['varianten'][0];
        $this->newLine();
        $this->info("=== VOORBEELD POULES (beste variant) ===");

        foreach (array_slice($beste['poules'], 0, 3) as $i => $poule) {
            $this->newLine();
            $this->line("Poule " . ($i + 1) . " ({$poule['leeftijd_groep']}, {$poule['gewicht_groep']}):");
            $this->line("  Range: L={$poule['leeftijd_range']}j, G={$poule['gewicht_range']}kg, B={$poule['band_range']}");

            foreach ($poule['judokas'] as $judoka) {
                $this->line("  - {$judoka->naam} ({$judoka->leeftijd}j, {$judoka->gewicht}kg, {$judoka->band})");
            }
        }

        return Command::SUCCESS;
    }

    private function genereerTestJudokas(int $aantal): Collection
    {
        $judokas = collect();

        for ($i = 0; $i < $aantal; $i++) {
            $leeftijd = rand(7, 15);
            $basisGewicht = 20 + ($leeftijd - 6) * 4;
            $gewicht = round($basisGewicht + (rand(-50, 100) / 10), 1);
            $maxBand = min($leeftijd - 6, 6);
            $bandIndex = rand(0, $maxBand);

            $judokas->push((object) [
                'id' => $i + 1,
                'naam' => "Judoka_" . ($i + 1),
                'leeftijd' => $leeftijd,
                'gewicht' => $gewicht,
                'band' => self::BANDEN[$bandIndex],
                'geslacht' => rand(0, 1) ? 'M' : 'V',
            ]);
        }

        return $judokas;
    }
}
