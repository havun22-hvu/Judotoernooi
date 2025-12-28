<?php

/**
 * Test script voor EliminatieService
 *
 * Gebruik: php test_eliminatie.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EliminatieService;

$service = new EliminatieService();

echo "=== ELIMINATIE BRACKET TEST ===\n\n";

// Test statistieken voor verschillende aantallen judoka's
$testCases = [8, 12, 16, 20, 24, 29, 32];

foreach ($testCases as $n) {
    $stats = $service->berekenStatistieken($n);

    echo "--- {$n} Judoka's ---\n";
    echo "Doel (D):            {$stats['doel']}\n";
    echo "A-groep wedstrijden: {$stats['a_wedstrijden']} (N-1 = " . ($n - 1) . ")\n";
    echo "B-groep wedstrijden: {$stats['b_wedstrijden']} (N-4 = " . ($n - 4) . ")\n";
    echo "Totaal:              {$stats['totaal_wedstrijden']} (2N-5 = " . (2 * $n - 5) . ")\n";
    echo "Voorronde weds:      {$stats['voorronde_wedstrijden']} (N-D = " . ($n - $stats['doel']) . ")\n";
    echo "Byes:                {$stats['byes']} (2D-N = " . (2 * $stats['doel'] - $n) . ")\n";
    echo "\n";
}

echo "=== FORMULES VERIFICATIE ===\n";
echo "A-groep: N - 1 wedstrijden\n";
echo "B-groep: N - 4 wedstrijden\n";
echo "Totaal:  2N - 5 wedstrijden\n";
echo "Doel D:  grootste macht van 2 <= N\n";
echo "Voorronde: N - D wedstrijden (om naar D te komen)\n";
echo "Byes: 2D - N (judoka's die direct doorgaan)\n\n";

echo "=== 29 JUDOKA's DETAIL ===\n";
$n = 29;
$d = 16;  // 2^4 = 16 <= 29 < 32

echo "N = 29, D = 16\n";
echo "\nA-GROEP:\n";
echo "- 1/16 finale: " . ($n - $d) . " wedstrijden (" . (2 * ($n - $d)) . " judoka's spelen)\n";
echo "- Byes: " . (2 * $d - $n) . " judoka's gaan direct naar 1/8\n";
echo "- 1/8 finale: 8 wedstrijden (16 judoka's)\n";
echo "- 1/4 finale: 4 wedstrijden (8 judoka's)\n";
echo "- 1/2 finale: 2 wedstrijden (4 judoka's)\n";
echo "- Finale: 1 wedstrijd (2 judoka's) → Goud + Zilver\n";
echo "Totaal A: " . ($n - 1) . " wedstrijden\n";

echo "\nB-GROEP:\n";
$eersteInstroom = ($n - $d) + ($d / 2);  // Verliezers 1/16 + verliezers 1/8
echo "- Instroom batch 1: " . ($n - $d) . " (verliezers 1/16)\n";
echo "- Instroom batch 2: " . ($d / 2) . " (verliezers 1/8)\n";
echo "- Totaal eerste instroom: {$eersteInstroom}\n";
echo "- B voorronde: " . max(0, $eersteInstroom - 16) . " wedstrijden (om naar 16 te komen)\n";
echo "- B 1/8 (1): 8 wedstrijden\n";
echo "- B 1/8 (2): 8 wedstrijden (+verliezers A 1/8)\n";
echo "- B 1/4 (1): 4 wedstrijden\n";
echo "- B 1/4 (2): 4 wedstrijden (+verliezers A 1/4)\n";
echo "- B 1/2 (1): 2 wedstrijden\n";
echo "- B 1/2 (2): 2 wedstrijden (+verliezers A 1/2) = BRONS\n";
echo "Totaal B: " . ($n - 4) . " wedstrijden\n";

echo "\nTOTAAL: " . (2 * $n - 5) . " wedstrijden\n";
echo "4 Medailles: 1x Goud, 1x Zilver, 2x Brons\n";
