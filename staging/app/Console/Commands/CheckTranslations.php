<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckTranslations extends Command
{
    protected $signature = 'check:translations {--fix : Show suggestion for each violation}';
    protected $description = 'Detect hardcoded Dutch text in <script> blocks of Blade files';

    // Multi-word Dutch phrases (high confidence, no false positives)
    private const DUTCH_PHRASES = [
        'Er ging iets mis',
        'Fout bij',
        'Niet gevonden',
        'Niet ondersteund',
        'Niet beschikbaar',
        'Probeer opnieuw',
        'Komen niet overeen',
        'Bezig met',
        'Weet je zeker',
        'Geen verbinding',
        'Geen resultaten',
        'Geen geschikte',
        'Kon niet',
        'Sessie verlopen',
        'Nog geen',
        'Wil je ook',
        'Kies een',
        'Voer je',
        'Voer dezelfde',
    ];

    // Single Dutch words - only flagged in user-facing contexts
    private const DUTCH_WORDS = [
        'Welkom', 'Inloggen', 'Uitloggen', 'Opslaan', 'Verwijderen',
        'Bevestig', 'Annuleren', 'Sluiten', 'Doorsturen', 'Goedgekeurd',
        'Mislukt', 'Gelukt', 'Voltooid', 'Verlopen', 'Vernieuw',
        'Overslaan', 'Onjuiste', 'Biometrie', 'Registratie',
        'Ingesteld', 'Ingeschakeld', 'Geannuleerd',
    ];

    // Patterns that indicate user-facing string assignment
    private const USER_FACING_PATTERNS = [
        'textContent',
        'innerText',
        'innerHTML',
        'alert(',
        'confirm(',
        'showPinError(',
        'showError(',
        'showStatus(',
        'console.error(',
    ];

    public function handle(): int
    {
        $bladePath = resource_path('views');
        $files = File::allFiles($bladePath);
        $violations = 0;
        $showFix = $this->option('fix');
        $currentFile = '';

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;

            $content = $file->getContents();
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());

            // Extract <script> blocks
            if (!preg_match_all('/<script\b[^>]*>(.*?)<\/script>/si', $content, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches as $match) {
                $scriptContent = $match[1][0];
                $scriptOffset = $match[1][1];
                $lines = explode("\n", $scriptContent);

                foreach ($lines as $lineIndex => $line) {
                    // Skip translation lines (both __t object pattern and __ prefixed constants)
                    if (str_contains($line, '__t.') || str_contains($line, '@json(') || str_contains($line, "__(")
                        || preg_match('/\$\{__\w+/', $line) || preg_match('/\b__[a-zA-Z]/', $line)) {
                        continue;
                    }
                    // Skip comments
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                        continue;
                    }

                    $found = false;

                    // Check multi-word phrases (always flag if in a string)
                    foreach (self::DUTCH_PHRASES as $phrase) {
                        if (stripos($line, $phrase) !== false && $this->isInString($line, $phrase)) {
                            $absoluteLine = substr_count($content, "\n", 0, $scriptOffset) + $lineIndex + 1;
                            if ($currentFile !== $relativePath) {
                                $currentFile = $relativePath;
                                $this->newLine();
                                $this->line("  <fg=cyan>{$relativePath}</>");
                            }
                            $violations++;
                            $this->warn("    :{$absoluteLine}  " . trim($line));
                            if ($showFix) {
                                $this->info("         → Verplaats naar __t object met @json(__('...'))");
                            }
                            $found = true;
                            break;
                        }
                    }

                    if ($found) continue;

                    // Check single words only in user-facing contexts
                    if (!$this->isUserFacingLine($line)) continue;

                    foreach (self::DUTCH_WORDS as $word) {
                        if (stripos($line, $word) !== false && $this->isInString($line, $word)) {
                            $absoluteLine = substr_count($content, "\n", 0, $scriptOffset) + $lineIndex + 1;
                            if ($currentFile !== $relativePath) {
                                $currentFile = $relativePath;
                                $this->newLine();
                                $this->line("  <fg=cyan>{$relativePath}</>");
                            }
                            $violations++;
                            $this->warn("    :{$absoluteLine}  " . trim($line));
                            if ($showFix) {
                                $this->info("         → Verplaats naar __t object met @json(__('...'))");
                            }
                            break;
                        }
                    }
                }
            }
        }

        $this->newLine();
        if ($violations === 0) {
            $this->info("✓ Geen hardcoded tekst gevonden in <script> blokken.");
            return Command::SUCCESS;
        }

        $this->error("✗ {$violations} hardcoded tekst(en) gevonden.");
        $this->line("  Gebruik een __t translations object. Zie: docs/3-DEVELOPMENT/CODE-STANDAARDEN.md §14");
        return Command::FAILURE;
    }

    private function isInString(string $line, string $text): bool
    {
        // Check if the text appears inside quotes (not in a DOM selector or variable name)
        $escaped = preg_quote($text, '/');
        return (bool) preg_match("/['\"`][^'\"`]*{$escaped}[^'\"`]*['\"`]/i", $line);
    }

    private function isUserFacingLine(string $line): bool
    {
        foreach (self::USER_FACING_PATTERNS as $pattern) {
            if (str_contains($line, $pattern)) return true;
        }
        return false;
    }
}
