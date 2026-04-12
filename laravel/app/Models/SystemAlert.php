<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $fillable = [
        'type',
        'severity',
        'title',
        'message',
        'metadata',
        'source',
        'is_read',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Fire a new system alert.
     */
    public static function fire(string $type, string $severity, string $title, ?string $message = null, ?array $metadata = null, ?string $source = null): self
    {
        return static::create([
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata,
            'source' => $source,
        ]);
    }

    /**
     * Scope: unread alerts only.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope: by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the severity color for display.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'gray',
        };
    }
}
