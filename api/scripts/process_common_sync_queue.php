<?php
/**
 * Consume Excel-import notifications from common_sync_queue.
 *
 * Intended cron entry (run once per minute):
 *   * * * * * cd /path/to/jyut && php api/scripts/process_common_sync_queue.php --max=10 --retry-failed >> /var/log/jyutdict-common-sync.log 2>&1
 *
 * Usage:
 *   php api/scripts/process_common_sync_queue.php --status
 *   php api/scripts/process_common_sync_queue.php --max=10
 *   php api/scripts/process_common_sync_queue.php --max=10 --retry-failed
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';

$options = getopt('', ['max:', 'retry-failed', 'status']);
$max = isset($options['max']) ? (int)$options['max'] : 10;
$retryFailed = isset($options['retry-failed']);
$statusOnly = isset($options['status']);

if ($max < 1 || $max > 100) {
    throw new RuntimeException('--max must be between 1 and 100');
}

function queueTableExists(PDO $dbh) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'common_sync_queue'"
    );
    $stmt->execute();
    return (int)$stmt->fetchColumn() > 0;
}

function queueWorkerStateExists(PDO $dbh) {
    $stmt = $dbh->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'maintenance_worker_state'"
    );
    return (int)$stmt->fetchColumn() === 1;
}

function queueWorkerStart(PDO $dbh) {
    if (!queueWorkerStateExists($dbh)) {
        return;
    }
    $dbh->exec(
        "INSERT INTO `maintenance_worker_state`
         (`worker_name`, `last_seen_at`, `last_started_at`, `last_status`)
         VALUES ('common_sync_queue', NOW(), NOW(), 'running')
         ON DUPLICATE KEY UPDATE
           `last_started_at` = IF(`last_seen_at` IS NULL OR `last_seen_at` < DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW(), `last_started_at`),
           `last_status` = IF(`last_seen_at` IS NULL OR `last_seen_at` < DATE_SUB(NOW(), INTERVAL 5 MINUTE), 'running', `last_status`),
           `last_error` = IF(`last_seen_at` IS NULL OR `last_seen_at` < DATE_SUB(NOW(), INTERVAL 5 MINUTE), NULL, `last_error`),
           `last_seen_at` = IF(`last_seen_at` IS NULL OR `last_seen_at` < DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW(), `last_seen_at`)"
    );
}

function queueWorkerFinish(PDO $dbh, $processed, $failures, $errorMessage = null) {
    if (!queueWorkerStateExists($dbh)) {
        return;
    }
    if ((int)$processed === 0 && (int)$failures === 0 && $errorMessage === null) {
        $dbh->exec(
            "UPDATE `maintenance_worker_state`
             SET `last_seen_at` = NOW(), `last_finished_at` = NOW(), `last_status` = 'success',
                 `last_processed` = 0, `last_failures` = 0, `last_error` = NULL
             WHERE `worker_name` = 'common_sync_queue'
               AND (`last_status` = 'running' OR `last_seen_at` IS NULL
                    OR `last_seen_at` < DATE_SUB(NOW(), INTERVAL 5 MINUTE))"
        );
        return;
    }
    $stmt = $dbh->prepare(
        "INSERT INTO `maintenance_worker_state`
         (`worker_name`, `last_seen_at`, `last_started_at`, `last_finished_at`, `last_status`,
          `last_processed`, `last_failures`, `last_error`)
         VALUES ('common_sync_queue', NOW(), NOW(), NOW(), ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           `last_seen_at` = NOW(), `last_finished_at` = NOW(), `last_status` = VALUES(`last_status`),
           `last_processed` = VALUES(`last_processed`), `last_failures` = VALUES(`last_failures`),
           `last_error` = VALUES(`last_error`)"
    );
    $stmt->execute([
        $failures > 0 || $errorMessage !== null ? 'failed' : 'success',
        (int)$processed,
        (int)$failures,
        $errorMessage === null ? null : mb_substr((string)$errorMessage, 0, 4000, 'UTF-8'),
    ]);
}

function queuePrintStatus(PDO $dbh) {
    $rows = $dbh->query(
        "SELECT `status`, COUNT(*) AS `count`,
                SUM(`processed_generation` < `requested_generation`) AS `outstanding`
         FROM `common_sync_queue`
         GROUP BY `status`
         ORDER BY FIELD(`status`, 'pending', 'processing', 'failed', 'done')"
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(
        ['database' => $dbh->query('SELECT DATABASE()')->fetchColumn(), 'queue' => $rows],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
}

function queueClaim(PDO $dbh, $retryFailed, array $excludedAreaIds) {
    $dbh->beginTransaction();
    try {
        $retryClause = $retryFailed
            ? "OR (`status` = 'failed' AND `attempt_count` < 3)"
            : '';
        $excludedClause = '';
        if ($excludedAreaIds) {
            $excludedClause = 'AND `area_id` NOT IN ('
                . implode(',', array_fill(0, count($excludedAreaIds), '?')) . ')';
        }
        $sql = "SELECT `area_id`, `legacy_table`, `source_ref`, `requested_generation`
                FROM `common_sync_queue`
                WHERE `processed_generation` < `requested_generation`
                  AND (
                    `status` = 'pending'
                    OR (`status` = 'processing' AND `started_at` < DATE_SUB(NOW(), INTERVAL 15 MINUTE))
                    {$retryClause}
                  )
                  {$excludedClause}
                ORDER BY `requested_at`, `area_id`
                LIMIT 1 FOR UPDATE";
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array_values($excludedAreaIds));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $dbh->commit();
            return null;
        }

        $stmt = $dbh->prepare(
            "UPDATE `common_sync_queue`
             SET `status` = 'processing', `started_at` = NOW(), `completed_at` = NULL,
                 `attempt_count` = `attempt_count` + 1, `last_error` = NULL
             WHERE `area_id` = ?"
        );
        $stmt->execute([(int)$row['area_id']]);
        $dbh->commit();

        $row['area_id'] = (int)$row['area_id'];
        $row['requested_generation'] = (int)$row['requested_generation'];
        return $row;
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        throw $error;
    }
}

function queueRunSync(array $job) {
    $sourceRef = $job['source_ref'] !== null && $job['source_ref'] !== ''
        ? (string)$job['source_ref']
        : (string)$job['legacy_table'];
    $command = [
        PHP_BINARY,
        __DIR__ . '/sync_common_entries.php',
        '--area-id=' . $job['area_id'],
        '--apply',
        '--source-ref=' . $sourceRef,
        '--message=Automatic sync after Excel SQL import',
    ];
    $pipes = [];
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start sync_common_entries.php');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'stdout' => trim((string)$stdout),
        'stderr' => trim((string)$stderr),
    ];
}

function queueComplete(PDO $dbh, array $job, array $result) {
    $success = $result['exit_code'] === 0;
    $errorText = trim($result['stderr'] . ($success ? '' : "\n" . $result['stdout']));
    if ($errorText === '' && !$success) {
        $errorText = 'sync_common_entries.php exited with code ' . $result['exit_code'];
    }
    if (mb_strlen($errorText, 'UTF-8') > 4000) {
        $errorText = mb_substr($errorText, -4000, null, 'UTF-8');
    }

    $dbh->beginTransaction();
    try {
        $stmt = $dbh->prepare(
            "SELECT `requested_generation` FROM `common_sync_queue`
             WHERE `area_id` = ? FOR UPDATE"
        );
        $stmt->execute([$job['area_id']]);
        $currentGeneration = (int)$stmt->fetchColumn();
        $newerRequestExists = $currentGeneration > $job['requested_generation'];

        if ($success) {
            $stmt = $dbh->prepare(
                "UPDATE `common_sync_queue`
                 SET `processed_generation` = GREATEST(`processed_generation`, ?),
                     `status` = ?, `completed_at` = IF(? = 1, NULL, NOW()), `last_error` = NULL
                 WHERE `area_id` = ?"
            );
            $stmt->execute([
                $job['requested_generation'],
                $newerRequestExists ? 'pending' : 'done',
                $newerRequestExists ? 1 : 0,
                $job['area_id'],
            ]);
        } else {
            $stmt = $dbh->prepare(
                "UPDATE `common_sync_queue`
                 SET `status` = ?, `completed_at` = IF(? = 1, NULL, NOW()), `last_error` = ?
                 WHERE `area_id` = ?"
            );
            $stmt->execute([
                $newerRequestExists ? 'pending' : 'failed',
                $newerRequestExists ? 1 : 0,
                $newerRequestExists ? null : $errorText,
                $job['area_id'],
            ]);
        }
        $dbh->commit();
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        throw $error;
    }
}

if (!queueTableExists($dbh)) {
    fwrite(STDERR, "common_sync_queue is missing; run common_entries_schema.php --apply first.\n");
    exit(2);
}

if ($statusOnly) {
    queuePrintStatus($dbh);
    exit(0);
}

$lockStmt = $dbh->query("SELECT GET_LOCK('jyutdict_common_sync_queue_worker', 0)");
if ((int)$lockStmt->fetchColumn() !== 1) {
    echo "Another queue worker is already running.\n";
    exit(0);
}

$processed = 0;
$failures = 0;
$attemptedAreaIds = [];
$workerError = null;
queueWorkerStart($dbh);
try {
    while ($processed < $max) {
        $job = queueClaim($dbh, $retryFailed, $attemptedAreaIds);
        if ($job === null) {
            break;
        }
        $attemptedAreaIds[] = $job['area_id'];

        try {
            $result = queueRunSync($job);
        } catch (Throwable $error) {
            $result = ['exit_code' => 255, 'stdout' => '', 'stderr' => $error->getMessage()];
        }
        queueComplete($dbh, $job, $result);
        $processed++;
        if ($result['exit_code'] !== 0) {
            $failures++;
        }
        echo json_encode([
            'area_id' => $job['area_id'],
            'table' => $job['legacy_table'],
            'generation' => $job['requested_generation'],
            'ok' => $result['exit_code'] === 0,
            'exit_code' => $result['exit_code'],
            'sync_output' => $result['stdout'],
            'error' => $result['stderr'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
} catch (Throwable $error) {
    $workerError = $error->getMessage();
    throw $error;
} finally {
    queueWorkerFinish($dbh, $processed, $failures, $workerError);
    $dbh->query("SELECT RELEASE_LOCK('jyutdict_common_sync_queue_worker')");
}

if ($processed > 0 || $failures > 0) {
    echo json_encode(
        ['processed' => $processed, 'failures' => $failures],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
}
exit($failures > 0 ? 1 : 0);
