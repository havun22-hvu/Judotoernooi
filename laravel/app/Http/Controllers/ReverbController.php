<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Process;

class ReverbController extends Controller
{
    /**
     * Get Reverb status
     */
    public function status(): JsonResponse
    {
        // Only on production/staging server
        if (app()->environment('local')) {
            return response()->json([
                'running' => false,
                'message' => 'Reverb alleen beschikbaar op server',
                'local' => true,
            ]);
        }

        // Staging heeft geen eigen Reverb server
        if (app()->environment('staging')) {
            return response()->json([
                'running' => false,
                'message' => 'Reverb niet beschikbaar op staging',
                'staging' => true,
            ]);
        }

        $result = Process::run('supervisorctl status reverb');
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
            $result = Process::run('supervisorctl start reverb');

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
            $result = Process::run('supervisorctl stop reverb');

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
}
