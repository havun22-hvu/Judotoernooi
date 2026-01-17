<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test Python call zoals in DynamischeIndelingService

$scriptPath = base_path('scripts/poule_solver.py');
echo "Script path: $scriptPath\n";
echo "Exists: " . (file_exists($scriptPath) ? 'YES' : 'NO') . "\n\n";

// Find Python
$pythonCmd = null;
if (PHP_OS_FAMILY === 'Windows') {
    $paths = ['python', 'python3', 'py'];
    foreach ($paths as $cmd) {
        exec("where $cmd 2>NUL", $output, $exitCode);
        if ($exitCode === 0) {
            $pythonCmd = $cmd;
            echo "Found Python: $cmd\n";
            break;
        }
        $output = [];
    }
}

if (!$pythonCmd) {
    echo "Python NOT FOUND!\n";
    exit(1);
}

// Test input
$testInput = json_encode([
    'max_kg_verschil' => 3.0,
    'max_leeftijd_verschil' => 1,
    'poule_grootte_voorkeur' => [5, 4, 6, 3],
    'judokas' => [
        ['id' => 1, 'leeftijd' => 6, 'gewicht' => 16.1, 'band' => 0],
        ['id' => 2, 'leeftijd' => 5, 'gewicht' => 16.3, 'band' => 0],
        ['id' => 3, 'leeftijd' => 6, 'gewicht' => 18.3, 'band' => 0],
        ['id' => 4, 'leeftijd' => 6, 'gewicht' => 18.7, 'band' => 0],
        ['id' => 5, 'leeftijd' => 6, 'gewicht' => 20.0, 'band' => 0],
    ]
]);

echo "\nInput JSON:\n$testInput\n\n";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

echo "Calling: $pythonCmd $scriptPath\n";
echo "Working dir: " . base_path('scripts') . "\n\n";

$process = proc_open(
    [$pythonCmd, $scriptPath],
    $descriptors,
    $pipes,
    base_path('scripts')
);

if (!is_resource($process)) {
    echo "Failed to start process!\n";
    exit(1);
}

fwrite($pipes[0], $testInput);
fclose($pipes[0]);

$output = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

echo "Exit code: $exitCode\n";
echo "Stderr: $stderr\n";
echo "Output:\n$output\n";
