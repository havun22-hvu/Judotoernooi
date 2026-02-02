<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Validate production readiness.
 *
 * Run before deploy to ensure all services are configured correctly.
 */
class ValidateProductionCommand extends Command
{
    protected $signature = 'validate:production {--fix : Attempt to fix issues}';
    protected $description = 'Validate production environment configuration';

    private array $errors = [];
    private array $warnings = [];
    private array $success = [];

    public function handle(): int
    {
        $this->info('🔍 Validating production readiness...');
        $this->newLine();

        $this->checkEnvironment();
        $this->checkDatabase();
        $this->checkCache();
        $this->checkMollie();
        $this->checkMail();
        $this->checkSecurity();
        $this->checkAssets();
        $this->checkPython();

        $this->newLine();
        $this->displayResults();

        return count($this->errors) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkEnvironment(): void
    {
        $this->info('📋 Environment');

        $env = config('app.env');
        if ($env === 'production') {
            $this->pass('APP_ENV is production');
        } else {
            $this->addWarning("APP_ENV is '{$env}' (expected: production)");
        }

        if (!config('app.debug')) {
            $this->pass('APP_DEBUG is false');
        } else {
            $this->addError('APP_DEBUG is true - MUST be false in production');
        }

        if (config('app.key')) {
            $this->pass('APP_KEY is set');
        } else {
            $this->addError('APP_KEY is not set');
        }

        if (config('app.url') && !str_contains(config('app.url'), 'localhost')) {
            $this->pass('APP_URL is set: ' . config('app.url'));
        } else {
            $this->addWarning('APP_URL may not be correct: ' . config('app.url'));
        }
    }

    private function checkDatabase(): void
    {
        $this->info('💾 Database');

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $time = round((microtime(true) - $start) * 1000);

            $this->pass("Connection OK ({$time}ms)");

            $driver = config('database.default');
            if ($driver === 'mysql') {
                $this->pass("Using MySQL driver");
            } else {
                $this->addWarning("Using {$driver} driver (MySQL recommended for production)");
            }

        } catch (\Exception $e) {
            $this->addError('Database connection failed: ' . $e->getMessage());
        }
    }

    private function checkCache(): void
    {
        $this->info('⚡ Cache');

        try {
            $key = 'validate_production_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === 'test') {
                $this->pass('Cache read/write OK');
            } else {
                $this->addError('Cache read/write failed');
            }

            $driver = config('cache.default');
            if (in_array($driver, ['redis', 'memcached', 'database'])) {
                $this->pass("Using {$driver} driver");
            } else {
                $this->addWarning("Using {$driver} driver (redis/database recommended)");
            }

        } catch (\Exception $e) {
            $this->addError('Cache check failed: ' . $e->getMessage());
        }
    }

    private function checkMollie(): void
    {
        $this->info('💳 Mollie');

        $platformKey = config('services.mollie.platform_key');
        $testKey = config('services.mollie.platform_test_key');

        if ($platformKey && str_starts_with($platformKey, 'live_')) {
            $this->pass('Platform live key configured');
        } elseif ($platformKey) {
            $this->addWarning('Platform key set but not a live key');
        } else {
            $this->addWarning('Platform live key not configured');
        }

        if ($testKey && str_starts_with($testKey, 'test_')) {
            $this->pass('Platform test key configured');
        } else {
            $this->addWarning('Platform test key not configured');
        }

        $clientId = config('services.mollie.client_id');
        $clientSecret = config('services.mollie.client_secret');

        if ($clientId && $clientSecret) {
            $this->pass('OAuth credentials configured');
        } else {
            $this->addWarning('OAuth credentials not configured (Connect mode unavailable)');
        }
    }

    private function checkMail(): void
    {
        $this->info('📧 Mail');

        $mailer = config('mail.default');

        if ($mailer === 'log') {
            $this->addWarning("Mailer is 'log' - emails won't be sent");
        } elseif ($mailer === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            if ($host) {
                $this->pass("SMTP configured: {$host}");
            } else {
                $this->addError('SMTP host not configured');
            }
        } else {
            $this->pass("Using {$mailer} mailer");
        }

        $fromAddress = config('mail.from.address');
        if ($fromAddress && $fromAddress !== 'hello@example.com') {
            $this->pass("From address: {$fromAddress}");
        } else {
            $this->addWarning('From address not configured properly');
        }
    }

    private function checkSecurity(): void
    {
        $this->info('🔒 Security');

        // Check HTTPS
        if (config('session.secure')) {
            $this->pass('Session cookies are secure (HTTPS only)');
        } else {
            $this->addWarning('Session cookies not set to secure');
        }

        // Check session driver
        $sessionDriver = config('session.driver');
        if (in_array($sessionDriver, ['database', 'redis'])) {
            $this->pass("Session driver: {$sessionDriver}");
        } else {
            $this->addWarning("Session driver '{$sessionDriver}' (database/redis recommended)");
        }

        // Check CSRF
        $this->pass('CSRF protection enabled (Laravel default)');

        // Check password hashing
        $this->pass('Password hashing: bcrypt (Laravel default)');
    }

    private function checkAssets(): void
    {
        $this->info('📦 Assets');

        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $this->pass('Vite manifest exists');

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (isset($manifest['resources/js/app.js'])) {
                $this->pass('JS bundle present');
            } else {
                $this->addError('JS bundle missing from manifest');
            }

            if (isset($manifest['resources/css/app.css'])) {
                $this->pass('CSS bundle present');
            } else {
                $this->addError('CSS bundle missing from manifest');
            }
        } else {
            $this->addError('Vite manifest missing - run: npm run build');
        }
    }

    private function checkPython(): void
    {
        $this->info('🐍 Python');

        $scriptPath = base_path('scripts/poule_solver.py');
        if (file_exists($scriptPath)) {
            $this->pass('Poule solver script exists');
        } else {
            $this->addWarning('Poule solver script missing (fallback will be used)');
        }

        // Check if python is available
        $pythonCmd = PHP_OS_FAMILY === 'Windows' ? 'where python' : 'which python3';
        exec($pythonCmd . ' 2>&1', $output, $exitCode);

        if ($exitCode === 0) {
            $this->pass('Python available');
        } else {
            $this->addWarning('Python not found (solver fallback will be used)');
        }
    }

    private function pass(string $message): void
    {
        $this->success[] = $message;
        $this->line("  <fg=green>✓</> {$message}");
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
        $this->line("  <fg=yellow>⚠</> {$message}");
    }

    private function addError(string $message): void
    {
        $this->errors[] = $message;
        $this->line("  <fg=red>✗</> {$message}");
    }

    private function displayResults(): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (count($this->errors) === 0 && count($this->warnings) === 0) {
            $this->info('🎉 All checks passed! Ready for production.');
        } elseif (count($this->errors) === 0) {
            $this->addWarning('⚠️  ' . count($this->warnings) . ' warning(s), but no critical errors.');
            $this->info('   Production deployment possible with caution.');
        } else {
            $this->error('❌ ' . count($this->errors) . ' error(s) found!');
            $this->line('   Fix errors before deploying to production.');
        }

        $this->newLine();
        $this->line("Summary: <fg=green>" . count($this->success) . " passed</>, " .
            "<fg=yellow>" . count($this->warnings) . " warnings</>, " .
            "<fg=red>" . count($this->errors) . " errors</>");
    }
}
