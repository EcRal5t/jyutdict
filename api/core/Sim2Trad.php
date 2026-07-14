<?php
/**
 * 簡繁異體轉換
 * 從根目錄 Lookup.class.php 重寫，用單次 JOIN 查詢替代 N+1 查詢，修復 SQL 注入。
 */

/**
 * 查詢一個字的所有繁/異體字
 *
 * @param string $character 輸入字元
 * @param PDO $dbh 數據庫連接
 * @return array 包含原字及所有繁/異體的數組
 */
function querySim2Trad($character, $dbh) {
    $result = [$character];

    try {
        $stmt = $dbh->prepare("
            SELECT DISTINCT l2.`chara`
            FROM `character_simtrad_list` l1
            JOIN `character_simtrad_map` m ON l1.`chara_id` = m.`chara_id_sim`
            JOIN `character_simtrad_list` l2 ON m.`chara_id_trad` = l2.`chara_id`
            WHERE l1.`chara` = :chara
        ");
        $stmt->execute([':chara' => $character]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $tradChara) {
            if ($tradChara !== $character) {
                $result[] = $tradChara;
            }
        }
    } catch (Exception $e) {
        // 查詢失敗時返回原字
    }

    return $result;
}

/**
 * Batch version of querySim2Trad. The flattened result keeps input order and
 * preserves repeated input characters, matching the legacy per-character loop.
 */
function querySim2TradBatch(array $characters, PDO $dbh) {
    if (!$characters) {
        return [];
    }

    $unique = [];
    $seen = [];
    foreach ($characters as $character) {
        if (!isset($seen[$character])) {
            $seen[$character] = true;
            $unique[] = $character;
        }
    }

    $mapped = [];
    try {
        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $stmt = $dbh->prepare("
            SELECT l1.`chara` AS `source_chara`, l2.`chara` AS `trad_chara`
            FROM `character_simtrad_list` l1
            JOIN `character_simtrad_map` m ON l1.`chara_id` = m.`chara_id_sim`
            JOIN `character_simtrad_list` l2 ON m.`chara_id_trad` = l2.`chara_id`
            WHERE l1.`chara` IN ($placeholders)
            ORDER BY l1.`chara_id`, m.`chara_id_trad`
        ");
        $stmt->execute($unique);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapped[$row['source_chara']][] = $row['trad_chara'];
        }
    } catch (Exception $e) {
        $mapped = [];
    }

    $result = [];
    foreach ($characters as $character) {
        $result[] = $character;
        $localSeen = [$character => true];
        foreach ($mapped[$character] ?? [] as $tradCharacter) {
            if (!isset($localSeen[$tradCharacter])) {
                $result[] = $tradCharacter;
                $localSeen[$tradCharacter] = true;
            }
        }
    }
    return $result;
}
