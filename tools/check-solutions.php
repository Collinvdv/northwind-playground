<?php
/**
 * Runs every reference solution against the database and checks that it
 * works, returns rows, stays under the row cap, and is accepted by the
 * checker when submitted as the student's answer.
 *
 *   php tools/check-solutions.php            (uses .env / environment variables)
 *   DB_HOST=127.0.0.1 DB_PASS=secret php tools/check-solutions.php
 *
 * Exit code 0 when everything passes, 1 otherwise.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require dirname(__DIR__) . '/lib.php';
require dirname(__DIR__) . '/examlib.php';

$pdo = connect($config);
rememberConnectionId($pdo);
$schema = loadSchema($pdo, $config['DB_NAME']);

$failures = 0;
$count = 0;
// The exams in exam-sets/ go through the same checks as the lessons.
$sets = array_map(fn($l) => ['number' => $l['label']] + $l, loadLessons());
foreach (glob(dirname(__DIR__) . '/exam-sets/*.php') ?: [] as $file) {
    $sets[] = loadExamSet(basename($file, '.php')) + ['number' => 'E', 'examples' => [], 'runnable' => true];
}
foreach ($sets as $chapter) {
    echo ($chapter['number'] === 'E' ? '' : "Lesson {$chapter['number']}: "), $chapter['title'], "\n";
    // <erd> diagrams in the theory, notes and tasks must be readable.
    $html = ($chapter['intro'] ?? '') . implode('', array_column($chapter['examples'], 'note')) . implode('', array_column($chapter['exercises'], 'task'));
    if (preg_match_all('/<div class="erd-error">(.*?)<\/div>/s', lessonHtml($html, $schema), $bad)) {
        foreach ($bad[1] as $message) {
            $failures++;
            echo "  FAIL ", html_entity_decode($message), "\n";
        }
    }
    // Examples of a lesson with 'runnable' => false change data: shown, never run.
    foreach ($chapter['runnable'] ? $chapter['examples'] : [] as $i => $example) {
        try {
            $r = runStatement($pdo, rtrim(trim($example['sql']), ';'), CHECK_MAX_ROWS);
            printf("  example %d %-45s rows=%d%s\n", $i + 1, mb_strimwidth($example['title'], 0, 45, '…'), count($r['rows']), $r['truncated'] ? ' TRUNCATED' : '');
        } catch (PDOException $e) {
            $failures++;
            printf("  example %d %-45s FAIL %s\n", $i + 1, $example['title'], mysqlMessage($e));
        }
    }
    foreach ($chapter['exercises'] as $ex) {
        $count++;
        $label = sprintf("  %s.%-2d %-45s", $chapter['number'], $ex['number'], mb_strimwidth($ex['title'], 0, 45, '…'));
        $problems = [];
        if (($ex['type'] ?? 'sql') === 'erd') {
            // A design exercise: the model design must be readable and accepted by its own check.
            $check = checkErdExercise($ex, $ex['solution']);
            if ($check['status'] !== 'correct') {
                $failures++;
                echo $label, " FAIL model design: ", $check['message'], "\n";
            } else {
                echo $label, " design, tables=", count($check['design']['tables']), "\n";
            }
            continue;
        }
        try {
            $r = runStatement($pdo, $ex['solution'], CHECK_MAX_ROWS);
            if (count($r['rows']) === 0) {
                $problems[] = 'solution returns 0 rows';
            }
            if ($r['truncated']) {
                $problems[] = 'solution returns more than ' . CHECK_MAX_ROWS . ' rows';
            }
            if ($ex['ordered'] && !preg_match('/\bORDER\s+BY\b/i', $ex['solution'])) {
                $problems[] = 'ordered exercise without ORDER BY in the solution';
            }
            $check = checkExercise($pdo, $ex, $ex['solution']);
            if ($check['status'] !== 'correct') {
                $problems[] = 'checker rejects its own solution: ' . $check['message'];
            }
            $rows = count($r['rows']);
        } catch (PDOException $e) {
            $problems[] = 'MySQL: ' . mysqlMessage($e);
            $rows = 0;
        }
        if ($problems) {
            $failures++;
            echo $label, " FAIL ", implode('; ', $problems), "\n";
        } else {
            echo $label, " rows=", $rows, "\n";
        }
    }
}
echo "\n$count exercises, $failures failure(s)\n";
exit($failures === 0 ? 0 : 1);
