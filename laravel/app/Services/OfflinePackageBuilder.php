<?php

namespace App\Services;

use App\Models\Toernooi;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Builds the complete offline noodpakket as a single self-extracting exe.
 *
 * Architecture:
 * 1. Pre-built Go launcher (generic, ~2MB)
 * 2. Bundle.zip (PHP + Laravel + SQLite + license) appended to launcher
 * 3. 16-byte trailer: [8 bytes: zip offset (uint64 LE)] [8 bytes: magic "JTNOODPK"]
 *
 * Result: single noodpakket.exe that the organizer double-clicks to start.
 */
class OfflinePackageBuilder
{
    /** Magic bytes matching Go launcher's expected trailer */
    private const MAGIC = 'JTNOODPK';

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
     * Returns a single self-extracting exe.
     *
     * @return string Path to the generated noodpakket.exe
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

        // Step 3: Create the bundle.zip (PHP + Laravel + data + license)
        $bundlePath = storage_path('app/offline_bundle_' . $toernooi->id . '.zip');
        $this->createBundle($bundlePath, $sqlitePath, $licensePath);

        // Step 4: Create self-extracting exe (launcher + bundle + trailer)
        $outputPath = storage_path('app/noodpakket_' . $toernooi->id . '.exe');
        $this->createSelfExtractingExe($outputPath, $bundlePath);

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
     * Create a self-extracting exe by appending bundle.zip to the launcher.
     *
     * Format: [launcher.exe bytes] [bundle.zip bytes] [8 bytes: zip offset] [8 bytes: "JTNOODPK"]
     *
     * The Go launcher reads the last 16 bytes to find the zip offset,
     * then extracts the embedded zip from itself.
     */
    private function createSelfExtractingExe(string $outputPath, string $bundlePath): void
    {
        // Copy launcher as base
        if (!copy($this->launcherPath, $outputPath)) {
            throw new \RuntimeException('Kan launcher niet kopiëren');
        }

        $launcherSize = filesize($outputPath);
        $bundleData = file_get_contents($bundlePath);

        if ($bundleData === false) {
            throw new \RuntimeException('Kan bundle.zip niet lezen');
        }

        // Append bundle + trailer to exe
        $handle = fopen($outputPath, 'ab');
        if (!$handle) {
            throw new \RuntimeException('Kan exe niet openen voor schrijven');
        }

        // Write bundle zip data
        fwrite($handle, $bundleData);

        // Write trailer: zip offset (8 bytes, uint64 LE) + magic (8 bytes)
        fwrite($handle, pack('P', $launcherSize)); // P = uint64 little-endian
        fwrite($handle, self::MAGIC);

        fclose($handle);
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
}
