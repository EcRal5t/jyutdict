<?php
/** Compare legacy and unified lookup helpers without changing the public mode. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/LocationLookup.php';
require_once __DIR__ . '/../core/CommonEntries.php';

function commonVerifyFirstDifference($left, $right, $path = '$') {
    if (gettype($left) !== gettype($right)) {
        return $path . ': type ' . gettype($left) . ' !== ' . gettype($right) .
            ' (' . var_export($left, true) . ' !== ' . var_export($right, true) . ')';
    }
    if (is_array($left)) {
        if (array_keys($left) !== array_keys($right)) {
            return $path . ': array keys/order differ';
        }
        foreach ($left as $key => $value) {
            $difference = commonVerifyFirstDifference($value, $right[$key], $path . '[' . var_export($key, true) . ']');
            if ($difference !== null) {
                return $difference;
            }
        }
        return null;
    }
    return $left === $right ? null : $path . ': ' . var_export($left, true) . ' !== ' . var_export($right, true);
}

function commonVerifyRun($label, callable $legacy, callable $common) {
    $legacyStarted = microtime(true);
    $legacyResult = $legacy();
    $legacyMs = (microtime(true) - $legacyStarted) * 1000;

    $commonStarted = microtime(true);
    $commonResult = $common();
    $commonMs = (microtime(true) - $commonStarted) * 1000;

    $legacyJson = json_encode($legacyResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $commonJson = json_encode($commonResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($legacyJson === false || $commonJson === false) {
        throw new RuntimeException("JSON encoding failed for {$label}");
    }
    $equal = hash_equals(hash('sha256', $legacyJson), hash('sha256', $commonJson)) &&
             $legacyJson === $commonJson;
    printf(
        "%-28s equal=%s legacy=%8.2fms common=%8.2fms bytes=%d hash=%s\n",
        $label,
        $equal ? 'yes' : 'NO',
        $legacyMs,
        $commonMs,
        strlen($legacyJson),
        substr(hash('sha256', $legacyJson), 0, 12)
    );
    if (!$equal) {
        fwrite(STDERR, commonVerifyFirstDifference($legacyResult, $commonResult) . "\n");
        throw new RuntimeException("Legacy/common query mismatch: {$label}");
    }
}

$areas = jyutdictLoadAreas($dbh);
$selectedAreas = array_values(array_filter($areas, function ($area) {
    return in_array($area['id'], [1, 26, 69], true);
}));
$noAreas = [];

$characterCases = [
    'character-one' => ['廣'],
    'character-eight' => preg_split('//u', '廣州話粵音字典', -1, PREG_SPLIT_NO_EMPTY),
    'character-duplicate-input' => ['一', '一', '廣'],
    'character-no-result' => ['\u{10FFFF}'],
];

foreach ($characterCases as $label => $characters) {
    commonVerifyRun(
        $label,
        function () use ($dbh, $areas, $characters) {
            return jyutdictLookupLocationCharacters($dbh, $areas, $characters);
        },
        function () use ($dbh, $areas, $characters) {
            return jyutdictLookupCommonLocationCharacters($dbh, $areas, $characters);
        }
    );
}

commonVerifyRun(
    'character-selected-areas',
    function () use ($dbh, $selectedAreas) {
        return jyutdictLookupLocationCharacters($dbh, $selectedAreas, ['一', '廣']);
    },
    function () use ($dbh, $selectedAreas) {
        return jyutdictLookupCommonLocationCharacters($dbh, $selectedAreas, ['一', '廣']);
    }
);

commonVerifyRun(
    'character-empty-areas',
    function () use ($dbh, $noAreas) {
        return jyutdictLookupLocationCharacters($dbh, $noAreas, ['一']);
    },
    function () use ($dbh, $noAreas) {
        return jyutdictLookupCommonLocationCharacters($dbh, $noAreas, ['一']);
    }
);

$pronunciationCases = [
    'pron-exact' => ['initial' => 'j', 'nuclei' => 'yu', 'coda' => 't', 'tone' => '6'],
    'pron-tone-wildcard' => ['initial' => 'j', 'nuclei' => 'yu', 'coda' => 't', 'tone' => '%'],
    'pron-initial-wildcard' => ['initial' => '%', 'nuclei' => 'aa', 'coda' => 'n', 'tone' => '1'],
    'pron-empty-initial' => ['initial' => '', 'nuclei' => 'aa', 'coda' => 'i', 'tone' => '%'],
];

foreach ($pronunciationCases as $label => $parts) {
    commonVerifyRun(
        $label,
        function () use ($dbh, $areas, $parts) {
            return jyutdictLookupSourcePronunciations($dbh, $areas, $parts);
        },
        function () use ($dbh, $areas, $parts) {
            return jyutdictLookupCommonPronunciations($dbh, $areas, $parts);
        }
    );
}

$selectedParts = ['initial' => 'j', 'nuclei' => 'yu', 'coda' => 't', 'tone' => '6'];
commonVerifyRun(
    'pron-selected-areas',
    function () use ($dbh, $selectedAreas, $selectedParts) {
        return jyutdictLookupSourcePronunciations($dbh, $selectedAreas, $selectedParts);
    },
    function () use ($dbh, $selectedAreas, $selectedParts) {
        return jyutdictLookupCommonPronunciations($dbh, $selectedAreas, $selectedParts);
    }
);

echo "All legacy/common query comparisons passed.\n";
