<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Toernooi;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Log an activity for a tournament.
     *
     * @param Toernooi $toernooi
     * @param string $actie Action key (e.g. 'verplaats_judoka')
     * @param string $beschrijving Human-readable description
     * @param array $options Optional: model, properties, interface
     */
    public static function log(Toernooi $toernooi, string $actie, string $beschrijving, array $options = []): ?ActivityLog
    {
        try {
            $actor = static::detectActor();

            $data = [
                'toernooi_id' => $toernooi->id,
                'actie' => $actie,
                'beschrijving' => mb_substr($beschrijving, 0, 255),
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'actor_naam' => mb_substr($actor['naam'], 0, 100),
                'ip_adres' => request()->ip(),
                'interface' => $options['interface'] ?? $actor['interface'] ?? null,
            ];

            // Model info
            if (isset($options['model']) && $options['model'] instanceof Model) {
                $model = $options['model'];
                $data['model_type'] = class_basename($model);
                $data['model_id'] = $model->getKey();
            } elseif (isset($options['model_type'])) {
                $data['model_type'] = $options['model_type'];
                $data['model_id'] = $options['model_id'] ?? null;
            }

            // Extra properties (old/new values, meta)
            if (isset($options['properties'])) {
                $data['properties'] = $options['properties'];
            }

            return ActivityLog::create($data);
        } catch (\Throwable $e) {
            // Never let logging break the actual operation
            \Log::warning('ActivityLogger failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Detect the current actor based on auth context.
     */
    private static function detectActor(): array
    {
        // 1. Authenticated organisator
        $organisator = auth('organisator')->user();
        if ($organisator) {
            $naam = $organisator->naam;
            if ($organisator->isSitebeheerder()) {
                $naam .= ' (admin)';
            }
            return [
                'type' => 'organisator',
                'id' => $organisator->id,
                'naam' => $naam,
                'interface' => 'dashboard',
            ];
        }

        // 2. Device-bound access (mat, weging, spreker via CheckDeviceBinding)
        $deviceToegang = request()->get('device_toegang');
        if ($deviceToegang) {
            return [
                'type' => 'device',
                'id' => $deviceToegang->id,
                'naam' => ucfirst($deviceToegang->rol) . ' (' . ($deviceToegang->naam ?? 'device') . ')',
                'interface' => $deviceToegang->rol ?? 'device',
            ];
        }

        // 3. Session-based role access (vrijwilligers via role code)
        $rolType = session('rol_type');
        if ($rolType) {
            return [
                'type' => 'rol_sessie',
                'id' => null,
                'naam' => ucfirst($rolType) . ' (sessie)',
                'interface' => $rolType,
            ];
        }

        // 4. Fallback
        return [
            'type' => 'systeem',
            'id' => null,
            'naam' => 'Systeem',
            'interface' => null,
        ];
    }
}
