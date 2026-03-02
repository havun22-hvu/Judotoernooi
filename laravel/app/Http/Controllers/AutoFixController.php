<?php

namespace App\Http\Controllers;

use App\Models\AutofixProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoFixController extends Controller
{
    /**
     * Show the fix proposal for review.
     */
    public function show(string $token)
    {
        $proposal = AutofixProposal::where('approval_token', $token)->firstOrFail();

        return view('autofix.show', compact('proposal'));
    }

    /**
     * Approve and apply the fix.
     */
    public function approve(Request $request, string $token)
    {
        $proposal = AutofixProposal::where('approval_token', $token)->firstOrFail();

        if (!$proposal->isPending()) {
            return back()->with('error', "Dit voorstel is al verwerkt (status: {$proposal->status}).");
        }

        $proposal->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Try to apply the fix
        try {
            $this->applyFix($proposal);

            $proposal->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);

            return back()->with('success', 'Fix is goedgekeurd en toegepast! Server cache is geleegd.');

        } catch (\Throwable $e) {
            $proposal->update([
                'status' => 'failed',
                'apply_error' => $e->getMessage(),
            ]);

            Log::error('AutoFix: Apply failed', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Fix goedgekeurd maar toepassen is mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Reject the fix proposal.
     */
    public function reject(string $token)
    {
        $proposal = AutofixProposal::where('approval_token', $token)->firstOrFail();

        if (!$proposal->isPending()) {
            return redirect()->route('autofix.show', $token)
                ->with('error', "Dit voorstel is al verwerkt (status: {$proposal->status}).");
        }

        $proposal->update(['status' => 'rejected']);

        return redirect()->route('autofix.show', $token)
            ->with('info', 'Voorstel afgewezen.');
    }

    /**
     * Apply the fix by parsing the Claude response and modifying the file.
     */
    protected function applyFix(AutofixProposal $proposal): void
    {
        $analysis = $proposal->claude_analysis;

        // Parse FILE: path from Claude's response
        if (!preg_match('/FILE:\s*(.+)/i', $analysis, $fileMatch)) {
            throw new \RuntimeException('Could not parse target file from Claude response.');
        }

        $targetFile = trim($fileMatch[1]);
        $fullPath = base_path($targetFile);

        // Only allow changes to project files (not vendor, node_modules, etc.)
        if (!$this->isProjectFile($fullPath)) {
            throw new \RuntimeException("Target file is not a project file: {$targetFile}");
        }

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Target file not found: {$targetFile}");
        }

        // Check if file is protected
        if (in_array($targetFile, config('autofix.protected_files', []))) {
            throw new \RuntimeException("Target file is protected: {$targetFile}");
        }

        // Parse OLD and NEW code blocks
        if (!preg_match('/OLD:\s*```(?:php)?\s*\n(.*?)```/s', $analysis, $oldMatch)) {
            throw new \RuntimeException('Could not parse OLD code block from Claude response.');
        }

        if (!preg_match('/NEW:\s*```(?:php)?\s*\n(.*?)```/s', $analysis, $newMatch)) {
            throw new \RuntimeException('Could not parse NEW code block from Claude response.');
        }

        $oldCode = rtrim($oldMatch[1]);
        $newCode = rtrim($newMatch[1]);

        $fileContent = file_get_contents($fullPath);

        if (strpos($fileContent, $oldCode) === false) {
            throw new \RuntimeException("OLD code block not found in {$targetFile}. The code may have already been changed.");
        }

        // Apply the replacement
        $newContent = str_replace($oldCode, $newCode, $fileContent);

        // Backup original file to storage/ (www-data has write access there)
        $backupDir = storage_path('app/autofix-backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupName = str_replace(['/', '\\'], '_', $targetFile) . '.' . date('YmdHis');
        copy($fullPath, $backupDir . '/' . $backupName);

        // Write the fix
        file_put_contents($fullPath, $newContent);

        // Clear Laravel caches
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fullPath, true);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            // Non-critical
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

        $relative = str_replace([$basePath . '/', $basePath . '\\'], '', $path);
        return !str_starts_with($relative, 'vendor/')
            && !str_starts_with($relative, 'node_modules/')
            && !str_starts_with($relative, 'storage/');
    }
}
