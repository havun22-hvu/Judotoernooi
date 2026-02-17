<?php

namespace App\Services;

use App\Mail\AutoFixProposalMail;
use App\Models\AutofixProposal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AutoFixService
{
    /**
     * Handle an exception: analyze with Claude and create a fix proposal.
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

            // Ask Claude for analysis and fix
            $analysis = $this->askClaude($e, $codeContext);

            if (!$analysis) {
                return;
            }

            // Create proposal in database
            $proposal = $this->createProposal($e, $file, $line, $codeContext, $analysis);

            // Send email notification
            $this->sendNotification($proposal);

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
        // Check excluded exceptions
        foreach (config('autofix.excluded_exceptions', []) as $excluded) {
            if ($e instanceof $excluded) {
                return false;
            }
        }

        // Check rate limit: same error recently analyzed?
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

        // Primary file where the error occurred
        $primaryFile = $e->getFile();
        if (file_exists($primaryFile) && $this->isProjectFile($primaryFile)) {
            $relPath = $this->relativePath($primaryFile);
            $seen[$relPath] = true;
            $content = $this->readFileWithContext($primaryFile, $e->getLine(), 20);
            $context[] = "=== {$relPath} (error at line {$e->getLine()}) ===\n{$content}";
        }

        // Stack trace files
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

        // Truncate if too large
        if (strlen($result) > $maxSize) {
            $result = substr($result, 0, $maxSize) . "\n\n[... truncated ...]";
        }

        return $result;
    }

    /**
     * Call HavunCore AI Proxy to analyze the error.
     */
    protected function askClaude(Throwable $e, string $codeContext): ?array
    {
        $url = rtrim(config('autofix.havuncore_url'), '/') . '/api/ai/chat';

        $message = "PRODUCTION ERROR ANALYSIS REQUEST\n\n"
            . "Exception: " . get_class($e) . "\n"
            . "Message: " . $e->getMessage() . "\n"
            . "File: " . $this->relativePath($e->getFile()) . ":" . $e->getLine() . "\n"
            . "URL: " . (request()?->fullUrl() ?? 'N/A') . "\n"
            . "Method: " . (request()?->method() ?? 'N/A') . "\n\n"
            . "RELEVANT SOURCE CODE:\n\n"
            . $codeContext;

        $systemPrompt = "You are a Laravel debugging expert analyzing a production error in JudoToernooi (a judo tournament management app). "
            . "Analyze the error and source code, then provide:\n\n"
            . "1. **ANALYSIS**: What caused this error (2-3 sentences)\n"
            . "2. **FIX**: The exact code change needed. Show the file path, the old code to replace, and the new code. "
            . "Use this format:\n"
            . "FILE: path/to/file.php\n"
            . "OLD:\n```php\n// old code\n```\n"
            . "NEW:\n```php\n// new code\n```\n\n"
            . "3. **RISK**: low/medium/high - how risky is this fix?\n\n"
            . "Be concise and precise. Only propose minimal changes that fix the specific error.";

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
                    'body' => $response->body(),
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
    protected function createProposal(Throwable $e, string $file, int $line, string $codeContext, array $analysis): AutofixProposal
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
            'proposed_diff' => $analysis['analysis'], // Full response contains the diff
            'approval_token' => Str::random(64),
            'status' => 'pending',
            'url' => request()?->fullUrl(),
        ]);
    }

    /**
     * Send email notification with the fix proposal.
     */
    protected function sendNotification(AutofixProposal $proposal): void
    {
        $email = config('autofix.email');
        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send(new AutoFixProposalMail($proposal));

            $proposal->update(['email_sent_at' => now()]);

        } catch (Throwable $mailError) {
            Log::warning('AutoFix: Email notification failed', [
                'error' => $mailError->getMessage(),
                'proposal_id' => $proposal->id,
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

        // Exclude vendor and framework files
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
