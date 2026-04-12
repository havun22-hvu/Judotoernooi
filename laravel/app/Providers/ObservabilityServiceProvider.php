<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    protected array $skipTables = [
        'request_metrics', 'error_logs', 'slow_queries', 'metrics_aggregated',
    ];

    public function boot(): void
    {
        if (! config('observability.enabled', true)) {
            return;
        }

        $threshold = config('observability.slow_query_threshold_ms', 100);

        DB::listen(function (QueryExecuted $query) use ($threshold) {
            if ($query->time < $threshold) {
                return;
            }

            foreach ($this->skipTables as $table) {
                if (str_contains($query->sql, $table)) {
                    return;
                }
            }

            try {
                DB::connection('havuncore')->table('slow_queries')->insert([
                    'project' => config('observability.project'),
                    'query' => $this->replaceBindings($query),
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                    'request_path' => request()?->path(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
            }
        });
    }

    protected function replaceBindings(QueryExecuted $query): string
    {
        $sql = $query->sql;
        foreach ($query->bindings as $binding) {
            $value = match (true) {
                is_null($binding) => 'NULL',
                is_bool($binding) => $binding ? 'true' : 'false',
                is_int($binding), is_float($binding) => (string) $binding,
                default => "'" . addslashes((string) $binding) . "'",
            };
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        return $sql;
    }
}
