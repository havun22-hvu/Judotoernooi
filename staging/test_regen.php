<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Poule;
use App\Services\EliminatieService;

$service = new EliminatieService();

// Test alle eliminatie poules
$poules = Poule::where('type', 'eliminatie')->get();
foreach ($poules as $poule) {
    $judokas = $poule->judokas;
    if ($judokas->count() < 2) continue;

    $result = $service->genereerBracket($poule, $judokas);

    echo "Poule {$poule->id} ({$judokas->count()} judokas):\n";
    echo "  A: doel={$result['doel_a']}, voorronde={$result['voorronde_a']}, weds={$result['a_wedstrijden']}\n";
    echo "  B: weds={$result['b_wedstrijden']}\n";

    // Toon B-rondes
    $bWeds = $poule->wedstrijden()->where('groep', 'B')->get()->groupBy('ronde');
    foreach ($bWeds as $ronde => $weds) {
        echo "    {$ronde}: {$weds->count()} weds\n";
    }
    echo "\n";
}
