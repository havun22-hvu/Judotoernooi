<?php

namespace App\Observers;

use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the public tournament cache when rendered data changes.
 * Used by PubliekController's uitslagen cache.
 */
class PublicTournamentCacheObserver
{
    /**
     * Per-request memo for poule_id -> toernooi_id lookups so we do not
     * hit the DB on every Wedstrijd score save during live scoring.
     *
     * @var array<int, int|null>
     */
    private static array $pouleToernooiMap = [];

    public function saved(Model $model): void
    {
        $this->forgetFor($model);
    }

    public function deleted(Model $model): void
    {
        $this->forgetFor($model);
    }

    private function forgetFor(Model $model): void
    {
        $toernooiId = $this->resolveToernooiId($model);
        if ($toernooiId === null) {
            return;
        }

        Cache::forget("public.toernooi.{$toernooiId}.uitslagen");
    }

    private function resolveToernooiId(Model $model): ?int
    {
        // Poule carries toernooi_id directly.
        $direct = $model->getAttribute('toernooi_id');
        if ($direct !== null) {
            return (int) $direct;
        }

        // Wedstrijd has no toernooi_id — walk via poule. Cached per-request
        // because poule -> toernooi mapping is immutable.
        if ($model instanceof Wedstrijd) {
            $pouleId = $model->getAttribute('poule_id');
            if ($pouleId === null) {
                return null;
            }

            if (!array_key_exists($pouleId, self::$pouleToernooiMap)) {
                self::$pouleToernooiMap[$pouleId] = Poule::query()
                    ->whereKey($pouleId)
                    ->value('toernooi_id');
            }

            $toernooiId = self::$pouleToernooiMap[$pouleId];
            return $toernooiId !== null ? (int) $toernooiId : null;
        }

        return null;
    }
}
