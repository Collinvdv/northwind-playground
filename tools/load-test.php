<?php
/**
 * Load test: pretends to be a classroom pressing Check at the same moment.
 *
 *   php tools/load-test.php https://northwind.ddev.site/ --students=150 --rounds=3
 *
 * Options:
 *   --students=N      requests per round (default 150)
 *   --rounds=N        rounds, one after another (default 3)
 *   --concurrency=N   requests in flight at once (default: all students)
 *   --heavy=P         percent of runaway queries (cross joins) in the mix (default 10)
 *   --insecure        skip TLS verification (ddev's self-signed certificate)
 *
 * The mix per round: 70 % correct answers (the reference solution of a random
 * exercise), 20 % wrong answers, 10 % runaway cross joins that MySQL kills
 * after QUERY_TIMEOUT_MS. Only HTTP is used; the tool never talks to MySQL.
 *
 * Reads: HTTP status per request, latency p50 / p95 / max, verdicts, and
 * MySQL connection errors in the answers. Exit code 1 when a status other
 * than 200 / 503 / 429 comes back or a connection error is seen.
 *
 * 503 means lib.php's admission gate was full and the browser would retry;
 * a few are fine. 429 comes from the host's per-IP rate limit (see README):
 * running this from one laptop against webhosting.be shows where that
 * limit sits, which is useful to know before the exam.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require dirname(__DIR__) . '/lib.php';

$opts = ['students' => 150, 'rounds' => 3, 'concurrency' => 0, 'heavy' => 10, 'insecure' => false];
$base = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(\w+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        $base = $arg;
    }
}
if ($base === null) {
    fwrite(STDERR, "Usage: php tools/load-test.php <url> [--students=150] [--rounds=3] [--concurrency=N] [--heavy=10] [--insecure]\n");
    exit(2);
}
$base = rtrim($base, '/') . '/';
$students = max(1, (int) $opts['students']);
$rounds = max(1, (int) $opts['rounds']);
$concurrency = (int) $opts['concurrency'] ?: $students;
$heavyPercent = max(0, min(100, (int) $opts['heavy']));
$insecure = (bool) $opts['insecure'];

$exercises = [];
foreach (loadChapters() as $chapter) {
    foreach ($chapter['exercises'] as $ex) {
        $exercises[] = ['c' => $chapter['number'], 'e' => $ex['number'], 'solution' => rtrim(trim($ex['solution']), ';')];
    }
}
if (!$exercises) {
    fwrite(STDERR, "No exercises found.\n");
    exit(2);
}

$heavySql = 'SELECT COUNT(*) FROM Orders a, Orders b, OrderDetails c WHERE a.Freight + b.Freight > c.Quantity';

/** One simulated student: [url, sql, kind]. */
function makeRequest(array $exercises, int $heavyPercent, string $base): array
{
    $ex = $exercises[array_rand($exercises)];
    $roll = random_int(1, 100);
    if ($roll <= $heavyPercent) {
        return [$base . "exercise.php?c={$ex['c']}&e={$ex['e']}", $GLOBALS['heavySql'], 'heavy'];
    }
    if ($roll <= $heavyPercent + 20) {
        return [$base . "exercise.php?c={$ex['c']}&e={$ex['e']}", 'SELECT * FROM (' . $ex['solution'] . ') AS wrong LIMIT 1', 'wrong'];
    }
    return [$base . "exercise.php?c={$ex['c']}&e={$ex['e']}", $ex['solution'], 'correct'];
}

function percentile(array $sorted, float $p): float
{
    if (!$sorted) {
        return 0.0;
    }
    $i = (int) ceil($p * count($sorted)) - 1;
    return $sorted[max(0, min($i, count($sorted) - 1))];
}

$failed = false;
for ($round = 1; $round <= $rounds; $round++) {
    $queue = [];
    for ($i = 0; $i < $students; $i++) {
        $queue[] = makeRequest($exercises, $heavyPercent, $base);
    }

    $multi = curl_multi_init();
    $active = [];           // handle id => [kind, start]
    $stats = ['status' => [], 'latency' => [], 'verdict' => [], 'connErrors' => 0, 'transport' => 0];
    $t0 = microtime(true);

    $launch = function () use (&$queue, &$active, $multi, $insecure): void {
        [$url, $sql, $kind] = array_shift($queue);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['sql' => $sql, 'action' => 'check']),
            CURLOPT_HTTPHEADER => ['X-Requested-With: fetch'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => !$insecure,
            CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
        ]);
        curl_multi_add_handle($multi, $ch);
        $active[(int) $ch] = ['kind' => $kind, 'handle' => $ch];
    };

    while ($queue && count($active) < $concurrency) {
        $launch();
    }
    do {
        $status = curl_multi_exec($multi, $running);
        if ($status === CURLM_OK && $running) {
            curl_multi_select($multi, 0.1);
        }
        while (($done = curl_multi_info_read($multi)) !== false) {
            $ch = $done['handle'];
            $info = $active[(int) $ch];
            unset($active[(int) $ch]);
            $body = (string) curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $ms = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
            if ($done['result'] !== CURLE_OK) {
                $stats['transport']++;
                $stats['status']['curl:' . curl_strerror($done['result'])] = ($stats['status']['curl:' . curl_strerror($done['result'])] ?? 0) + 1;
            } else {
                $stats['status'][$code] = ($stats['status'][$code] ?? 0) + 1;
                $stats['latency'][] = $ms;
                if (preg_match('/class="verdict (\w+)"/', $body, $m)) {
                    $stats['verdict'][$m[1]] = ($stats['verdict'][$m[1]] ?? 0) + 1;
                }
                if (preg_match('/Too many connections|Cannot connect to MySQL|max_user_connections/i', $body)) {
                    $stats['connErrors']++;
                }
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
            if ($queue) {
                $launch();
                $running = 1;
            }
        }
    } while ($running > 0 || $queue);
    curl_multi_close($multi);

    $wall = microtime(true) - $t0;
    sort($stats['latency']);
    ksort($stats['status']);
    ksort($stats['verdict']);

    printf("Round %d: %d requests in %.1f s (%.0f req/s)\n", $round, $students, $wall, $students / max($wall, 0.001));
    printf("  status   %s\n", implode('  ', array_map(fn($k, $v) => "$k=$v", array_keys($stats['status']), $stats['status'])));
    printf("  verdict  %s\n", $stats['verdict'] ? implode('  ', array_map(fn($k, $v) => "$k=$v", array_keys($stats['verdict']), $stats['verdict'])) : '-');
    printf("  latency  p50=%.0f ms  p95=%.0f ms  max=%.0f ms\n",
        percentile($stats['latency'], 0.5), percentile($stats['latency'], 0.95), percentile($stats['latency'], 1.0));
    if ($stats['connErrors'] > 0) {
        printf("  MySQL connection errors: %d\n", $stats['connErrors']);
        $failed = true;
    }
    foreach ($stats['status'] as $code => $n) {
        if (!in_array($code, [200, 503, 429], true)) {
            $failed = true;
        }
    }
}

echo $failed ? "\nFAIL: unexpected statuses or MySQL connection errors (see above)\n" : "\nOK\n";
exit($failed ? 1 : 0);
