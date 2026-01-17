<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$toernooi = App\Models\Toernooi::first();
echo "Toernooi: {$toernooi->naam}\n";

// Haal alle judoka's en bereken leeftijd
$alleJudokas = App\Models\Judoka::where('toernooi_id', $toernooi->id)->get();

// Leeftijdsverdeling
$leeftijdCounts = [];
foreach ($alleJudokas as $j) {
    $lft = $j->leeftijd; // Dit is een accessor
    $leeftijdCounts[$lft] = ($leeftijdCounts[$lft] ?? 0) + 1;
}
ksort($leeftijdCounts);

echo "\n=== LEEFTIJDSVERDELING ===\n";
foreach ($leeftijdCounts as $lft => $aantal) {
    echo "Leeftijd $lft: $aantal judoka's\n";
}

// Haal judoka's van de jongste leeftijden
$minLeeftijd = min(array_keys($leeftijdCounts));
$maxLeeftijd = $minLeeftijd + 1;

$judokas = $alleJudokas->filter(fn($j) => $j->leeftijd >= $minLeeftijd && $j->leeftijd <= $maxLeeftijd)
    ->sortBy('gewicht');

echo "\n=== JUDOKAS {$minLeeftijd}-{$maxLeeftijd} JAAR ===\n";
echo "Gevonden: " . $judokas->count() . " judoka's\n\n";

// Haal config
$config = $toernooi->gewichtsklassen ?? [];
$firstCategory = reset($config);
$maxKg = $firstCategory['max_kg_verschil'] ?? 3;
$maxLft = $firstCategory['max_leeftijd_verschil'] ?? 1;

echo "Config: max_kg=$maxKg, max_lft=$maxLft\n\n";

$pythonInput = [
    'max_kg_verschil' => (float)$maxKg,
    'max_leeftijd_verschil' => (int)$maxLft,
    'poule_grootte_voorkeur' => [5, 4, 6, 3],
    'judokas' => [],
];

foreach ($judokas->take(20) as $j) {
    $band = strtolower(explode(' ', $j->band ?? 'wit')[0]);
    $bandNum = ['wit' => 0, 'geel' => 1, 'oranje' => 2, 'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6][$band] ?? 0;

    echo "{$j->voornaam} | {$j->leeftijd}j | {$j->gewicht}kg | band=$bandNum\n";

    $pythonInput['judokas'][] = [
        'id' => $j->id,
        'leeftijd' => $j->leeftijd,
        'gewicht' => (float)$j->gewicht,
        'band' => $bandNum,
        'club_id' => $j->club_id ?? 0,
    ];
}

if (empty($pythonInput['judokas'])) {
    echo "\nGeen judoka's gevonden!\n";
    exit;
}

echo "\n=== CALLING PYTHON ===\n";
$scriptPath = __DIR__ . '/poule_solver.py';
$inputJson = json_encode($pythonInput);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open(['python', $scriptPath], $descriptors, $pipes, __DIR__);

if (is_resource($process)) {
    fwrite($pipes[0], $inputJson);
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    echo "Exit code: $exitCode\n";
    if ($stderr) echo "Stderr: $stderr\n";

    $result = json_decode($output, true);
    if ($result && $result['success']) {
        echo "\n=== POULES ===\n";
        // Map IDs terug naar namen
        $judokaMap = $judokas->keyBy('id');
        foreach ($result['poules'] as $i => $p) {
            $namen = [];
            foreach ($p['judoka_ids'] as $id) {
                $j = $judokaMap[$id] ?? null;
                if ($j) {
                    $band = strtolower(explode(' ', $j->band ?? 'wit')[0]);
                    $bandNum = ['wit' => 0, 'geel' => 1, 'oranje' => 2, 'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6][$band] ?? 0;
                    $namen[] = "{$j->voornaam}({$j->leeftijd}j,{$j->gewicht}kg,b$bandNum)";
                }
            }
            echo "Poule " . ($i+1) . " (size={$p['size']}, gew_range={$p['gewicht_range']}kg, lft_range={$p['leeftijd_range']}j):\n";
            echo "  " . implode(', ', $namen) . "\n";
        }
        echo "\nStats: " . json_encode($result['stats']) . "\n";
    } else {
        echo "Python output: $output\n";
    }
}
