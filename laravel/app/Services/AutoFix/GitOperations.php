<?php

namespace App\Services\AutoFix;

use App\Models\AutofixProposal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * GitOperations - Handles all git/GitHub interactions for AutoFix.
 *
 * Extracted from AutoFixService to isolate:
 *  - building commit messages from proposals
 *  - direct push vs branch + PR workflow
 *  - creating GitHub pull requests via REST API
 *  - sandbox guard for test/local environments
 */
class GitOperations
{
    /**
     * Commit fix to a hotfix branch and create a PR, or push directly.
     */
    public function commitAndPush(AutofixProposal $proposal): void
    {
        if ($this->shouldSkipGit('gitCommitAndPush', ['proposal_id' => $proposal->id, 'file' => $proposal->file])) {
            return;
        }

        try {
            $file = $proposal->file;
            $basePath = base_path();

            $message = $this->buildCommitMessage($proposal);

            if (config('autofix.branch_model', false)) {
                $this->branchAndPR($basePath, $file, $message, $proposal);
            } else {
                $this->directPush($basePath, $file, $message);
            }
        } catch (Throwable $gitError) {
            Log::warning('AutoFix: git operations failed', ['error' => $gitError->getMessage()]);
        }
    }

    /**
     * Build a structured git commit message from a proposal.
     */
    protected function buildCommitMessage(AutofixProposal $proposal): string
    {
        $analysis = '';
        if (preg_match('/ANALYSIS:\s*(.+?)(?:\n|$)/s', $proposal->claude_analysis, $match)) {
            $analysis = trim($match[1]);
        }

        $risk = self::extractRisk($proposal->claude_analysis);

        $shortFile = str_replace('.blade', '', basename($proposal->file, '.php'));
        $prefix = "autofix({$shortFile}): ";
        $maxAnalysis = max(20, 72 - strlen($prefix));
        $title = $prefix . ($analysis ? Str::limit($analysis, $maxAnalysis, '...') : class_basename($proposal->exception_class));

        $body = "File: {$proposal->file}\n"
            . "Exception: {$proposal->exception_class}\n"
            . ($risk !== 'unknown' ? "Risk: {$risk}\n" : '')
            . "Proposal: #{$proposal->id}";

        return $title . "\n\n" . $body;
    }

    /**
     * Create a hotfix branch, commit, push, and create a PR via GitHub CLI.
     */
    protected function branchAndPR(string $basePath, string $file, string $message, AutofixProposal $proposal): void
    {
        if ($this->shouldSkipGit('gitBranchAndPR', ['file' => $file])) {
            return;
        }

        $branchPrefix = config('autofix.branch_prefix', 'hotfix/autofix-');
        $branch = $branchPrefix . date('Ymd-His');

        // Detect the main branch name
        exec(sprintf('cd %s && git symbolic-ref refs/remotes/origin/HEAD 2>/dev/null | sed "s@^refs/remotes/origin/@@"', escapeshellarg($basePath)), $mainOutput);
        $mainBranch = trim($mainOutput[0] ?? 'main');

        // Create branch, commit, push
        $commands = sprintf(
            'cd %s && git checkout -b %s && git add %s && git commit -m %s && git push -u origin %s 2>&1',
            escapeshellarg($basePath),
            escapeshellarg($branch),
            escapeshellarg($file),
            escapeshellarg($message),
            escapeshellarg($branch)
        );

        exec($commands, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning('AutoFix: branch+push failed, falling back to direct push', ['output' => implode("\n", $output)]);
            // Fallback: go back to main branch and push directly
            exec(sprintf('cd %s && git checkout %s 2>&1', escapeshellarg($basePath), escapeshellarg($mainBranch)));
            $this->directPush($basePath, $file, $message);
            return;
        }

        Log::info('AutoFix: Fix committed to branch', ['branch' => $branch, 'file' => $file]);

        // Create PR via GitHub REST API
        if (config('autofix.auto_pr', false)) {
            $this->createGitHubPR($basePath, $mainBranch, $branch, $file, $proposal);
        }

        // Switch back to main branch so the server keeps serving from main
        exec(sprintf('cd %s && git checkout %s 2>&1', escapeshellarg($basePath), escapeshellarg($mainBranch)));
    }

    /**
     * Push directly to the current branch (legacy behavior).
     */
    protected function directPush(string $basePath, string $file, string $message): void
    {
        if ($this->shouldSkipGit('gitDirectPush', ['file' => $file])) {
            return;
        }

        $commands = sprintf(
            'cd %s && git add %s && git commit -m %s && git push 2>&1',
            escapeshellarg($basePath),
            escapeshellarg($file),
            escapeshellarg($message)
        );

        exec($commands, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning('AutoFix: git push failed', ['output' => implode("\n", $output)]);
        } else {
            Log::info('AutoFix: Changes committed and pushed', ['file' => $file]);
        }
    }

    /**
     * Create a Pull Request via the GitHub REST API.
     */
    protected function createGitHubPR(string $basePath, string $mainBranch, string $branch, string $file, AutofixProposal $proposal): void
    {
        $token = config('autofix.github_token');
        if (!$token) {
            Log::warning('AutoFix: No GITHUB_TOKEN configured, skipping PR creation');
            return;
        }

        // Detect owner/repo from git remote
        exec(sprintf('cd %s && git remote get-url origin 2>/dev/null', escapeshellarg($basePath)), $remoteOutput);
        $remoteUrl = trim($remoteOutput[0] ?? '');

        if (!preg_match('#[:/]([^/]+)/([^/.]+?)(?:\.git)?$#', $remoteUrl, $repoMatch)) {
            Log::warning('AutoFix: Could not parse GitHub owner/repo from remote', ['remote' => $remoteUrl]);
            return;
        }

        $owner = $repoMatch[1];
        $repo = $repoMatch[2];

        $prTitle = sprintf('[AutoFix] %s', Str::limit($file . ': ' . $proposal->exception_class, 60));
        $prBody = sprintf(
            "## AutoFix Proposal #%d\n\n"
            . "**Exception:** `%s`\n"
            . "**File:** `%s`\n"
            . "**Risk:** %s\n\n"
            . "---\n\n"
            . "Review URL: %s/autofix/%s\n\n"
            . "This PR was automatically created by AutoFix. Review the changes and merge if correct.",
            $proposal->id,
            $proposal->exception_class,
            $file,
            self::extractRisk($proposal->claude_analysis),
            config('app.url'),
            $proposal->approval_token
        );

        try {
            $response = Http::withToken($token)
                ->post("https://api.github.com/repos/{$owner}/{$repo}/pulls", [
                    'title' => $prTitle,
                    'body' => $prBody,
                    'head' => $branch,
                    'base' => $mainBranch,
                ]);

            if ($response->successful()) {
                $prUrl = $response->json('html_url');
                Log::info('AutoFix: PR created via GitHub API', ['url' => $prUrl, 'branch' => $branch]);
            } else {
                Log::warning('AutoFix: GitHub API PR creation failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 300),
                ]);
            }
        } catch (Throwable $apiError) {
            Log::warning('AutoFix: GitHub API call failed', ['error' => $apiError->getMessage()]);
        }
    }

    /**
     * Extract RISK level from Claude's analysis. Pure utility, shared with AutoFixService.
     */
    public static function extractRisk(string $analysis): string
    {
        if (preg_match('/RISK:\s*(low|medium|high)/i', $analysis, $match)) {
            return strtolower($match[1]);
        }
        return 'unknown';
    }

    /**
     * Sandbox guard: in testing/local we never touch real git unless the
     * caller explicitly opts in via autofix.force_git_in_tests.
     */
    protected function shouldSkipGit(string $caller, array $context = []): bool
    {
        if (!app()->environment(['testing', 'local'])) {
            return false;
        }
        if (config('autofix.force_git_in_tests', false)) {
            return false;
        }
        Log::info("AutoFix: {$caller} skipped (test/local env)", $context);
        return true;
    }
}
