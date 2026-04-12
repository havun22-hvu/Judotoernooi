<?php

return [
    'project' => env('OBSERVABILITY_PROJECT', 'judotoernooi'),
    'enabled' => env('OBSERVABILITY_ENABLED', true),
    'sampling_rate' => env('OBSERVABILITY_SAMPLING_RATE', 1.0),
    'slow_query_threshold_ms' => env('SLOW_QUERY_THRESHOLD_MS', 100),
    'error_trace_max_length' => 5000,
    'excluded_paths' => ['up', 'health'],
];
