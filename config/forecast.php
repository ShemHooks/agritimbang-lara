<?php

return [
    'python_binary' => env('FORECAST_PYTHON_BINARY', 'python'),
    'timeout_seconds' => (int) env('FORECAST_TIMEOUT_SECONDS', 30),
    'minimum_observations' => 12,
    'maximum_observations' => 104,
];
