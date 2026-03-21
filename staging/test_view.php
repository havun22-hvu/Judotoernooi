<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$toernooi = App\Models\Toernooi::first();

try {
    $html = view('pages.mat.interface', [
        'toernooi' => $toernooi,
        'blokken' => $toernooi->blokken,
        'matten' => $toernooi->matten,
    ])->render();

    // Zoek naar function matInterface
    if (strpos($html, 'function matInterface()') !== false) {
        echo "✓ matInterface() gevonden in output\n";

        // Extract the function definition line
        preg_match('/function matInterface\(\).*?\{/s', $html, $matches);
        if ($matches) {
            echo "Function start: " . substr($matches[0], 0, 100) . "\n";
        }
    } else {
        echo "✗ matInterface() NIET gevonden in output!\n";

        // Check for PHP errors in output
        if (strpos($html, 'Error') !== false || strpos($html, 'Exception') !== false) {
            preg_match('/(Error|Exception).*/', $html, $errors);
            echo "Mogelijke error: " . ($errors[0] ?? 'onbekend') . "\n";
        }
    }

    // Check for script tags
    preg_match_all('/<script>/', $html, $scriptTags);
    echo "Aantal <script> tags: " . count($scriptTags[0]) . "\n";

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
