<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'toernooi_id',
        'van_type',
        'van_id',
        'naar_type',
        'naar_id',
        'bericht',
        'gelezen_op',
    ];

    protected $casts = [
        'gelezen_op' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    /**
     * Get human-readable sender name
     */
    public function getAfzenderNaamAttribute(): string
    {
        return match($this->van_type) {
            'hoofdjury' => 'Hoofdjury',
            'mat' => "Mat {$this->van_id}",
            'weging' => 'Weging',
            'spreker' => 'Spreker',
            'dojo' => 'Dojo',
            default => 'Onbekend',
        };
    }

    /**
     * Get human-readable recipient name
     */
    public function getOntvangerNaamAttribute(): string
    {
        return match($this->naar_type) {
            'hoofdjury' => 'Hoofdjury',
            'mat' => "Mat {$this->naar_id}",
            'weging' => 'Weging',
            'spreker' => 'Spreker',
            'dojo' => 'Dojo',
            'alle_matten' => 'Alle matten',
            'iedereen' => 'Iedereen',
            default => 'Onbekend',
        };
    }

    /**
     * Check if message is for a specific recipient
     */
    public function isVoor(string $type, ?int $id = null): bool
    {
        // Broadcast messages
        if ($this->naar_type === 'iedereen') {
            return true;
        }

        // Alle matten
        if ($this->naar_type === 'alle_matten' && $type === 'mat') {
            return true;
        }

        // Direct message
        if ($this->naar_type === $type) {
            if ($id === null || $this->naar_id === null) {
                return true;
            }
            return $this->naar_id === $id;
        }

        return false;
    }

    /**
     * Mark as read
     */
    public function markeerGelezen(): void
    {
        if (!$this->gelezen_op) {
            $this->update(['gelezen_op' => now()]);
        }
    }

    /**
     * Scope: messages for a specific recipient
     */
    public function scopeVoor($query, string $type, ?int $id = null)
    {
        return $query->where(function ($q) use ($type, $id) {
            // Direct messages
            $q->where(function ($sub) use ($type, $id) {
                $sub->where('naar_type', $type);
                if ($id !== null) {
                    $sub->where('naar_id', $id);
                }
            });

            // Broadcast to all
            $q->orWhere('naar_type', 'iedereen');

            // Broadcast to all mats
            if ($type === 'mat') {
                $q->orWhere('naar_type', 'alle_matten');
            }
        });
    }

    /**
     * Scope: unread messages
     */
    public function scopeOngelezen($query)
    {
        return $query->whereNull('gelezen_op');
    }
}
