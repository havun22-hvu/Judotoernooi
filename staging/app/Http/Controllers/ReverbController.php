<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Process;

class ReverbController extends Controller
{
    private function processName(): string
    {
        return app()->environment('staging') ? 'reverb-staging' : 'reverb';
    }

    /**
     * Get Reverb status
     */
    public function status(): JsonResponse
    {
        if (app()->environment('local')) {
            return response()->json([
                'running' => false,
                'message' => 'Reverb alleen beschikbaar op server',
                'local' => true,
            ]);
        }

        $result = Process::run('supervisorctl status ' . $this->processName());
        $output = $result->output();

        $running = str_contains($output, 'RUNNING');

        return response()->json([
            'running' => $running,
            'status' => trim($output),
        ]);
    }

    /**
     * Start Reverb
     */
    public function start(): JsonResponse
    {
        if (app()->environment('local')) {
            return response()->json([
                'success' => false,
                'message' => 'Reverb alleen beschikbaar op server',
                'local' => true,
            ]);
        }

        try {
            $result = Process::run('supervisorctl start ' . $this->processName());

            return response()->json([
                'success' => $result->successful(),
                'message' => $result->successful() ? 'Reverb gestart' : 'Fout bij starten',
                'output' => trim($result->output() . $result->errorOutput()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Stop Reverb
     */
    public function stop(): JsonResponse
    {
        if (app()->environment('local')) {
            return response()->json([
                'success' => false,
                'message' => 'Reverb alleen beschikbaar op server',
                'local' => true,
            ]);
        }

        try {
            $result = Process::run('supervisorctl stop ' . $this->processName());

            return response()->json([
                'success' => $result->successful(),
                'message' => $result->successful() ? 'Reverb gestopt' : 'Fout bij stoppen',
                'output' => trim($result->output() . $result->errorOutput()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Restart Reverb
     */
    public function restart(): JsonResponse
    {
        if (app()->environment('local')) {
            return response()->json([
                'success' => false,
                'message' => 'Reverb alleen beschikbaar op server',
                'local' => true,
            ]);
        }

        try {
            $result = Process::run('supervisorctl restart ' . $this->processName());

            return response()->json([
                'success' => $result->successful(),
                'message' => $result->successful() ? 'Reverb herstart' : 'Fout bij herstarten',
                'output' => trim($result->output() . $result->errorOutput()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout: ' . $e->getMessage(),
            ]);
        }
    }
}
