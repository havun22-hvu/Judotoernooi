<?php

namespace App\Services;

use App\Models\Toernooi;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ConstraintSolverService
{
    private string $pythonPath;
    private string $scriptPath;

    public function __construct()
    {
        // Use python from PATH or configure in .env
        $this->pythonPath = config('services.python.path', 'python');
        $this->scriptPath = base_path('scripts/blok_mat_solver.py');
    }

    /**
     * Solve blok/mat distribution using OR-Tools constraint solver
     */
    public function solveBlokMatDistribution(Toernooi $toernooi): array
    {
        // Prepare input data
        $inputData = $this->prepareInputData($toernooi);

        // Run Python solver
        $result = $this->runPythonSolver($inputData);

        if (!$result['success']) {
            Log::warning('Constraint solver failed', [
                'error' => $result['error'] ?? 'Unknown error',
                'toernooi_id' => $toernooi->id,
            ]);
        }

        return $result;
    }

    /**
     * Prepare input data for the solver
     */
    private function prepareInputData(Toernooi $toernooi): array
    {
        $blokken = $toernooi->blokken()
            ->orderBy('nummer')
            ->get(['id', 'nummer'])
            ->map(fn ($b) => ['id' => $b->id, 'nummer' => $b->nummer])
            ->toArray();

        $matten = $toernooi->matten()
            ->orderBy('nummer')
            ->get(['id', 'nummer'])
            ->map(fn ($m) => ['id' => $m->id, 'nummer' => $m->nummer])
            ->toArray();

        $poules = $toernooi->poules()
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->orderBy('nummer')
            ->get(['id', 'leeftijdsklasse', 'gewichtsklasse', 'aantal_wedstrijden'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'leeftijdsklasse' => $p->leeftijdsklasse,
                'gewichtsklasse' => $p->gewichtsklasse,
                'aantal_wedstrijden' => $p->aantal_wedstrijden,
            ])
            ->toArray();

        return [
            'blokken' => $blokken,
            'matten' => $matten,
            'poules' => $poules,
        ];
    }

    /**
     * Run the Python constraint solver
     */
    private function runPythonSolver(array $inputData): array
    {
        if (!file_exists($this->scriptPath)) {
            return [
                'success' => false,
                'error' => 'Python solver script not found: ' . $this->scriptPath,
            ];
        }

        $process = new Process([
            $this->pythonPath,
            $this->scriptPath,
        ]);

        $process->setInput(json_encode($inputData));
        $process->setTimeout(60); // 60 seconds max

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput() ?: 'Process failed',
                ];
            }

            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON output from solver: ' . $output,
                ];
            }

            return $result;

        } catch (ProcessFailedException $e) {
            return [
                'success' => false,
                'error' => 'Process failed: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if OR-Tools is available
     */
    public function isAvailable(): bool
    {
        $process = new Process([
            $this->pythonPath,
            '-c',
            'from ortools.sat.python import cp_model; print("OK")',
        ]);

        $process->setTimeout(10);

        try {
            $process->run();
            return $process->isSuccessful() && str_contains($process->getOutput(), 'OK');
        } catch (\Exception $e) {
            return false;
        }
    }
}
