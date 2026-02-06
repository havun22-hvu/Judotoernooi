<?php
// One-time script to reclassify judokas after empty label fix
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$toernooi = App\Models\Toernooi::find(7);
if (!$toernooi) {
    echo "Toernooi 7 niet gevonden\n";
    exit(1);
}

$config = $toernooi->getAlleGewichtsklassen();
$jaar = $toernooi->datum ? $toernooi->datum->year : (int) date('Y');
$classifier = new App\Services\CategorieClassifier($config);
$updated = 0;

foreach ($toernooi->judokas()->get() as $j) {
    $r = $classifier->classificeer($j, $jaar);
    if ($j->leeftijdsklasse !== $r['label'] || $j->categorie_key !== $r['key']) {
        $j->update([
            'leeftijdsklasse' => $r['label'],
            'categorie_key' => $r['key'],
            'sort_categorie' => $r['sortCategorie'],
        ]);
        $updated++;
    }
}

// Also update poules with empty leeftijdsklasse
$poules = $toernooi->poules()->get();
$pouleUpdated = 0;
foreach ($poules as $poule) {
    if (empty($poule->leeftijdsklasse) && !empty($poule->categorie_key)) {
        $catConfig = $config[$poule->categorie_key] ?? null;
        if ($catConfig) {
            $label = !empty($catConfig['label']) ? $catConfig['label'] : $poule->categorie_key;
            $poule->update(['leeftijdsklasse' => $label]);
            $pouleUpdated++;
        }
    }
}

echo "Herclassificatie: $updated judokas, $pouleUpdated poules bijgewerkt\n";
