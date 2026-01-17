<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$toernooi = App\Models\Toernooi::first();
$alleJudokas = App\Models\Judoka::where('toernooi_id', $toernooi->id)->get();
$judokas = $alleJudokas->filter(fn($j) => $j->leeftijd >= 5 && $j->leeftijd <= 6);

echo "Testing Python solver directly from service logic...\n\n";

// Replicate what DynamischeIndelingService does
$maxKg = 3.0;
$maxLft = 1;
$voorkeur = [5, 4, 6, 3];

$judokaMap = [];
$pythonInput = [
    'max_kg_verschil' => $maxKg,
    'max_leeftijd_verschil' => $maxLft,
    'poule_grootte_voorkeur' => $voorkeur,
    'judokas' => [],
];

$bandMapping = ['wit' => 0, 'geel' => 1, 'oranje' => 2, 'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6];

foreach ($judokas as $judoka) {
    $id = $judoka->id;
    $judokaMap[$id] = $judoka;

    $gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
    $band = strtolower(explode(' ', $judoka->band ?? 'wit')[0]);
    $bandNum = $bandMapping[$band] ?? 0;

    $pythonInput['judokas'][] = [
        'id' => $id,
        'leeftijd' => $judoka->leeftijd ?? 0,
        'gewicht' => (float) $gewicht,
        'band' => $bandNum,
        'club_id' => $judoka->club_id ?? 0,
    ];

    echo "{$judoka->voornaam} | id=$id | {$judoka->leeftijd}j | {$gewicht}kg | band=$bandNum\n";
}

$scriptPath = base_path('scripts/poule_solver.py');
$pythonCmd = 'python';

echo "\n=== CALLING PYTHON ===\n";
$inputJson = json_encode($pythonInput);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open(
    [$pythonCmd, $scriptPath],
    $descriptors,
    $pipes,
    base_path('scripts')
);

if (!is_resource($process)) {
    echo "Failed to start!\n";
    exit;
}

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

if (!$result) {
    echo "Failed to parse JSON!\n";
    echo "Raw output: $output\n";
    exit;
}

if (!$result['success']) {
    echo "Solver failed: " . ($result['error'] ?? 'unknown') . "\n";
    exit;
}

echo "\n=== POULES ===\n";
foreach ($result['poules'] as $i => $p) {
    echo "Poule " . ($i+1) . " (size={$p['size']}, range={$p['gewicht_range']}kg):\n";
    foreach ($p['judoka_ids'] as $id) {
        $j = $judokaMap[$id] ?? null;
        if ($j) {
            echo "  - {$j->voornaam} | {$j->leeftijd}j | {$j->gewicht}kg\n";
        }
    }
}
