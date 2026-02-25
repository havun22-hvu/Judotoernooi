<?php

namespace App\Services;

use App\Mail\AutoFixProposalMail;
use App\Models\AutofixProposal;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AutoFixService
{
    protected const MAX_ATTEMPTS = 2;

    /**
     * Handle an exception: analyze with Claude, auto-apply fix (max 2 attempts).
     * Only sends email if both attempts fail.
     */
    public function handle(Throwable $e): void
    {
        if (!config('autofix.enabled', false)) {
            return;
        }

        if (!$this->shouldProcess($e)) {
            return;
        }

        try {
            $file = $this->relativePath($e->getFile());
            $line = $e->getLine();
            $codeContext = $this->gatherCodeContext($e);
            $previousAttempt = null;

            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                // Ask Claude for analysis and fix
                $analysis = $this->askClaude($e, $codeContext, $attempt, $previousAttempt);

                if (!$analysis) {
                    Log::info("AutoFix: Claude returned no analysis (attempt {$attempt})");
                    continue;
                }

                // Check if Claude recommends NOTIFY_ONLY (no code fix possible)
                if ($this->isNotifyOnly($analysis['analysis'])) {
                    $proposal = $this->createProposal($e, $file, $line, $codeContext, $analysis, $attempt);
                    $proposal->update(['status' => 'notify_only']);
                    $this->sendNotifyOnlyNotification($e, $file, $line, $proposal);
                    Log::info('AutoFix: NOTIFY_ONLY - no code fix, notification sent', [
                        'proposal_id' => $proposal->id,
                        'exception' => get_class($e),
                    ]);
                    return;
                }

                // Create proposal record
                $proposal = $this->createProposal($e, $file, $line, $codeContext, $analysis, $attempt);

                // Try to apply the fix directly
                $applyResult = $this->applyFix($proposal);

                if ($applyResult === true) {
                    // Fix applied successfully
                    $proposal->update([
                        'status' => 'applied',
                        'applied_at' => now(),
                    ]);

                    Log::info("AutoFix: Fix applied successfully", [
                        'proposal_id' => $proposal->id,
                        'attempt' => $attempt,
                        'exception' => get_class($e),
                        'file' => $file,
                    ]);

                    $this->sendSuccessNotification($e, $file, $line, $proposal, $attempt);

                    return;
                }

                // Fix failed, store error for next attempt
                $previousAttempt = [
                    'analysis' => $analysis['analysis'],
                    'error' => $applyResult,
                ];

                $proposal->update([
                    'status' => 'failed',
                    'apply_error' => $applyResult,
                ]);

                Log::warning("AutoFix: Apply failed (attempt {$attempt})", [
                    'proposal_id' => $proposal->id,
                    'error' => $applyResult,
                ]);
            }

            // Both attempts failed - send email to admin
            $this->sendFailureNotification($e, $file, $line);

        } catch (Throwable $serviceError) {
            // AutoFix mag NOOIT de error handling breken
            Log::warning('AutoFix service failed', [
                'error' => $serviceError->getMessage(),
                'original_exception' => get_class($e),
            ]);

            // Mark last proposal as failed if it exists and is still pending
            if (isset($proposal) && $proposal->status === 'pending') {
                try {
                    $proposal->update([
                        'status' => 'failed',
                        'apply_error' => 'Service error: ' . $serviceError->getMessage(),
                    ]);
                } catch (Throwable $updateError) {
                    // Ignore - we're already in error handling
                }
            }
        }
    }

    /**
     * Check if this exception should be processed.
     */
    protected function shouldProcess(Throwable $e): bool
    {
        // Check excluded exception classes (supports both instanceof and string comparison)
        foreach (config('autofix.excluded_exceptions', []) as $excluded) {
            if ($e instanceof $excluded || get_class($e) === $excluded) {
                return false;
            }
        }

        // Check excluded file path patterns
        $errorFile = $e->getFile();
        foreach (config('autofix.excluded_file_patterns', []) as $pattern) {
            if (preg_match($pattern, $errorFile)) {
                Log::info('AutoFix: Skipping excluded file pattern', ['file' => $errorFile, 'pattern' => $pattern]);
                return false;
            }
        }

        // Skip errors from files outside the project
        if (!$this->isProjectFile($errorFile)) {
            // Check if there's at least one project file in the stack trace
            $hasProjectFile = false;
            foreach ($e->getTrace() as $frame) {
                if (isset($frame['file']) && $this->isProjectFile($frame['file'])) {
                    $hasProjectFile = true;
                    break;
                }
            }
            if (!$hasProjectFile) {
                Log::info('AutoFix: Skipping - no project file in stack trace', ['file' => $this->relativePath($errorFile)]);
                return false;
            }
        }

        $file = $this->relativePath($e->getFile());
        if (AutofixProposal::recentlyAnalyzed(get_class($e), $file, $e->getLine())) {
            return false;
        }

        // Check if the same file was already fixed by AutoFix in the past 24 hours
        $fixTargetFile = $this->findFixTargetFile($e);
        if ($fixTargetFile && AutofixProposal::where('file', $fixTargetFile)
            ->where('status', 'applied')
            ->where('applied_at', '>=', now()->subHours(24))
            ->exists()) {
            Log::info('AutoFix: Skipping - file was already fixed in past 24h', ['file' => $fixTargetFile]);
            return false;
        }

        return true;
    }

    /**
     * Find the most likely fix target file from the exception.
     */
    protected function findFixTargetFile(Throwable $e): ?string
    {
        if ($this->isProjectFile($e->getFile())) {
            return $this->relativePath($e->getFile());
        }

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? '';
            if ($file && $this->isProjectFile($file)) {
                return $this->relativePath($file);
            }
        }

        return null;
    }

    /**
     * Gather source code context from the stack trace.
     * When the error originates in vendor code, follows the stack trace
     * to find the first project file as the real fix target.
     */
    protected function gatherCodeContext(Throwable $e): string
    {
        $context = [];
        $maxFiles = config('autofix.max_context_files', 5);
        $maxSize = config('autofix.max_file_size', 50000);
        $seen = [];

        $primaryFile = $e->getFile();
        $errorIsInVendor = !$this->isProjectFile($primaryFile);

        // Include vendor error location as reference (not editable, but informative)
        if ($errorIsInVendor) {
            $vendorRelPath = $this->relativePath($primaryFile);
            $vendorContent = $this->readFileWithContext($primaryFile, $e->getLine(), 10);
            $context[] = "=== {$vendorRelPath} (VENDOR - error origin, NOT editable) ===\n{$vendorContent}";
        }

        if (file_exists($primaryFile) && !$errorIsInVendor) {
            $relPath = $this->relativePath($primaryFile);
            $seen[$relPath] = true;
            $content = $this->readFileForContext($primaryFile, $e->getLine());
            $label = $this->isFullFileContent($primaryFile)
                ? "{$relPath} (FULL FILE - error at line {$e->getLine()}, fix the code here)"
                : "{$relPath} (error at line {$e->getLine()})";
            $context[] = "=== {$label} ===\n{$content}";
        }

        // Walk the stack trace for project files
        // When error is in vendor, the first project file is the likely fix target
        $foundProjectFile = false;
        foreach ($e->getTrace() as $frame) {
            if (count($context) >= $maxFiles) {
                break;
            }

            $file = $frame['file'] ?? '';
            if (!$file || !file_exists($file) || !$this->isProjectFile($file)) {
                continue;
            }

            $relPath = $this->relativePath($file);
            if (isset($seen[$relPath])) {
                continue;
            }
            $seen[$relPath] = true;

            $line = $frame['line'] ?? 1;

            if ($errorIsInVendor && !$foundProjectFile) {
                // First project file in a vendor error: send full file
                $content = $this->readFileForContext($file, $line);
                $label = $this->isFullFileContent($file)
                    ? "{$relPath} (FULL FILE - FIX TARGET, called vendor code at line {$line})"
                    : "{$relPath} (FIX TARGET - called vendor code at line {$line})";
            } else {
                $content = $this->readFileWithContext($file, $line, 10);
                $label = "{$relPath} (line {$line})";
            }
            $foundProjectFile = true;

            $context[] = "=== {$label} ===\n{$content}";
        }

        $result = implode("\n\n", $context);

        if (strlen($result) > $maxSize) {
            $result = substr($result, 0, $maxSize) . "\n\n[... truncated ...]";
        }

        return $result;
    }

    /**
     * Call HavunCore AI Proxy to analyze the error.
     */
    protected function askClaude(Throwable $e, string $codeContext, int $attempt, ?array $previousAttempt): ?array
    {
        $url = rtrim(config('autofix.havuncore_url'), '/') . '/api/ai/chat';

        $message = "PRODUCTION ERROR - AUTO-FIX REQUEST (attempt {$attempt}/" . self::MAX_ATTEMPTS . ")\n\n"
            . "Exception: " . get_class($e) . "\n"
            . "Message: " . $e->getMessage() . "\n"
            . "File: " . $this->relativePath($e->getFile()) . ":" . $e->getLine() . "\n"
            . "URL: " . (request()?->fullUrl() ?? 'N/A') . "\n"
            . "Method: " . (request()?->method() ?? 'N/A') . "\n\n"
            . "RELEVANT SOURCE CODE:\n\n"
            . $codeContext;

        // Add previous attempt info for retry
        if ($previousAttempt) {
            $message .= "\n\n--- PREVIOUS ATTEMPT FAILED ---\n"
                . "Previous analysis:\n" . $previousAttempt['analysis'] . "\n\n"
                . "Why it failed:\n" . $previousAttempt['error'] . "\n\n"
                . "Please provide a DIFFERENT fix that addresses the apply failure.";
        }

        $systemPrompt = "You are a Laravel debugging expert. A production error occurred in JudoToernooi (a judo tournament app). "
            . "Your fix will be AUTOMATICALLY APPLIED to the production server. Be extremely careful and precise.\n\n"
            . "Provide your response in EXACTLY this format:\n\n"
            . "ACTION: FIX | NOTIFY_ONLY\n"
            . "ANALYSIS: [1-2 sentences explaining the cause]\n\n"
            . "If ACTION is FIX, also include:\n"
            . "FILE: [relative path to file, e.g. app/Services/MyService.php]\n"
            . "OLD:\n```php\n[exact code to find and replace - copy EXACTLY from the source]\n```\n"
            . "NEW:\n```php\n[replacement code]\n```\n\n"
            . "RISK: [low/medium/high]\n\n"
            . "FIX STRATEGY (in order of preference):\n"
            . "1. NULL SAFETY: If the error contains 'on null', use ?-> or null checks\n"
            . "2. COLUMN/SCHEMA: If a column doesn't exist, respond with ACTION: NOTIFY_ONLY. Do NOT propose a code fix. A migration or schema change is needed.\n"
            . "3. MISSING RESOURCE: If a command/class/file doesn't exist, respond with ACTION: NOTIFY_ONLY. The resource needs to be created manually.\n"
            . "4. LOGIC FIX: If the code has a logical error, fix the logic minimally\n"
            . "5. TRY/CATCH: Only as a LAST RESORT, and NEVER around entrypoints (artisan, index.php) or entire method bodies\n\n"
            . "CRITICAL RULES:\n"
            . "- The OLD block must match the EXACT code in the file (including whitespace)\n"
            . "- Only make MINIMAL changes to fix the specific error\n"
            . "- Never change .env, config files, or database schema\n"
            . "- Never add new dependencies\n"
            . "- Never modify vendor/ files - if the error originates in vendor code, fix the PROJECT file that calls it (marked as FIX TARGET)\n"
            . "- Never modify the 'artisan' file\n"
            . "- Never wrap entire method bodies in try/catch\n"
            . "- Never invent code that you don't see in the provided context";

        try {
            $response = Http::timeout(30)->post($url, [
                'tenant' => 'judotoernooi',
                'message' => $message,
                'system_prompt' => $systemPrompt,
                'max_tokens' => 2048,
            ]);

            if (!$response->successful()) {
                Log::warning('AutoFix: AI Proxy returned error', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            if (!($data['success'] ?? false) || empty($data['response'])) {
                return null;
            }

            return [
                'analysis' => $data['response'],
                'usage' => $data['usage'] ?? [],
            ];

        } catch (Throwable $httpError) {
            Log::warning('AutoFix: AI Proxy call failed', [
                'error' => $httpError->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a proposal record in the database.
     */
    protected function createProposal(Throwable $e, string $file, int $line, string $codeContext, array $analysis, int $attempt): AutofixProposal
    {
        $trace = collect($e->getTrace())->take(10)->map(function ($frame) {
            $file = isset($frame['file']) ? $this->relativePath($frame['file']) : '';
            return ($file ? $file . ':' . ($frame['line'] ?? '?') : '')
                . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        })->filter()->implode("\n");

        // Capture user and toernooi context
        $organisator = auth('organisator')->user();
        $toernooi = $this->resolveToernooi();

        return AutofixProposal::create([
            'exception_class' => get_class($e),
            'exception_message' => Str::limit($e->getMessage(), 1000),
            'file' => $file,
            'line' => $line,
            'stack_trace' => $trace,
            'code_context' => $codeContext,
            'claude_analysis' => $analysis['analysis'],
            'proposed_diff' => $analysis['analysis'],
            'approval_token' => Str::random(64),
            'status' => 'pending',
            'url' => request()?->fullUrl(),
            'organisator_id' => $organisator?->id,
            'organisator_naam' => $organisator?->naam,
            'toernooi_id' => $toernooi?->id,
            'toernooi_naam' => $toernooi?->naam,
            'http_method' => request()?->method(),
            'route_name' => request()?->route()?->getName(),
        ]);
    }

    /**
     * Try to apply a fix proposal. Returns true on success, error string on failure.
     */
    protected function applyFix(AutofixProposal $proposal): true|string
    {
        $analysis = $proposal->claude_analysis;

        // Parse FILE: path
        if (!preg_match('/FILE:\s*(.+)/i', $analysis, $fileMatch)) {
            return 'Could not parse target file from Claude response.';
        }

        $targetFile = trim($fileMatch[1]);
        $fullPath = base_path($targetFile);

        // Only allow changes to project files (not vendor, node_modules, etc.)
        if (!$this->isProjectFile($fullPath)) {
            return "Target file is not a project file: {$targetFile}";
        }

        if (!file_exists($fullPath)) {
            return "Target file not found: {$targetFile}";
        }

        // Check if file is protected
        if (in_array($targetFile, config('autofix.protected_files', []))) {
            return "Target file is protected and cannot be modified by AutoFix: {$targetFile}";
        }

        // Check if file was already modified by AutoFix in the past 24 hours
        $backupDir = storage_path('app/autofix-backups');
        $backupPrefix = str_replace(['/', '\\'], '_', $targetFile) . '.';
        if (is_dir($backupDir)) {
            $cutoff = now()->subHours(24)->format('YmdHis');
            foreach (scandir($backupDir) as $backupFile) {
                if (str_starts_with($backupFile, $backupPrefix)) {
                    $timestamp = substr($backupFile, strlen($backupPrefix));
                    if ($timestamp >= $cutoff) {
                        return "File {$targetFile} was already modified by AutoFix in the past 24 hours. Manual review needed.";
                    }
                }
            }
        }

        // Parse OLD and NEW code blocks
        if (!preg_match('/OLD:\s*```(?:php)?\s*\n(.*?)```/s', $analysis, $oldMatch)) {
            return 'Could not parse OLD code block from Claude response.';
        }

        if (!preg_match('/NEW:\s*```(?:php)?\s*\n(.*?)```/s', $analysis, $newMatch)) {
            return 'Could not parse NEW code block from Claude response.';
        }

        $oldCode = rtrim($oldMatch[1]);
        $newCode = rtrim($newMatch[1]);

        $fileContent = file_get_contents($fullPath);

        if (strpos($fileContent, $oldCode) === false) {
            return "OLD code block not found in {$targetFile}. The code may not match exactly.";
        }

        // Count occurrences - should be exactly 1
        if (substr_count($fileContent, $oldCode) > 1) {
            return "OLD code block found multiple times in {$targetFile}. Fix is ambiguous.";
        }

        // Backup original file to storage/ (www-data has write access there)
        $backupDir = storage_path('app/autofix-backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupName = str_replace(['/', '\\'], '_', $targetFile) . '.' . date('YmdHis');
        $backupPath = $backupDir . '/' . $backupName;
        try {
            if (!copy($fullPath, $backupPath)) {
                return "Could not create backup of {$targetFile}.";
            }
        } catch (Throwable $copyError) {
            return "Could not create backup of {$targetFile}: " . $copyError->getMessage();
        }

        // Apply the replacement
        $newContent = str_replace($oldCode, $newCode, $fileContent);
        try {
            file_put_contents($fullPath, $newContent);
        } catch (Throwable $writeError) {
            // Restore from backup if write fails
            copy($backupPath, $fullPath);
            return "Could not write to {$targetFile}: " . $writeError->getMessage();
        }

        // Clear caches
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fullPath, true);
        }

        try {
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            // Non-critical
        }

        return true;
    }

    /**
     * Send success notification email after fix applied.
     */
    protected function sendSuccessNotification(Throwable $e, string $file, int $line, AutofixProposal $proposal, int $attempt): void
    {
        $email = config('autofix.email');
        if (!$email) {
            return;
        }

        try {
            $body = "[AutoFix OK] Fix automatisch toegepast (poging {$attempt})\n\n"
                . "Exception: " . get_class($e) . "\n"
                . "Message: " . $e->getMessage() . "\n"
                . "File: {$file}:{$line}\n"
                . "URL: " . (request()?->fullUrl() ?? 'N/A') . "\n"
                . "Time: " . now()->format('d-m-Y H:i:s') . "\n\n"
                . "--- Claude's analyse ---\n"
                . Str::limit($proposal->claude_analysis, 500) . "\n\n"
                . "Backup: {$file}.autofix-backup." . date('YmdHis');

            Mail::raw($body, function ($message) use ($email, $e) {
                $message->to($email)
                    ->subject('[AutoFix OK] ' . class_basename(get_class($e)) . ' - fix toegepast');
            });

            $proposal->update(['email_sent_at' => now()]);

        } catch (Throwable $mailError) {
            Log::warning('AutoFix: Success notification email failed', [
                'error' => $mailError->getMessage(),
            ]);
        }
    }

    /**
     * Send failure notification email after all attempts exhausted.
     */
    protected function sendFailureNotification(Throwable $e, string $file, int $line): void
    {
        $email = config('autofix.email');
        if (!$email) {
            return;
        }

        // Get the most recent proposals for this error
        $proposals = AutofixProposal::where('exception_class', get_class($e))
            ->where('file', $file)
            ->where('line', $line)
            ->where('status', 'failed')
            ->latest()
            ->take(self::MAX_ATTEMPTS)
            ->get();

        try {
            if ($proposals->isNotEmpty()) {
                Mail::to($email)->send(new AutoFixProposalMail($proposals->first(), $proposals));

                foreach ($proposals as $proposal) {
                    $proposal->update(['email_sent_at' => now()]);
                }
            } else {
                // No proposals created (Claude API unreachable) - send plain text notification
                $body = "[AutoFix] Could not analyze error - AI Proxy unreachable\n\n"
                    . "Exception: " . get_class($e) . "\n"
                    . "Message: " . $e->getMessage() . "\n"
                    . "File: {$file}:{$line}\n"
                    . "URL: " . (request()?->fullUrl() ?? 'N/A') . "\n"
                    . "Time: " . now()->format('d-m-Y H:i:s');

                Mail::raw($body, function ($message) use ($email, $e) {
                    $message->to($email)
                        ->subject('[AutoFix] AI Proxy onbereikbaar - ' . class_basename(get_class($e)));
                });
            }

        } catch (Throwable $mailError) {
            Log::warning('AutoFix: Failure notification email failed', [
                'error' => $mailError->getMessage(),
            ]);
        }
    }

    /**
     * Check if Claude's response indicates NOTIFY_ONLY (no code fix possible).
     */
    protected function isNotifyOnly(string $analysis): bool
    {
        return (bool) preg_match('/ACTION:\s*NOTIFY_ONLY/i', $analysis);
    }

    /**
     * Send notification email when Claude determines no code fix is possible.
     */
    protected function sendNotifyOnlyNotification(Throwable $e, string $file, int $line, AutofixProposal $proposal): void
    {
        $email = config('autofix.email');
        if (!$email) {
            return;
        }

        try {
            // Extract analysis from Claude's response
            $analysisText = $proposal->claude_analysis;
            if (preg_match('/ANALYSIS:\s*(.+?)(?:\n\n|$)/s', $analysisText, $match)) {
                $analysisText = trim($match[1]);
            }

            $body = "[AutoFix] Handmatige actie nodig - geen code fix mogelijk\n\n"
                . "Exception: " . get_class($e) . "\n"
                . "Message: " . $e->getMessage() . "\n"
                . "File: {$file}:{$line}\n"
                . "URL: " . (request()?->fullUrl() ?? 'N/A') . "\n"
                . "Time: " . now()->format('d-m-Y H:i:s') . "\n\n"
                . "--- Claude's analyse ---\n"
                . $analysisText . "\n\n"
                . "Dit probleem kan niet automatisch opgelost worden. "
                . "Waarschijnlijk is een migration, schema wijziging, of handmatige interventie nodig.";

            Mail::raw($body, function ($message) use ($email, $e) {
                $message->to($email)
                    ->subject('[AutoFix] Handmatige actie nodig - ' . class_basename(get_class($e)));
            });

            $proposal->update(['email_sent_at' => now()]);

        } catch (Throwable $mailError) {
            Log::warning('AutoFix: NOTIFY_ONLY email failed', [
                'error' => $mailError->getMessage(),
            ]);
        }
    }

    /**
     * Try to resolve the current toernooi from route parameters.
     */
    protected function resolveToernooi(): ?object
    {
        try {
            $route = request()?->route();
            if (!$route) {
                return null;
            }

            // Try route model binding first
            $toernooi = $route->parameter('toernooi');
            if ($toernooi && is_object($toernooi) && isset($toernooi->id, $toernooi->naam)) {
                return $toernooi;
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Check if a file belongs to the project (not vendor/framework).
     */
    protected function isProjectFile(string $path): bool
    {
        $basePath = base_path();
        if (!str_starts_with($path, $basePath)) {
            return false;
        }

        $relative = $this->relativePath($path);
        return !str_starts_with($relative, 'vendor/')
            && !str_starts_with($relative, 'node_modules/')
            && !str_starts_with($relative, 'storage/');
    }

    /**
     * Convert absolute path to relative project path.
     */
    protected function relativePath(string $path): string
    {
        return str_replace(
            [base_path() . '/', base_path() . '\\'],
            '',
            $path
        );
    }

    /**
     * Read a file for context: full file if < 50KB, otherwise 100 lines around target.
     */
    protected function readFileForContext(string $path, int $targetLine): string
    {
        if ($this->isFullFileContent($path)) {
            $content = file_get_contents($path);
            if ($content === false) {
                return '[Could not read file]';
            }
            // Add line numbers
            $lines = explode("\n", $content);
            $result = [];
            foreach ($lines as $i => $line) {
                $lineNum = $i + 1;
                $marker = ($lineNum === $targetLine) ? ' >>>' : '    ';
                $result[] = sprintf('%s %4d | %s', $marker, $lineNum, $line);
            }
            return implode("\n", $result);
        }

        // Large file: 100 lines around the error
        return $this->readFileWithContext($path, $targetLine, 100);
    }

    /**
     * Check if a file is small enough to send in full (< 50KB).
     */
    protected function isFullFileContent(string $path): bool
    {
        return file_exists($path) && filesize($path) < 50000;
    }

    /**
     * Read a file with context lines around a specific line number.
     */
    protected function readFileWithContext(string $path, int $targetLine, int $contextLines = 10): string
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return '[Could not read file]';
        }

        $start = max(0, $targetLine - $contextLines - 1);
        $end = min(count($lines), $targetLine + $contextLines);

        $result = [];
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $marker = ($lineNum === $targetLine) ? ' >>>' : '    ';
            $result[] = sprintf('%s %4d | %s', $marker, $lineNum, $lines[$i]);
        }

        return implode("\n", $result);
    }
}
