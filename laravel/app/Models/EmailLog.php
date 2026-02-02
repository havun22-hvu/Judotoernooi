<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'toernooi_id',
        'club_id',
        'type',
        'recipients',
        'subject',
        'summary',
        'status',
        'error_message',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Log a sent email
     */
    public static function logSent(
        int $toernooiId,
        string $type,
        array|string $recipients,
        string $subject,
        ?string $summary = null,
        ?int $clubId = null
    ): self {
        return self::create([
            'toernooi_id' => $toernooiId,
            'club_id' => $clubId,
            'type' => $type,
            'recipients' => is_array($recipients) ? implode(', ', $recipients) : $recipients,
            'subject' => $subject,
            'summary' => $summary,
            'status' => 'sent',
        ]);
    }

    /**
     * Log a failed email
     */
    public static function logFailed(
        int $toernooiId,
        string $type,
        array|string $recipients,
        string $subject,
        string $errorMessage,
        ?int $clubId = null
    ): self {
        return self::create([
            'toernooi_id' => $toernooiId,
            'club_id' => $clubId,
            'type' => $type,
            'recipients' => is_array($recipients) ? implode(', ', $recipients) : $recipients,
            'subject' => $subject,
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get readable type name
     */
    public function getTypeNaamAttribute(): string
    {
        return match ($this->type) {
            'uitnodiging' => 'Uitnodiging',
            'correctie' => 'Correctie verzoek',
            'herinnering' => 'Herinnering',
            default => ucfirst($this->type),
        };
    }

    /**
     * Check if email was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'sent';
    }
}
