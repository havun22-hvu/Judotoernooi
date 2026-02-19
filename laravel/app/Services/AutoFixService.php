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
        }
    }

    /**
     * Check if this exception should be processed.
     */
    protected function shouldProcess(Throwable $e): bool
    {
        foreach (config('autofix.excluded_exceptions', []) as $excluded) {
            if ($e instanceof $excluded) {
                return false;
            }
        }

        $file = $this->relativePath($e->getFile());
        if (AutofixProposal::recentlyAnalyzed(get_class($e), $file, $e->getLine())) {
            return false;
        }

        return true;
    }

    /**
     * Gather source code context from the stack trace.
     */
    protected function gatherCodeContext(Throwable $e): string
    {
        $context = [];
        $maxFiles = config('autofix.max_context_files', 5);
        $maxSize = config('autofix.max_file_size', 50000);
        $seen = [];

        $primaryFile = $e->getFile();
        if (file_exists($primaryFile) && $this->isProjectFile($primaryFile)) {
            $relPath = $this->relativePath($primaryFile);
            $seen[$relPath] = true;
            $content = $this->readFileWithContext($primaryFile, $e->getLine(), 20);
            $context[] = "=== {$relPath} (error at line {$e->getLine()}) ===\n{$content}";
        }

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
            $content = $this->readFileWithContext($file, $line, 10);
            $context[] = "=== {$relPath} (line {$line}) ===\n{$content}";
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
            . "ANALYSIS: [1-2 sentences explaining the cause]\n\n"
            . "FILE: [relative path to file, e.g. app/Services/MyService.php]\n"
            . "OLD:\n```php\n[exact code to find and replace - copy EXACTLY from the source]\n```\n"
            . "NEW:\n```php\n[replacement code]\n```\n\n"
            . "RISK: [low/medium/high]\n\n"
            . "CRITICAL RULES:\n"
            . "- The OLD block must match the EXACT code in the file (including whitespace)\n"
            . "- Only make MINIMAL changes to fix the specific error\n"
            . "- Never change .env, config files, or database schema\n"
            . "- Never add new dependencies\n"
            . "- Prefer defensive fixes (null checks, try/catch) over structural changes";

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

        if (!file_exists($fullPath)) {
            return "Target file not found: {$targetFile}";
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

        // Backup original file
        $backupPath = $fullPath . '.autofix-backup.' . date('YmdHis');
        if (!copy($fullPath, $backupPath)) {
            return "Could not create backup of {$targetFile}.";
        }

        // Apply the replacement
        $newContent = str_replace($oldCode, $newCode, $fileContent);
        file_put_contents($fullPath, $newContent);

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
