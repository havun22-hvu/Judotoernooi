<?php

namespace App\Services;

use App\Models\Toernooi;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Builds the complete offline noodpakket for download.
 * Combines: pre-built Go launcher + portable PHP + stripped Laravel + SQLite data + license.
 */
class OfflinePackageBuilder
{
    private OfflineExportService $exportService;

    /** Path to pre-built launcher binary (Go cross-compiled for Windows) */
    private string $launcherPath;

    /** Path to portable PHP directory */
    private string $phpDir;

    /** Path to stripped Laravel app */
    private string $laravelOfflineDir;

    public function __construct(OfflineExportService $exportService)
    {
        $this->exportService = $exportService;
        $this->launcherPath = base_path('../offline/build/launcher.exe');
        $this->phpDir = base_path('../offline/build/php');
        $this->laravelOfflineDir = base_path('../offline/build/laravel');
    }

    /**
     * Check if all build prerequisites are available.
     *
     * @return array{ready: bool, missing: string[]}
     */
    public function checkPrerequisites(): array
    {
        $missing = [];

        if (!file_exists($this->launcherPath)) {
            $missing[] = 'Go launcher binary (offline/build/launcher.exe)';
        }

        if (!is_dir($this->phpDir) || !file_exists($this->phpDir . '/php.exe')) {
            $missing[] = 'Portable PHP directory (offline/build/php/php.exe)';
        }

        if (!is_dir($this->laravelOfflineDir)) {
            $missing[] = 'Stripped Laravel app (offline/build/laravel/)';
        }

        return [
            'ready' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Build the complete offline package for a tournament.
     *
     * @return string Path to the generated zip/exe file
     */
    public function build(Toernooi $toernooi): string
    {
        $prereqs = $this->checkPrerequisites();
        if (!$prereqs['ready']) {
            throw new \RuntimeException(
                'Build prerequisites missing: ' . implode(', ', $prereqs['missing'])
            );
        }

        // Step 1: Export tournament data to SQLite
        $sqlitePath = $this->exportService->export($toernooi);

        // Step 2: Generate license
        $license = $this->exportService->generateLicense($toernooi);
        $licensePath = storage_path('app/offline_license_' . $toernooi->id . '.json');
        file_put_contents($licensePath, json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Step 3: Create the bundle.zip (everything the Go launcher will embed)
        $bundlePath = storage_path('app/offline_bundle_' . $toernooi->id . '.zip');
        $this->createBundle($bundlePath, $sqlitePath, $licensePath);

        // Step 4: Create the final self-extracting exe
        // The Go launcher has bundle.zip embedded at compile time.
        // For dynamic packages, we create a zip with launcher + bundle.
        $outputPath = storage_path('app/noodpakket_' . $toernooi->id . '.zip');
        $this->createFinalPackage($outputPath, $bundlePath);

        // Clean up intermediate files
        @unlink($sqlitePath);
        @unlink($licensePath);
        @unlink($bundlePath);

        return $outputPath;
    }

    /**
     * Build the bundle.zip that contains PHP + Laravel + data.
     * This is what the Go launcher extracts at runtime.
     */
    private function createBundle(string $outputPath, string $sqlitePath, string $licensePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Kan bundle.zip niet aanmaken');
        }

        // Add license
        $zip->addFile($licensePath, 'license.json');

        // Add SQLite database
        $zip->addFile($sqlitePath, 'database.sqlite');

        // Add portable PHP
        $this->addDirectoryToZip($zip, $this->phpDir, 'php');

        // Add stripped Laravel app
        $this->addDirectoryToZip($zip, $this->laravelOfflineDir, 'laravel');

        // Add app key file (from current environment, for HMAC verification)
        $zip->addFromString('laravel/app_key.txt', config('app.key'));

        $zip->close();
    }

    /**
     * Create the final downloadable package.
     * Contains the launcher exe + bundle zip.
     */
    private function createFinalPackage(string $outputPath, string $bundlePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Kan noodpakket.zip niet aanmaken');
        }

        // Add launcher
        $zip->addFile($this->launcherPath, 'noodpakket.exe');

        // Add bundle (launcher will look for this next to itself)
        $zip->addFile($bundlePath, 'bundle.zip');

        // Add README
        $zip->addFromString('LEES MIJ.txt', $this->getReadmeContent());

        $zip->close();
    }

    /**
     * Recursively add a directory to a zip archive.
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPrefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = $zipPrefix . '/' . $iterator->getSubPathname();
            // Normalize path separators for zip
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($item->getPathname(), $relativePath);
            }
        }
    }

    private function getReadmeContent(): string
    {
        return <<<'TXT'
JudoToernooi Noodpakket - Offline Server
=========================================

INSTRUCTIES:
1. Pak deze zip uit in een map op uw laptop
2. Dubbelklik op "noodpakket.exe"
3. De server start automatisch
4. Tablets verbinden via het getoonde IP adres

VEREISTEN:
- Windows 10 of nieuwer
- Laptop verbonden met hetzelfde WiFi netwerk als de tablets
- Eventueel Windows Firewall toestemming geven voor poort 8000

PROBLEMEN?
- Als tablets niet kunnen verbinden: controleer of laptop en tablets op hetzelfde WiFi zitten
- Als server niet start: probeer als Administrator uit te voeren
- Noodpakket verlopen? Download een nieuw pakket via judotournament.org

Na het toernooi:
- Start de server opnieuw als er internet is
- Klik op "Upload resultaten naar cloud" in het menu

Havun - judotournament.org
TXT;
    }
}
