<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$toernooi = App\Models\Toernooi::where('slug', 'test-toernooi-2026')->first();
$service = app(App\Services\PouleIndelingService::class);

// Pak een meisje in sen_d met lage leeftijd
$judoka = $toernooi->judokas()->where('categorie_key', 'sen_d')->orderBy('leeftijd')->first();
$config = $toernooi->getAlleGewichtsklassen();

// Check de config in de service via reflectie
$reflection = new ReflectionClass($service);
$prop = $reflection->getProperty('gewichtsklassenConfig');
$prop->setAccessible(true);

// Set config en kijk wat het is
$prop->setValue($service, $config);
$serviceConfig = $prop->getValue($service);

echo "Service config keys: " . implode(', ', array_keys($serviceConfig)) . "\n";
echo "Toernooi config keys: " . implode(', ', array_keys($config)) . "\n\n";

echo "=== u21_d config volledig ===\n";
print_r($config['u21_d']);
echo "\n";

echo "Test judoka:\n";
echo "  Naam: {$judoka->naam}\n";
echo "  Leeftijd: {$judoka->leeftijd}\n";
echo "  Geslacht: '{$judoka->geslacht}'\n";
echo "  Band: '{$judoka->band}'\n\n";

// Simuleer classificeerJudoka EXACT zoals in de service
$leeftijd = $judoka->leeftijd ?? ($toernooi->datum?->year ?? date('Y')) - $judoka->geboortejaar;
$geslacht = strtoupper($judoka->geslacht ?? '');

echo "Berekende waarden:\n";
echo "  leeftijd: $leeftijd\n";
echo "  geslacht (upper): '$geslacht'\n\n";

echo "=== Loop door categorieën ===\n";
$sortCategorie = 0;
foreach ($config as $key => $cat) {
    $maxLeeftijd = $cat['max_leeftijd'] ?? 99;
    $configGeslacht = strtoupper($cat['geslacht'] ?? 'gemengd');
    $label = strtolower($cat['label'] ?? '');

    echo "\n--- $key ---\n";
    echo "  maxLeeftijd: $maxLeeftijd\n";
    echo "  configGeslacht initial: '$configGeslacht'\n";

    // Normalize legacy values
    if ($configGeslacht === 'MEISJES') $configGeslacht = 'V';
    if ($configGeslacht === 'JONGENS') $configGeslacht = 'M';

    // Auto-detect check
    $originalGeslacht = strtolower($cat['geslacht'] ?? '');
    $isExplicitGemengd = $originalGeslacht === 'gemengd';

    echo "  originalGeslacht: '$originalGeslacht'\n";
    echo "  isExplicitGemengd: " . ($isExplicitGemengd ? 'true' : 'false') . "\n";

    if ($configGeslacht === 'GEMENGD' && !$isExplicitGemengd) {
        if (str_contains($label, 'dames') || str_contains($label, 'meisjes') || str_ends_with($key, '_d') || str_contains($key, '_d_')) {
            $configGeslacht = 'V';
            echo "  -> Auto-detect changed to V\n";
        } elseif (str_contains($label, 'heren') || str_contains($label, 'jongens') || str_ends_with($key, '_h') || str_contains($key, '_h_')) {
            $configGeslacht = 'M';
            echo "  -> Auto-detect changed to M\n";
        }
    }

    echo "  configGeslacht final: '$configGeslacht'\n";

    // Check leeftijd
    if ($leeftijd > $maxLeeftijd) {
        echo "  -> SKIP: leeftijd $leeftijd > $maxLeeftijd\n";
        $sortCategorie++;
        continue;
    }

    // Check geslacht
    if ($configGeslacht !== 'GEMENGD' && $configGeslacht !== $geslacht) {
        echo "  -> SKIP: geslacht '$configGeslacht' !== '$geslacht' en niet GEMENGD\n";
        $sortCategorie++;
        continue;
    }

    echo "  -> MATCH!\n";
    break;
}

// Nu de ECHTE classificeerJudoka aanroepen
echo "\n\n=== ECHTE classificeerJudoka via reflectie ===\n";
$method = $reflection->getMethod('classificeerJudoka');
$method->setAccessible(true);

// Zet config opnieuw
$prop->setValue($service, $config);

$result = $method->invoke($service, $judoka, $toernooi);
echo "Resultaat: ";
print_r($result);
