<?php
/**
 * Northwind SQL course: shared code.
 *
 * Included by index.php (course home), chapter.php, exercise.php,
 * playground.php and tools/check-solutions.php. No dependencies.
 *
 * Configuration: a .env file next to this script (see .env.example), or the
 * same names as environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS). A value in .env always wins over an environment variable.
 */

declare(strict_types=1);

const MAX_ROWS = 500;          // rows shown per result set in the playground / "Run only"
const CHECK_MAX_ROWS = 2000;   // rows compared when checking an exercise
const QUERY_TIMEOUT_MS = 5000; // SELECTs slower than this are killed
const SCHEMA_CACHE_TTL = 600;  // seconds the table list is cached (APCu or temp file)

// Only reading statements are allowed. The DB user on shared hosting has full
// rights, so this allowlist (plus SET SESSION TRANSACTION READ ONLY) is what
// keeps 30 students from changing each other's data.
const ALLOWED_STATEMENTS = ['SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

/** Minimal .env reader: KEY=VALUE lines, # comments, optional quotes. */
function loadDotEnv(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }
        $vars[trim($key)] = $value;
    }
    return $vars;
}

function loadConfig(): array
{
    $dotenv = loadDotEnv(__DIR__ . '/.env');
    $setting = fn(string $key, string $default) => ($dotenv[$key] ?? '') ?: (getenv($key) ?: $default);
    return [
        'DB_HOST' => $setting('DB_HOST', '127.0.0.1'),
        'DB_PORT' => $setting('DB_PORT', '3306'),
        'DB_NAME' => $setting('DB_NAME', 'northwind'),
        'DB_USER' => $setting('DB_USER', 'northwind_ro'),
        'DB_PASS' => $setting('DB_PASS', 'northwind'),
    ];
}

$config = loadConfig();

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function connect(array $c): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['DB_HOST'], $c['DB_PORT'], $c['DB_NAME']);
    $pdo = new PDO($dsn, $c['DB_USER'], $c['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
        // Unbuffered: rows stream from MySQL, PHP never holds more than the row cap.
        // A huge accidental cross join then costs time (capped by MAX_EXECUTION_TIME), not memory.
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    $pdo->exec('SET SESSION MAX_EXECUTION_TIME=' . QUERY_TIMEOUT_MS);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    return $pdo;
}

/**
 * Split a script into statements on ';', ignoring semicolons inside
 * quotes, backticks and comments. Returns non-empty statements.
 */
function splitStatements(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $i = 0;
    while ($i < $len) {
        $ch = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        // Line comments: -- (followed by space) or #
        if (($ch === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? "\n")) || $ch === '#') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $len : $end + 1;
            $buf .= "\n";
            continue;
        }
        // Block comment
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 2;
            $buf .= ' ';
            continue;
        }
        // Quoted strings / identifiers
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $q = $ch;
            $buf .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                $buf .= $c;
                if ($c === '\\' && $q !== '`') {     // backslash escape
                    $buf .= $sql[$i + 1] ?? '';
                    $i += 2;
                    continue;
                }
                $i++;
                if ($c === $q) {
                    if (($sql[$i] ?? '') === $q) {   // doubled quote
                        $buf .= $q;
                        $i++;
                        continue;
                    }
                    break;
                }
            }
            continue;
        }
        if ($ch === ';') {
            $out[] = trim($buf);
            $buf = '';
            $i++;
            continue;
        }
        $buf .= $ch;
        $i++;
    }
    $out[] = trim($buf);
    return array_values(array_filter($out, fn($s) => $s !== ''));
}

/**
 * Clean up text that was pasted from Word, PDF, slides or chat: line endings,
 * non-breaking and other Unicode spaces, zero-width characters, smart quotes
 * and dashes. None of these are valid in SQL, so replacing them is safe.
 * The editor does the same in the browser (assets/app.js); this is the
 * authoritative copy for the plain-textarea fallback and the ?sql= prefill.
 */
function normalizeSql(string $sql): string
{
    static $map = null;
    if ($map === null) {
        $map = [
            // BOM, zero-width characters, soft hyphen: remove
            "\u{FEFF}" => '', "\u{200B}" => '', "\u{200C}" => '', "\u{200D}" => '',
            "\u{2060}" => '', "\u{00AD}" => '',
            // Unicode spaces: ASCII space
            "\u{00A0}" => ' ', "\u{1680}" => ' ', "\u{202F}" => ' ', "\u{205F}" => ' ', "\u{3000}" => ' ',
            // Smart single quotes
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{201B}" => "'", "\u{2032}" => "'",
            // Smart double quotes
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{201F}" => '"', "\u{2033}" => '"',
            // Em dash (Word turns "--" into one) and en dash
            "\u{2014}" => '--', "\u{2013}" => '-',
        ];
        for ($i = 0x80; $i <= 0x8A; $i++) {          // U+2000 .. U+200A
            $map["\xE2\x80" . chr($i)] = ' ';
        }
    }
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    return strtr($sql, $map);
}

/** True when the statement starts with one of ALLOWED_STATEMENTS (after optional "(" ). */
function isAllowedStatement(string $sql): bool
{
    if (!preg_match('/^[\s(]*([a-z]+)/i', $sql, $m)) {
        return false;
    }
    return in_array(strtoupper($m[1]), ALLOWED_STATEMENTS, true);
}

/** MySQL's own message, without the PDO prefix "SQLSTATE[42000]: ...". */
function mysqlMessage(PDOException $e): string
{
    return preg_replace('/^SQLSTATE\[\w+\]:?\s*(?:[^:]+:\s*\d+\s*)?/', '', $e->getMessage());
}

/**
 * Run one statement.
 * Returns ['columns'=>[], 'rows'=>[], 'affected'=>int, 'truncated'=>bool, 'ms'=>float].
 */
function runStatement(PDO $pdo, string $sql, int $maxRows = MAX_ROWS): array
{
    global $config;
    $t0 = microtime(true);
    $stmt = $pdo->query($sql);
    $result = ['columns' => [], 'rows' => [], 'affected' => 0, 'truncated' => false, 'ms' => 0.0];

    if ($stmt->columnCount() > 0) {
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $result['columns'][] = $stmt->getColumnMeta($i)['name'] ?? "col$i";
        }
        while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
            if (count($result['rows']) >= $maxRows) {
                $result['truncated'] = true;
                break;
            }
            $result['rows'][] = $row;
        }
        if ($result['truncated']) {
            // Stop MySQL from producing the rest of the result set, then drop the cursor.
            killRunningQuery($config, $pdo);
        }
    } else {
        $result['affected'] = $stmt->rowCount();
    }
    $stmt->closeCursor();
    $result['ms'] = (microtime(true) - $t0) * 1000;
    return $result;
}

/** Kill the statement still running on $pdo, via a second connection. */
function killRunningQuery(array $config, PDO $pdo): void
{
    $id = connectionIds()[spl_object_id($pdo)] ?? 0;
    if ($id === 0) {
        return; // id unknown (never remembered): closeCursor() drains the rest, bounded by MAX_EXECUTION_TIME
    }
    try {
        $killer = connect($config);
        $killer->exec('KILL QUERY ' . $id);
    } catch (PDOException $e) {
        // Not fatal, same fallback as above.
    }
}

/**
 * Remember the MySQL connection id of $pdo so killRunningQuery() can use it.
 * Must be called before a query is running (CONNECTION_ID() cannot be asked
 * while an unbuffered result set is open).
 */
function rememberConnectionId(PDO $pdo): void
{
    $ids = &connectionIds();
    $ids[spl_object_id($pdo)] = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
}

function &connectionIds(): array
{
    static $ids = [];
    return $ids;
}

// ---------------------------------------------------------------------------
// Schema (table list for the sidebar and autocomplete)
// ---------------------------------------------------------------------------

/** Tables with their columns (views are queryable but not listed). */
function loadSchema(PDO $pdo, string $db): array
{
    $sql = 'SELECT t.TABLE_NAME, t.TABLE_TYPE, c.COLUMN_NAME, c.COLUMN_TYPE
            FROM information_schema.TABLES t
            JOIN information_schema.COLUMNS c
              ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
            WHERE t.TABLE_SCHEMA = ? AND t.TABLE_TYPE = \'BASE TABLE\'
            ORDER BY t.TABLE_TYPE, t.TABLE_NAME, c.ORDINAL_POSITION';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db]);
    $schema = ['tables' => [], 'views' => []];
    foreach ($stmt as $r) {
        $kind = $r['TABLE_TYPE'] === 'VIEW' ? 'views' : 'tables';
        $schema[$kind][$r['TABLE_NAME']][] = [$r['COLUMN_NAME'], $r['COLUMN_TYPE']];
    }
    return $schema;
}

/**
 * Schema cache. The database is read-only, so the table list never changes;
 * caching it saves one information_schema join (the slowest part of a page
 * load) per request. APCu when available, otherwise a JSON file in the temp dir.
 */
function schemaCacheKey(string $db): string
{
    return 'northwind-schema-' . md5($db);
}

function readSchemaCache(string $db): ?array
{
    $key = schemaCacheKey($db);
    if (function_exists('apcu_fetch')) {
        $hit = apcu_fetch($key, $ok);
        if ($ok && is_array($hit) && ($hit['tables'] ?? []) !== []) {
            return $hit;
        }
    }
    $file = sys_get_temp_dir() . '/' . $key . '.json';
    if (is_readable($file) && filemtime($file) > time() - SCHEMA_CACHE_TTL) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && ($cached['tables'] ?? []) !== []) {
            return ['tables' => $cached['tables'], 'views' => $cached['views'] ?? []];
        }
    }
    return null;
}

function writeSchemaCache(string $db, array $schema): void
{
    if (($schema['tables'] ?? []) === []) {
        return; // never cache an empty list (wrong database, missing rights)
    }
    $key = schemaCacheKey($db);
    if (function_exists('apcu_store')) {
        apcu_store($key, $schema, SCHEMA_CACHE_TTL);
    }
    @file_put_contents(sys_get_temp_dir() . '/' . $key . '.json', json_encode($schema), LOCK_EX);
}

/**
 * Schema from the cache, or from MySQL (connecting only when needed).
 * $pdo is filled in when a connection was opened; a connect error is returned
 * as string so callers can show it where it fits.
 */
function schemaOrConnect(array $config, bool $needConnection, ?PDO &$pdo, ?string &$connectError): array
{
    $schema = readSchemaCache($config['DB_NAME']);
    $connectError = null;
    try {
        if ($schema === null || $needConnection) {
            $pdo = connect($config);
            if ($needConnection) {
                rememberConnectionId($pdo);
            }
            if ($schema === null) {
                $schema = loadSchema($pdo, $config['DB_NAME']);
                writeSchemaCache($config['DB_NAME'], $schema);
            }
        }
    } catch (PDOException $e) {
        $connectError = $e->getMessage();
        $pdo = null;
    }
    return $schema ?? ['tables' => [], 'views' => []];
}

/** Table => [column, ...] map for the CodeMirror autocomplete. */
function hintTables(array $schema): array
{
    $hint = [];
    foreach ($schema['tables'] as $name => $cols) {
        $hint[$name] = array_column($cols, 0);
    }
    return $hint;
}

// ---------------------------------------------------------------------------
// Rendering results
// ---------------------------------------------------------------------------

function formatCell($v): string
{
    if ($v === null) {
        return '<td class="null">NULL</td>';
    }
    if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
        return '<td class="null">(binary, ' . strlen($v) . ' bytes)</td>';
    }
    $s = (string) $v;
    if (mb_strlen($s) > 200) {
        $s = mb_substr($s, 0, 200) . '…';
    }
    $cls = is_int($v) || is_float($v) || (is_string($v) && is_numeric($s)) ? ' class="num"' : '';
    return "<td$cls>" . h($s) . '</td>';
}

/** The table + row count notice for one statement result. */
function renderTable(array $result, int $cap = MAX_ROWS): string
{
    if (!$result['columns']) {
        return '<div class="notice">OK · ' . (int) $result['affected'] . ' rows affected</div>';
    }
    $out = '<div class="scroll"><table><thead><tr>';
    foreach ($result['columns'] as $c) {
        $out .= '<th>' . h($c) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    foreach ($result['rows'] as $row) {
        $out .= '<tr>';
        foreach ($row as $v) {
            $out .= formatCell($v);
        }
        $out .= '</tr>';
    }
    $out .= '</tbody></table></div>';
    $n = count($result['rows']);
    $out .= '<div class="notice">' . $n . ' row' . ($n === 1 ? '' : 's');
    if ($result['truncated']) {
        $out .= ' · only the first ' . $cap . ' rows are shown, add <code>LIMIT</code> to narrow it down';
    }
    $out .= '</div>';
    return $out;
}

/** The results block under the editor (playground / "Run only"). Several statements become tabs. */
function renderResults(array $results, ?string $connectError): string
{
    $out = '';
    if ($connectError) {
        $out .= '<div class="result"><div class="error">Cannot connect to MySQL: ' . h($connectError)
            . "\n\nCheck DB_HOST / DB_PORT / DB_USER / DB_PASS in .env (or the environment variables).</div></div>";
    }
    if (!$results) {
        return $out;
    }
    $panels = [];
    $labels = [];
    $active = 0;
    foreach ($results as $i => $r) {
        $head = '';
        if ($r['error'] !== null) {
            $body = '<div class="error">' . h($r['error']) . '</div>';
            $labels[] = ['Query ' . ($i + 1), 'error', true];
            $active = $i;                       // execution stopped here: show it
        } else {
            $n = count($r['result']['rows']);
            $body = renderTable($r['result']);
            $head = '<div class="head"><span>' . number_format($r['result']['ms'], 1) . ' ms</span></div>';
            $labels[] = ['Query ' . ($i + 1), $n . ' row' . ($n === 1 ? '' : 's') . ($r['result']['truncated'] ? '+' : ''), false];
        }
        $panels[] = $head . $body;
    }
    if (count($panels) === 1) {
        return $out . '<div class="result">' . $panels[0] . '</div>';
    }
    $out .= '<div class="result tabbed"><div class="tabs" role="tablist">';
    foreach ($labels as $i => [$label, $badge, $isError]) {
        $out .= '<button type="button" role="tab" class="tab' . ($i === $active ? ' active' : '') . ($isError ? ' err' : '')
            . '" data-tab="' . $i . '" aria-selected="' . ($i === $active ? 'true' : 'false') . '">'
            . h($label) . ' <span class="badge">' . h($badge) . '</span></button>';
    }
    $out .= '</div>';
    foreach ($panels as $i => $panel) {
        $out .= '<div class="panel" role="tabpanel" data-panel="' . $i . '"' . ($i === $active ? '' : ' hidden') . '>' . $panel . '</div>';
    }
    return $out . '</div>';
}

/**
 * Run a script the playground way: statement by statement, allowlist, stop at
 * the first error. Returns the list used by renderResults().
 */
function runScript(PDO $pdo, string $sqlInput): array
{
    $results = [];
    foreach (splitStatements($sqlInput) as $statement) {
        if (!isAllowedStatement($statement)) {
            $results[] = ['sql' => $statement, 'result' => null,
                'error' => 'Only ' . implode(', ', ALLOWED_STATEMENTS) . ' statements are allowed in this playground (the database is read-only).'];
            break;
        }
        try {
            $results[] = ['sql' => $statement, 'result' => runStatement($pdo, $statement), 'error' => null];
        } catch (PDOException $e) {
            $results[] = ['sql' => $statement, 'result' => null, 'error' => mysqlMessage($e)];
            break; // stop at first error, like Workbench does
        }
    }
    return $results;
}

// ---------------------------------------------------------------------------
// Course content
// ---------------------------------------------------------------------------

/** All chapters, in file order, with 'number' (1-based) and 'slug' added. */
function loadChapters(): array
{
    static $chapters = null;
    if ($chapters !== null) {
        return $chapters;
    }
    $chapters = [];
    $files = glob(__DIR__ . '/chapters/*.php') ?: [];
    sort($files);
    foreach ($files as $i => $file) {
        $chapter = require $file;
        $chapter['number'] = $i + 1;
        $chapter['slug'] = preg_replace('/^\d+-|\.php$/', '', basename($file));
        $chapter['examples'] = $chapter['examples'] ?? [];
        $chapter['exercises'] = $chapter['exercises'] ?? [];
        foreach ($chapter['exercises'] as $j => &$ex) {
            $ex['number'] = $j + 1;
            $ex['ordered'] = $ex['ordered'] ?? false;
            $ex['columns'] = $ex['columns'] ?? false;
        }
        unset($ex);
        $chapters[] = $chapter;
    }
    return $chapters;
}

function getChapter(int $c): ?array
{
    return loadChapters()[$c - 1] ?? null;
}

function getExercise(int $c, int $e): ?array
{
    return getChapter($c)['exercises'][$e - 1] ?? null;
}

// ---------------------------------------------------------------------------
// Exercise checking
// ---------------------------------------------------------------------------

/** One cell as a comparable string: NULL marker, numbers without float noise, text as is. */
function normalizeCell($v): string
{
    if ($v === null) {
        return "\0NULL";
    }
    if (is_int($v)) {
        return (string) $v;
    }
    if (is_float($v) || (is_string($v) && is_numeric($v))) {
        $s = number_format((float) $v, 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }
    return (string) $v;
}

function normalizeRows(array $rows): array
{
    return array_map(fn(array $row) => implode("\x1f", array_map('normalizeCell', $row)), $rows);
}

/**
 * Compare the student's query with the reference solution.
 * Returns ['status' => correct|wrong|error|invalid, 'message' => string,
 *          'student' => ?result, 'expected' => ?result]
 */
function checkExercise(PDO $pdo, array $exercise, string $studentSql): array
{
    $out = ['status' => 'invalid', 'message' => '', 'student' => null, 'expected' => null];

    $statements = splitStatements($studentSql);
    if (count($statements) === 0) {
        $out['message'] = 'Type a query first.';
        return $out;
    }
    if (count($statements) > 1) {
        $out['message'] = 'Submit exactly one statement (you have ' . count($statements) . '). Use the playground to run several.';
        return $out;
    }
    $sql = $statements[0];
    if (!isAllowedStatement($sql)) {
        $out['message'] = 'Only ' . implode(', ', ALLOWED_STATEMENTS) . ' statements are allowed (the database is read-only).';
        return $out;
    }

    try {
        $expected = runStatement($pdo, $exercise['solution'], CHECK_MAX_ROWS);
    } catch (PDOException $e) {
        $out['status'] = 'error';
        $out['message'] = 'The reference solution of this exercise failed, tell your teacher: ' . mysqlMessage($e);
        return $out;
    }
    $out['expected'] = $expected;

    try {
        $student = runStatement($pdo, $sql, CHECK_MAX_ROWS);
    } catch (PDOException $e) {
        $out['status'] = 'error';
        $out['message'] = mysqlMessage($e);
        return $out;
    }
    $out['student'] = $student;
    $out['status'] = 'wrong';

    if ($student['truncated']) {
        $out['message'] = 'Your result has more than ' . CHECK_MAX_ROWS . ' rows. Add a filter.';
        return $out;
    }
    if (!$student['columns']) {
        $out['message'] = 'Your statement returned no result set.';
        return $out;
    }
    $ec = count($expected['columns']);
    $sc = count($student['columns']);
    if ($ec !== $sc) {
        $out['message'] = "Expected $ec column" . ($ec === 1 ? '' : 's') . ", you have $sc.";
        return $out;
    }
    if ($exercise['columns']) {
        $en = array_map('strtolower', $expected['columns']);
        $sn = array_map('strtolower', $student['columns']);
        if ($en !== $sn) {
            $out['message'] = 'Column names differ. Expected: ' . implode(', ', $expected['columns']) . '. You have: ' . implode(', ', $student['columns']) . '.';
            return $out;
        }
    }
    $er = count($expected['rows']);
    $sr = count($student['rows']);
    if ($er !== $sr) {
        $out['message'] = "Expected $er row" . ($er === 1 ? '' : 's') . ", you have $sr.";
        return $out;
    }
    $eRows = normalizeRows($expected['rows']);
    $sRows = normalizeRows($student['rows']);
    if ($exercise['ordered']) {
        foreach ($eRows as $i => $row) {
            if ($sRows[$i] !== $row) {
                $sortedE = $eRows;
                $sortedS = $sRows;
                sort($sortedE);
                sort($sortedS);
                $out['message'] = $sortedE === $sortedS
                    ? 'The rows are right but the order is not. Row ' . ($i + 1) . ' differs, check your ORDER BY.'
                    : 'Row ' . ($i + 1) . ' differs.';
                return $out;
            }
        }
    } else {
        sort($eRows);
        sort($sRows);
        if ($eRows !== $sRows) {
            $out['message'] = 'Same number of rows, but the values differ. Compare your result with the expected result below.';
            return $out;
        }
    }
    $out['status'] = 'correct';
    $out['message'] = 'Correct!';
    return $out;
}

/** The verdict block for exercise.php (also sent alone to fetch() requests). */
function renderCheck(array $check): string
{
    $labels = ['correct' => 'Correct', 'wrong' => 'Not yet', 'error' => 'Error', 'invalid' => 'Cannot check'];
    $out = '<div class="verdict ' . h($check['status']) . '"><strong>' . $labels[$check['status']] . '</strong> ' . h($check['message']) . '</div>';
    if ($check['student'] !== null) {
        $out .= '<div class="result"><div class="head"><span>Your result</span><span>'
            . number_format($check['student']['ms'], 1) . ' ms</span></div>' . renderTable($check['student'], CHECK_MAX_ROWS) . '</div>';
    }
    if ($check['status'] === 'wrong' && $check['expected'] !== null) {
        $out .= '<div class="result"><div class="head"><span>Expected result</span></div>' . renderTable($check['expected'], CHECK_MAX_ROWS) . '</div>';
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Layout
// ---------------------------------------------------------------------------

/** True when the page's own JavaScript posted and only wants a fragment back. */
function isFetchRequest(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function sendFragment(string $html): never
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $html;
    exit;
}

function notFound(string $what): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "$what not found.";
    exit;
}

/** Sidebar block with every table and its columns. */
function renderSchemaSidebar(array $schema): string
{
    $out = '<h2>Tables (' . count($schema['tables']) . ')</h2>';
    foreach ($schema['tables'] as $name => $cols) {
        $out .= '<details><summary>' . h($name) . '</summary><ul>';
        foreach ($cols as [$col, $type]) {
            $out .= '<li>' . h($col) . ' <span>' . h($type) . '</span></li>';
        }
        $out .= '</ul></details>';
    }
    return $out;
}

/**
 * <head> + header + opening of the layout.
 * $active: 'home' | 'chapters' | 'playground'. With $sidebar the two-column
 * layout is used and the given HTML goes into <aside>.
 */
function pageStart(string $title, string $active, ?string $sidebar = null, bool $editor = false): void
{
    global $config;
    $nav = [
        'home' => ['index.php', 'Course'],
        'playground' => ['playground.php', 'Playground'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · Northwind SQL</title>
<?php if ($editor): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
<?php endif; ?>
<link rel="stylesheet" href="assets/app.css?v=<?= assetVersion('app.css') ?>">
</head>
<body>
<header>
  <a class="brand" href="index.php">Northwind SQL</a>
  <nav>
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= $href ?>"<?= $active === $key ? ' class="active"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <span class="muted">MySQL · database <code><?= h($config['DB_NAME']) ?></code> · read-only</span>
</header>
<div class="layout<?= $sidebar === null ? ' single' : '' ?>">
<?php if ($sidebar !== null): ?>
<aside><?= $sidebar ?></aside>
<?php endif; ?>
<main>
    <?php
}

/** Cache buster for assets/*: browsers fetch a new copy after every deploy. */
function assetVersion(string $file): string
{
    return (string) (@filemtime(__DIR__ . '/assets/' . $file) ?: 1);
}

/** Close the layout, load scripts. $hintTables feeds the autocomplete; null = no editor on this page. */
function pageEnd(?array $hintTables = null, string $primaryAction = 'run'): void
{
    ?>
</main>
</div>
<?php if ($hintTables !== null): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/sql-hint.min.js"></script>
<script src="assets/sqlformat.js?v=<?= assetVersion('sqlformat.js') ?>"></script>
<script>window.NW = { tables: <?= json_encode($hintTables, JSON_UNESCAPED_SLASHES) ?>, primaryAction: <?= json_encode($primaryAction) ?> };</script>
<script src="assets/app.js?v=<?= assetVersion('app.js') ?>"></script>
<?php endif; ?>
</body>
</html>
<?php
}
