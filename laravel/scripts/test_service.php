<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DynamischeIndelingService;

$toernooi = App\Models\Toernooi::first();
echo "Toernooi: {$toernooi->naam}\n";

// Haal judoka's van categorie "Jeugd" of eerste categorie
$alleJudokas = App\Models\Judoka::where('toernooi_id', $toernooi->id)->get();

// Filter op jongste leeftijden (5-6)
$judokas = $alleJudokas->filter(fn($j) => $j->leeftijd >= 5 && $j->leeftijd <= 6);

echo "Judoka's 5-6 jaar: " . $judokas->count() . "\n\n";

// Test de service
$service = new DynamischeIndelingService();
$result = $service->berekenIndeling($judokas, 1, 3.0);

echo "=== RESULTAAT ===\n";
echo "Aantal poules: " . count($result['poules']) . "\n";
echo "Score: " . $result['score'] . "\n\n";

foreach ($result['poules'] as $i => $poule) {
    echo "Poule " . ($i+1) . " (size=" . count($poule['judokas']) . ", range={$poule['gewicht_range']}kg):\n";
    foreach ($poule['judokas'] as $j) {
        echo "  - {$j->voornaam} | {$j->leeftijd}j | {$j->gewicht}kg\n";
    }
    echo "\n";
}
