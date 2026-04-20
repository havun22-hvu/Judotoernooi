<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvKoppeling extends Model
{
    protected $table = 'tv_koppelingen';

    protected $fillable = ['code', 'toernooi_id', 'mat_nummer', 'expires_at', 'linked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'linked_at' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public static function generateCode(): string
    {
        $attempts = 0;

        do {
            $code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            if (++$attempts > 50) {
                // Clean up expired codes to free up space, then retry once
                self::where('expires_at', '<', now())->delete();

                if ($attempts > 100) {
                    throw new \RuntimeException('Kan geen unieke TV-code genereren');
                }
            }
        } while (self::where('code', $code)->where('expires_at', '>', now())->exists());

        return $code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLinked(): bool
    {
        return $this->linked_at !== null;
    }

    public static function cleanupExpired(): void
    {
        self::where('expires_at', '<', now())->delete();
    }

    /**
     * Couple this TV to a mat and return the redirect URL used by the TV display.
     * Short URL (/tv/XXXX) if a mat-role DeviceToegang exists, otherwise the full
     * scoreboard-live route is returned as fallback.
     */
    public function linkToMat(int $toernooiId, int $matNummer): string
    {
        $this->update([
            'toernooi_id' => $toernooiId,
            'mat_nummer' => $matNummer,
            'linked_at' => now(),
        ]);

        $matToegang = DeviceToegang::where('toernooi_id', $toernooiId)
            ->where('rol', 'mat')
            ->where('mat_nummer', $matNummer)
            ->first();

        if ($matToegang) {
            return url('/tv/' . substr($matToegang->code, 0, 4));
        }

        $toernooi = Toernooi::with('organisator')->findOrFail($toernooiId);

        return route('mat.scoreboard-live', [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'mat' => $matNummer,
        ]);
    }
}
