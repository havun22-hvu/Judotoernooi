<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$toernooi = App\Models\Toernooi::first();
echo "Toernooi: " . $toernooi->naam . "\n\n";

// Vind Noa
$noas = App\Models\Judoka::where('toernooi_id', $toernooi->id)
    ->where('voornaam', 'like', '%Noa%')
    ->get();

echo "=== ZOEK NOA ===\n";
foreach ($noas as $noa) {
    $poule = App\Models\Poule::find($noa->poule_id);
    echo "Noa: {$noa->leeftijd}j, {$noa->gewicht}kg, band={$noa->band}";
    if ($poule) echo ", poule#{$poule->volgnummer}";
    echo "\n";
}

// Eerste 5 poules met 5-6 jarigen
echo "\n=== POULES MET 5-6 JARIGEN ===\n";
$poules = App\Models\Poule::where('toernooi_id', $toernooi->id)
    ->with('judokas')
    ->orderBy('volgnummer')
    ->get()
    ->filter(function($p) {
        $ages = $p->judokas->pluck('leeftijd')->filter();
        return $ages->isNotEmpty() && $ages->min() <= 6;
    })
    ->take(5);

foreach ($poules as $p) {
    $gewichten = $p->judokas->pluck('gewicht')->filter();
    $range = $gewichten->max() - $gewichten->min();
    echo "\nPoule #{$p->volgnummer} (range: {$range}kg):\n";
    foreach ($p->judokas->sortBy('gewicht') as $j) {
        echo "  {$j->voornaam} | {$j->leeftijd}j | {$j->gewicht}kg | {$j->band}\n";
    }
}
