<?php
/** Minimal PHP 7.4 test runner for canonical common-entry hashing. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/LocationLookup.php';
require_once __DIR__ . '/../core/CommonEntries.php';

function commonTestAssert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$base = [
    'chara' => '一',
    'initial' => 'j',
    'nuclei' => 'a',
    'coda' => 't',
    'tone' => '1',
    'ipa' => 'jɐt˥',
    'note' => '',
    'alt_group' => null,
];

$zeroAlt = $base;
$zeroAlt['alt_group'] = 0;
commonTestAssert(
    !hash_equals(jyutdictCommonRowHash($base), jyutdictCommonRowHash($zeroAlt)),
    'NULL and zero alt_group must hash differently'
);

$stringAlt = $base;
$stringAlt['alt_group'] = '1';
$integerAlt = $base;
$integerAlt['alt_group'] = 1;
commonTestAssert(
    hash_equals(jyutdictCommonRowHash($stringAlt), jyutdictCommonRowHash($integerAlt)),
    'PDO numeric strings and integers must normalize to the same semantic hash'
);

$second = $base;
$second['tone'] = '2';
commonTestAssert(
    !hash_equals(jyutdictCommonContentHash([$base, $second]), jyutdictCommonContentHash([$second, $base])),
    'Content hash must preserve visible row order'
);
commonTestAssert(
    !hash_equals(jyutdictCommonContentHash([$base]), jyutdictCommonContentHash([$base, $base])),
    'Content hash must preserve duplicate multiplicity'
);

echo "CommonEntries tests passed.\n";

