<?php

namespace App\Observers;

use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the public tournament cache whenever data that is rendered
 * on the public page changes. Used for PubliekController's uitslagen cache.
 *
 * Keep the model -> toernooi_id resolution minimal to avoid extra queries
 * during save events. Non-destructive: only forgets cache keys.
 */
class PublicTournamentCacheObserver
{
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
        // Direct column on Poule
        if (isset($model->toernooi_id)) {
            return (int) $model->toernooi_id;
        }

        // Wedstrijd has no toernooi_id — walk via poule relation.
        // Use getAttribute to avoid touching unloaded relations when not needed.
        if ($model instanceof Wedstrijd) {
            $pouleId = $model->poule_id ?? null;
            if ($pouleId === null) {
                return null;
            }
            $poule = Poule::query()->select('toernooi_id')->find($pouleId);
            return $poule?->toernooi_id ? (int) $poule->toernooi_id : null;
        }

        return null;
    }
}
