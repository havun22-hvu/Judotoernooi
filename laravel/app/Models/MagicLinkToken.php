<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MagicLinkToken extends Model
{
    protected $fillable = ['email', 'token', 'type', 'metadata', 'used_at', 'expires_at'];

    protected $casts = [
        'metadata' => 'array',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Create a new magic link token.
     */
    public static function generate(string $email, string $type = 'register', array $metadata = []): self
    {
        // Clean up old unused tokens for this email + type
        static::where('email', strtolower(trim($email)))
            ->where('type', $type)
            ->whereNull('used_at')
            ->delete();

        return static::create([
            'email' => strtolower(trim($email)),
            'token' => Str::random(64),
            'type' => $type,
            'metadata' => $metadata,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * Find a valid (unused, not expired) token.
     */
    public static function findValid(string $token, string $type): ?self
    {
        return static::where('token', $token)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Mark token as used (single-use).
     */
    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
