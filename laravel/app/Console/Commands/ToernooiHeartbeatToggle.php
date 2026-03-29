<?php

namespace App\Console\Commands;

use App\Models\Toernooi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ToernooiHeartbeatToggle extends Command
{
    protected $signature = 'toernooi:heartbeat-toggle {toernooi_id} {--off : Deactivate instead of activate}';
    protected $description = 'Activate or deactivate heartbeat for a tournament';

    public function handle(): int
    {
        $toernooiId = $this->argument('toernooi_id');

        $toernooi = Toernooi::find($toernooiId);
        if (!$toernooi) {
            $this->error("Toernooi #{$toernooiId} niet gevonden.");
            return self::FAILURE;
        }

        $cacheKey = "toernooi:{$toernooiId}:heartbeat_active";

        if ($this->option('off')) {
            Cache::forget($cacheKey);
            $this->info("Heartbeat gestopt voor '{$toernooi->naam}'");
        } else {
            Cache::put($cacheKey, true, now()->addMinutes(15));
            $this->info("Heartbeat geactiveerd voor '{$toernooi->naam}' (15 min TTL, verlengd bij activiteit)");
        }

        return self::SUCCESS;
    }
}
