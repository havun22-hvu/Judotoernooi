<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class LocalSyncController extends Controller
{
    /**
     * Show setup page for role configuration
     */
    public function setup(): View
    {
        $config = config('local-server');

        return view('local.setup', [
            'currentRole' => $config['role'],
            'currentIp' => $config['ip'],
            'currentDeviceName' => $config['device_name'],
            'configuredAt' => $config['configured_at'],
        ]);
    }

    /**
     * Save role configuration
     */
    public function saveSetup(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required|in:primary,standby',
            'device_name' => 'nullable|string|max:100',
        ]);

        $role = $validated['role'];
        $ip = $role === 'primary'
            ? config('local-server.primary_ip')
            : config('local-server.standby_ip');

        // Update .env file
        $this->updateEnvFile([
            'LOCAL_SERVER_ROLE' => $role,
            'LOCAL_SERVER_IP' => $ip,
            'LOCAL_SERVER_DEVICE_NAME' => $validated['device_name'] ?? '',
            'LOCAL_SERVER_CONFIGURED_AT' => now()->toDateTimeString(),
        ]);

        // Clear config cache
        \Artisan::call('config:clear');

        return redirect()->route('local.dashboard')
            ->with('success', 'Server geconfigureerd als ' . strtoupper($role));
    }

    /**
     * Standby sync UI - shows real-time sync status
     */
    public function standbySyncUI(): View
    {
        $role = config('local-server.role');

        if ($role !== 'standby') {
            return redirect()->route('local.dashboard')
                ->with('error', 'Deze pagina is alleen voor standby servers');
        }

        return view('local.standby-sync');
    }

    /**
     * Pre-flight check wizard
     */
    public function preflight(): View
    {
        return view('local.preflight');
    }

    /**
     * Health dashboard - shows system status
     */
    public function healthDashboard(): View
    {
        $toernooien = Toernooi::where('datum', today())->get();

        // Check cloud connectivity
        $cloudOnline = false;
        try {
            $response = @file_get_contents('https://judotournament.org', false, stream_context_create([
                'http' => ['timeout' => 3]
            ]));
            $cloudOnline = $response !== false;
        } catch (\Exception $e) {
            $cloudOnline = false;
        }

        // Check standby
        $standbyOnline = Cache::has('standby_last_heartbeat') &&
            now()->diffInSeconds(Cache::get('standby_last_heartbeat')) < 30;

        // Simulated devices (in real implementation, these would be tracked)
        $devices = [
            ['name' => 'Mat 1', 'type' => 'Tablet', 'online' => true],
            ['name' => 'Mat 2', 'type' => 'Tablet', 'online' => true],
            ['name' => 'Mat 3', 'type' => 'Tablet', 'online' => true],
            ['name' => 'Mat 4', 'type' => 'Tablet', 'online' => true],
            ['name' => 'Weging', 'type' => 'Laptop', 'online' => true],
            ['name' => 'Display 1', 'type' => 'Scherm', 'online' => true],
        ];

        return view('local.health-dashboard', compact('toernooien', 'cloudOnline', 'standbyOnline', 'devices'));
    }

    /**
     * Dashboard showing current status
     */
    public function dashboard(): View
    {
        $config = config('local-server');

        // Get today's tournaments
        $toernooien = Toernooi::where('datum', today())->get();

        // Get standby status if we're primary
        $standbyStatus = null;
        if ($config['role'] === 'primary') {
            $standbyStatus = Cache::get('standby_last_heartbeat');
        }

        // Get primary status if we're standby
        $primaryStatus = null;
        if ($config['role'] === 'standby') {
            $primaryStatus = Cache::get('primary_last_heartbeat');
        }

        return view('local.dashboard', [
            'config' => $config,
            'toernooien' => $toernooien,
            'standbyStatus' => $standbyStatus,
            'primaryStatus' => $primaryStatus,
            'lastSync' => Cache::get('standby_last_sync'),
        ]);
    }

    /**
     * Update .env file with new values
     */
    private function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            $value = str_contains($value, ' ') ? "\"$value\"" : $value;

            if (preg_match("/^{$key}=/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        File::put($envPath, $envContent);
    }

    /**
     * Get server status and role
     */
    public function status(): JsonResponse
    {
        $config = config('local-server');

        return response()->json([
            'role' => $config['role'],
            'ip' => $config['ip'],
            'device_name' => $config['device_name'],
            'configured_at' => $config['configured_at'],
            'timestamp' => now()->toIso8601String(),
            'uptime' => $this->getUptime(),
        ]);
    }

    /**
     * Heartbeat endpoint - standby polls this from primary
     */
    public function heartbeat(): JsonResponse
    {
        // Update last heartbeat timestamp
        Cache::put('local_server_heartbeat', now()->toIso8601String(), 60);

        return response()->json([
            'status' => 'ok',
            'role' => config('local-server.role'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get all sync data for standby replication
     * Returns all active tournament data
     */
    public function syncData(): JsonResponse
    {
        // Get all tournaments that are on wedstrijddag (today)
        $toernooien = Toernooi::where('datum', today())
            ->with(['blokken', 'matten'])
            ->get();

        $data = [];
        foreach ($toernooien as $toernooi) {
            $data[] = $this->getToernooiSyncData($toernooi);
        }

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'toernooien' => $data,
        ]);
    }

    /**
     * Get sync data for a specific tournament
     */
    public function syncToernooi(Toernooi $toernooi): JsonResponse
    {
        return response()->json($this->getToernooiSyncData($toernooi));
    }

    /**
     * Receive sync data from primary (standby endpoint)
     */
    public function receiveSync(Request $request): JsonResponse
    {
        $role = config('local-server.role');

        if ($role !== 'standby') {
            return response()->json([
                'error' => 'This server is not in standby mode',
            ], 400);
        }

        // Store received data in cache for quick access
        $data = $request->all();
        Cache::put('standby_sync_data', $data, 120);
        Cache::put('standby_last_sync', now()->toIso8601String(), 120);

        return response()->json([
            'status' => 'ok',
            'received_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get standby status (for primary to check)
     */
    public function standbyStatus(): JsonResponse
    {
        $lastSync = Cache::get('standby_last_sync');
        $lastHeartbeat = Cache::get('standby_last_heartbeat');

        return response()->json([
            'role' => config('local-server.role'),
            'last_sync' => $lastSync,
            'last_heartbeat' => $lastHeartbeat,
            'is_synced' => $lastSync && now()->diffInSeconds($lastSync) < 30,
        ]);
    }

    /**
     * Health check for monitoring
     */
    public function health(): JsonResponse
    {
        $config = config('local-server');
        $issues = [];

        // Check if configured
        if (!$config['role']) {
            $issues[] = 'Server role not configured';
        }

        // Check database connection
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $issues[] = 'Database connection failed';
        }

        return response()->json([
            'status' => empty($issues) ? 'healthy' : 'unhealthy',
            'role' => $config['role'],
            'issues' => $issues,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get full tournament sync data
     */
    private function getToernooiSyncData(Toernooi $toernooi): array
    {
        $poules = $toernooi->poules()
            ->whereNotNull('mat_id')
            ->with(['judokas.club', 'wedstrijden', 'mat', 'blok'])
            ->get();

        return [
            'toernooi_id' => $toernooi->id,
            'toernooi_naam' => $toernooi->naam,
            'toernooi_datum' => $toernooi->datum->format('Y-m-d'),
            'timestamp' => now()->toIso8601String(),
            'poules' => $poules->map(function ($poule) {
                return [
                    'id' => $poule->id,
                    'nummer' => $poule->nummer,
                    'titel' => $poule->getDisplayTitel(),
                    'mat_id' => $poule->mat_id,
                    'mat_nummer' => $poule->mat?->nummer,
                    'blok_id' => $poule->blok_id,
                    'blok_nummer' => $poule->blok?->nummer,
                    'actieve_wedstrijd_id' => $poule->actieve_wedstrijd_id,
                    'judokas' => $poule->judokas->map(fn($j) => [
                        'id' => $j->id,
                        'naam' => $j->naam,
                        'club_id' => $j->club_id,
                        'club' => $j->club?->naam,
                        'gewicht' => $j->gewicht,
                        'aanwezigheid' => $j->aanwezigheid,
                    ])->values(),
                    'wedstrijden' => $poule->wedstrijden->map(fn($w) => [
                        'id' => $w->id,
                        'volgorde' => $w->volgorde,
                        'judoka_wit_id' => $w->judoka_wit_id,
                        'judoka_blauw_id' => $w->judoka_blauw_id,
                        'is_gespeeld' => $w->is_gespeeld,
                        'winnaar_id' => $w->winnaar_id,
                        'score_wit' => $w->score_wit,
                        'score_blauw' => $w->score_blauw,
                    ])->values(),
                ];
            })->values(),
        ];
    }

    /**
     * Get server uptime
     */
    private function getUptime(): ?string
    {
        $startTime = Cache::get('server_start_time');
        if (!$startTime) {
            return null;
        }

        $diff = now()->diff($startTime);
        return $diff->format('%H:%I:%S');
    }
}
