<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class ArimaEngine
{
    public function forecast(array $prices, int $weeks): array
    {
        $script = base_path('scripts/arima_forecast.py');
        if (!is_file($script)) {
            throw new RuntimeException('ARIMA Python script is missing.');
        }

        $payload = json_encode([
            'prices' => $prices,
            'weeks' => $weeks,
        ], JSON_THROW_ON_ERROR);
        $configuredBinary = (string) config('forecast.python_binary', 'python');
        $binaries = array_values(array_unique(array_filter([
            $configuredBinary,
            $configuredBinary === 'python' ? 'python3' : null,
        ])));
        $process = null;
        $lastError = 'Python process could not be started.';

        foreach ($binaries as $binary) {
            $candidate = new Process([$binary, $script]);
            $candidate->setInput($payload);
            $candidate->setTimeout((int) config('forecast.timeout_seconds', 30));
            try {
                $candidate->run();
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
                continue;
            }
            $process = $candidate;
            if ($candidate->isSuccessful()) {
                break;
            }
            $lastError = trim($candidate->getErrorOutput()) ?: trim($candidate->getOutput());
        }

        if (!$process || !$process->isSuccessful()) {
            throw new RuntimeException('ARIMA process failed: '.mb_substr($lastError, 0, 400));
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('ARIMA process returned invalid JSON.', 0, $exception);
        }

        if (!is_array($result) || !isset($result['predictions'], $result['order']) || count($result['predictions']) !== $weeks) {
            throw new RuntimeException('ARIMA process returned an invalid forecast payload.');
        }

        foreach ($result['predictions'] as $prediction) {
            foreach (['predicted_price', 'lower_95', 'upper_95'] as $key) {
                if (!isset($prediction[$key]) || !is_numeric($prediction[$key]) || !is_finite((float) $prediction[$key])) {
                    throw new RuntimeException('ARIMA process returned an invalid prediction.');
                }
            }
        }

        return $result;
    }
}
