<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutofixProposal extends Model
{
    protected $fillable = [
        'exception_class',
        'exception_message',
        'file',
        'line',
        'stack_trace',
        'code_context',
        'claude_analysis',
        'proposed_diff',
        'approval_token',
        'status',
        'url',
        'organisator_id',
        'organisator_naam',
        'toernooi_id',
        'toernooi_naam',
        'http_method',
        'route_name',
        'email_sent_at',
        'approved_at',
        'applied_at',
        'apply_error',
    ];

    protected $casts = [
        'email_sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if a similar error was recently analyzed.
     */
    public static function recentlyAnalyzed(string $class, string $file, int $line): bool
    {
        $minutes = config('autofix.rate_limit_minutes', 60);

        return static::where('exception_class', $class)
            ->where('file', $file)
            ->where('line', $line)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }
}
