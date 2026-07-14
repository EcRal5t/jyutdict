<?php
/** Lightweight HTTP benchmark compatible with PHP 7.4 and no extensions. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8888', '/');
$iterations = max(1, min(100, (int)($argv[2] ?? 7)));
$cases = [
    'detail-one' => '/api/v1.0/detail?chara=' . rawurlencode('廣'),
    'detail-eight' => '/api/v1.0/detail?chara=' . rawurlencode('廣州話粵音字典'),
    'pronunciation' => '/api/v1.0/detail?pron=jyut6&ascii=1',
    'sheet-meta' => '/api/v1.0/sheet',
    'sheet-search' => '/api/v1.0/sheet?q=mat1',
];

function benchmarkPercentile(array $values, $fraction) {
    sort($values, SORT_NUMERIC);
    $index = (int)ceil(count($values) * $fraction) - 1;
    return $values[max(0, min(count($values) - 1, $index))];
}

echo "Base URL: {$baseUrl}\n";
echo "Iterations: {$iterations}\n";

foreach ($cases as $name => $path) {
    $times = [];
    $bytes = null;
    $hash = null;
    for ($i = 0; $i < $iterations; $i++) {
        $started = microtime(true);
        $body = @file_get_contents($baseUrl . $path);
        $elapsed = (microtime(true) - $started) * 1000;
        if ($body === false) {
            fwrite(STDERR, "Request failed: {$name}\n");
            exit(1);
        }
        json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            fwrite(STDERR, "Invalid JSON: {$name}\n");
            exit(1);
        }
        $times[] = $elapsed;
        $bytes = strlen($body);
        $hash = hash('sha256', $body);
    }
    printf(
        "%-16s median=%7.2fms p95=%7.2fms bytes=%d sha256=%s\n",
        $name,
        benchmarkPercentile($times, 0.5),
        benchmarkPercentile($times, 0.95),
        $bytes,
        substr($hash, 0, 12)
    );
}

