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
     * Uses DeviceToegang naam if available, otherwise falls back to type + ID
     */
    public function getAfzenderNaamAttribute(): string
    {
        // Try to get name from DeviceToegang if we have an ID
        if ($this->van_id && $this->van_type !== 'hoofdjury') {
            $toegang = DeviceToegang::find($this->van_id);
            if ($toegang) {
                // Mat uses mat_nummer, others use toegang naam or type + ID
                if ($this->van_type === 'mat' && $toegang->mat_nummer) {
                    return "Mat {$toegang->mat_nummer}";
                }
                if ($toegang->naam) {
                    return $toegang->naam;
                }
            }
        }

        // Fallback to type-based naming
        return match($this->van_type) {
            'hoofdjury' => 'Hoofdjury',
            'mat' => $this->van_id ? "Mat {$this->van_id}" : 'Mat',
            'weging' => $this->van_id ? "Weging #{$this->van_id}" : 'Weging',
            'spreker' => $this->van_id ? "Spreker #{$this->van_id}" : 'Spreker',
            'dojo' => $this->van_id ? "Dojo #{$this->van_id}" : 'Dojo',
            default => 'Onbekend',
        };
    }

    /**
     * Get human-readable recipient name
     */
    public function getOntvangerNaamAttribute(): string
    {
        // Try to get name from DeviceToegang if we have an ID
        if ($this->naar_id && !in_array($this->naar_type, ['hoofdjury', 'alle_matten', 'iedereen'])) {
            $toegang = DeviceToegang::find($this->naar_id);
            if ($toegang) {
                if ($this->naar_type === 'mat' && $toegang->mat_nummer) {
                    return "Mat {$toegang->mat_nummer}";
                }
                if ($toegang->naam) {
                    return $toegang->naam;
                }
            }
        }

        return match($this->naar_type) {
            'hoofdjury' => 'Hoofdjury',
            'mat' => $this->naar_id ? "Mat {$this->naar_id}" : 'Alle matten',
            'weging' => $this->naar_id ? "Weging #{$this->naar_id}" : 'Weging',
            'spreker' => $this->naar_id ? "Spreker #{$this->naar_id}" : 'Spreker',
            'dojo' => $this->naar_id ? "Dojo #{$this->naar_id}" : 'Dojo',
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
