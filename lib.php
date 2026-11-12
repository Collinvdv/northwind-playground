<?php
/**
 * Northwind SQL course: shared code.
 *
 * Included by index.php (course home), chapter.php (one lesson), exercise.php,
 * playground.php (also behind exam.php) and tools/check-solutions.php.
 * No dependencies.
 *
 * Configuration: a .env file next to this script (see .env.example), or the
 * same names as environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS). A value in .env always wins over an environment variable.
 */

declare(strict_types=1);

const MAX_ROWS = 500;          // rows shown per result set in the playground / "Run only"
const CHECK_MAX_ROWS = 2000;   // rows compared when checking an exercise
const QUERY_TIMEOUT_MS = 3000; // SELECTs slower than this are killed
const SCHEMA_CACHE_TTL = 600;  // seconds the table list is cached (APCu or temp file)
const EXPECTED_CACHE_TTL = 86400; // seconds a reference solution's result is cached (DB is read-only)

// Admission gate: at most DB_SLOTS requests talk to MySQL at the same time
// (DB_SLOTS in .env overrides the default). A request that finds no free slot
// within SLOT_WAIT_MS answers 503 + Retry-After and the browser retries.
// This bounds MySQL connections and CPU no matter how many students are online.
const DB_SLOTS_DEFAULT = 10;
const SLOT_WAIT_MS = 2000;
const SLOT_RETRY_AFTER = 2;    // seconds, sent in the Retry-After header

// Only reading statements are allowed. The DB user on shared hosting has full
// rights, so this allowlist (plus SET SESSION TRANSACTION READ ONLY) is what
// keeps 150 students from changing each other's data.
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
        'DB_SLOTS' => max(1, (int) $setting('DB_SLOTS', (string) DB_SLOTS_DEFAULT)),
        'EXAM_ADMIN_PASS' => $setting('EXAM_ADMIN_PASS', ''),   // empty: exam-admin.php is off
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
    // Strict GROUP BY: every selected column must be grouped or aggregated,
    // as standard SQL demands. Shared hosts often leave this mode off.
    $pdo->exec("SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'ONLY_FULL_GROUP_BY')");
    return $pdo;
}

// ---------------------------------------------------------------------------
// Admission gate (a queue without a queue server)
// ---------------------------------------------------------------------------

/**
 * Take one of $slots slots, waiting at most $waitMs. Slots are flock()ed files
 * in the temp dir, so they are shared by every PHP process on the host and
 * are released automatically when a process dies. Returns the slot handle,
 * null when every slot stayed busy, or true when the gate cannot work (no
 * writable temp dir): then the app runs ungated rather than not at all.
 */
function acquireDbSlot(int $slots, int $waitMs = SLOT_WAIT_MS)
{
    $dir = sys_get_temp_dir();
    $deadline = microtime(true) + $waitMs / 1000;
    $opened = 0;
    do {
        for ($n = 0; $n < $slots; $n++) {
            $fh = @fopen($dir . '/northwind-slot-' . $n . '.lock', 'c');
            if ($fh === false) {
                continue;
            }
            $opened++;
            if (flock($fh, LOCK_EX | LOCK_NB)) {
                return $fh;
            }
            fclose($fh);
        }
        if ($opened === 0) {
            return true; // temp dir not writable: fail open
        }
        usleep(100_000);
    } while (microtime(true) < $deadline);
    return null;
}

function releaseDbSlot($slot): void
{
    if (is_resource($slot)) {
        flock($slot, LOCK_UN);
        fclose($slot);
    }
}

/** HTML notice for a request that found no free slot (shown in the results block). */
function busyNotice(): string
{
    $n = (int) $GLOBALS['config']['DB_SLOTS'];
    return '<div class="result"><div class="notice">The server is busy (' . $n . ' quer' . ($n === 1 ? 'y is' : 'ies are')
        . ' running). Wait a few seconds and try again.</div></div>';
}

/**
 * Answer a fetch() request with 503 + Retry-After so assets/app.js retries by
 * itself. Plain form posts (no JavaScript) get a normal page instead: the
 * caller renders busyNotice() in the results block.
 */
function sendBusyIfFetch(): void
{
    if (!isFetchRequest()) {
        return;
    }
    http_response_code(503);
    header('Retry-After: ' . SLOT_RETRY_AFTER);
    sendFragment(busyNotice());
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

/**
 * Tables with their columns (views are queryable but not listed), plus the
 * keys for the sidebar markers and the diagram (erd.php):
 *   'primary' => [table => [column, ...]]
 *   'foreign' => [[table, column, referencedTable, referencedColumn], ...]
 */
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
    $schema = ['tables' => [], 'views' => [], 'primary' => [], 'foreign' => []];
    foreach ($stmt as $r) {
        $kind = $r['TABLE_TYPE'] === 'VIEW' ? 'views' : 'tables';
        $schema[$kind][$r['TABLE_NAME']][] = [$r['COLUMN_NAME'], $r['COLUMN_TYPE']];
    }

    $sql = 'SELECT k.TABLE_NAME, k.COLUMN_NAME, k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE k
            WHERE k.TABLE_SCHEMA = ?
              AND (k.CONSTRAINT_NAME = \'PRIMARY\' OR k.REFERENCED_TABLE_NAME IS NOT NULL)
            ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db]);
    foreach ($stmt as $r) {
        if (!isset($schema['tables'][$r['TABLE_NAME']])) {
            continue;
        }
        if ($r['CONSTRAINT_NAME'] === 'PRIMARY') {
            $schema['primary'][$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
        } else {
            $schema['foreign'][] = [$r['TABLE_NAME'], $r['COLUMN_NAME'], $r['REFERENCED_TABLE_NAME'], $r['REFERENCED_COLUMN_NAME']];
        }
    }
    return $schema;
}

/**
 * Small cache: APCu when available, otherwise a file in the temp dir. Values
 * are serialize()d, so result rows keep their PHP types (int/float/string,
 * binary columns included). Used for the table list and for the results of
 * the reference solutions; both never change because the database is read-only.
 */
function cacheGet(string $key, int $ttl): mixed
{
    if (function_exists('apcu_fetch')) {
        $hit = apcu_fetch($key, $ok);
        if ($ok) {
            return $hit;
        }
    }
    $file = sys_get_temp_dir() . '/' . $key . '.cache';
    if (is_readable($file) && filemtime($file) > time() - $ttl) {
        $value = unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if ($value !== false) {
            return $value;
        }
    }
    return null;
}

function cachePut(string $key, mixed $value, int $ttl): void
{
    if (function_exists('apcu_store')) {
        apcu_store($key, $value, $ttl);
    }
    @file_put_contents(sys_get_temp_dir() . '/' . $key . '.cache', serialize($value), LOCK_EX);
}

/** Schema cache: the table list for the sidebar and autocomplete. */
function schemaCacheKey(string $db): string
{
    return 'northwind-schema-' . md5($db);
}

function readSchemaCache(string $db): ?array
{
    $cached = cacheGet(schemaCacheKey($db), SCHEMA_CACHE_TTL);
    // A cache written by an older version has no keys: reload instead of using it.
    if (is_array($cached) && ($cached['tables'] ?? []) !== [] && isset($cached['primary'], $cached['foreign'])) {
        return ['tables' => $cached['tables'], 'views' => $cached['views'] ?? [],
            'primary' => $cached['primary'], 'foreign' => $cached['foreign']];
    }
    return null;
}

function writeSchemaCache(string $db, array $schema): void
{
    if (($schema['tables'] ?? []) === []) {
        return; // never cache an empty list (wrong database, missing rights)
    }
    cachePut(schemaCacheKey($db), $schema, SCHEMA_CACHE_TTL);
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
    return $schema ?? ['tables' => [], 'views' => [], 'primary' => [], 'foreign' => []];
}

/** Column => 'pk' | 'fk' per table, for the key markers in the sidebar and the diagram. */
function keyColumns(array $schema): array
{
    $keys = [];
    foreach ($schema['foreign'] ?? [] as [$table, $col]) {
        $keys[$table][$col] = 'fk';
    }
    foreach ($schema['primary'] ?? [] as $table => $cols) {
        foreach ($cols as $col) {
            $keys[$table][$col] = 'pk';   // a column that is both counts as PK
        }
    }
    return $keys;
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

/**
 * The course is a list of chapters (SELECT, ERD, CRUD, ...), each a folder
 * chapters/<n>-<slug>/ holding chapter.php (title, summary) and one file per
 * lesson (01-something.php: theory, examples, exercises).
 *
 * Lessons are also numbered through the whole course, in folder + file order:
 * that 'number' is the c= of chapter.php?c= and exercise.php?c=&e= and the
 * first half of a favourite's id. The first chapter's lessons therefore keep
 * the numbers (and URLs) they had when every lesson was a chapter of its own.
 * On the pages a lesson is shown as 'label' = chapter.lesson ("2.1").
 *
 * Returns [['number', 'slug', 'title', 'summary', 'lessons' => [...]], ...].
 */
function loadCourse(): array
{
    static $course = null;
    if ($course !== null) {
        return $course;
    }
    $course = [];
    $dirs = glob(__DIR__ . '/chapters/*', GLOB_ONLYDIR) ?: [];
    sort($dirs, SORT_NATURAL);
    $n = 0;
    foreach ($dirs as $i => $dir) {
        $chapter = (is_file("$dir/chapter.php") ? require "$dir/chapter.php" : []) + ['title' => basename($dir), 'summary' => ''];
        $chapter['number'] = $i + 1;
        $chapter['slug'] = preg_replace('/^\d+-/', '', basename($dir));
        $chapter['lessons'] = [];
        $files = glob("$dir/[0-9]*.php") ?: [];
        sort($files);
        foreach ($files as $j => $file) {
            $lesson = require $file;
            $lesson['number'] = ++$n;
            $lesson['chapter'] = $chapter['number'];
            $lesson['chapterTitle'] = $chapter['title'];
            $lesson['label'] = $chapter['number'] . '.' . ($j + 1);
            $lesson['slug'] = preg_replace('/^\d+-|\.php$/', '', basename($file));
            $lesson['runnable'] = $lesson['runnable'] ?? true;   // false: examples are not SELECTs (no playground link)
            $lesson['examples'] = $lesson['examples'] ?? [];
            $lesson['exercises'] = $lesson['exercises'] ?? [];
            foreach ($lesson['exercises'] as $k => &$ex) {
                $ex['number'] = $k + 1;
                $ex['type'] = $ex['type'] ?? 'sql';   // 'erd': the student draws a design (checkErdExercise)
                $ex['ordered'] = $ex['ordered'] ?? false;
                $ex['columns'] = $ex['columns'] ?? false;
            }
            unset($ex);
            $chapter['lessons'][] = $lesson;
        }
        $course[] = $chapter;
    }
    return $course;
}

/** Every lesson of the course in order; index + 1 is the lesson's 'number'. */
function loadLessons(): array
{
    return array_merge([], ...array_column(loadCourse(), 'lessons'));
}

function getLesson(int $c): ?array
{
    return loadLessons()[$c - 1] ?? null;
}

function getExercise(int $c, int $e): ?array
{
    return getLesson($c)['exercises'][$e - 1] ?? null;
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
 * Result of the reference solution, cached: the database never changes, so
 * one MySQL query per exercise per day serves every Check of every student.
 * The key contains the solution text, so editing a solution invalidates it.
 */
function expectedResult(PDO $pdo, array $exercise): array
{
    $key = 'northwind-expected-' . md5($exercise['solution']);
    $cached = cacheGet($key, EXPECTED_CACHE_TTL);
    if (is_array($cached) && isset($cached['columns'], $cached['rows'])) {
        return $cached;
    }
    $result = runStatement($pdo, $exercise['solution'], CHECK_MAX_ROWS);
    cachePut($key, $result, EXPECTED_CACHE_TTL);
    return $result;
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
        $expected = expectedResult($pdo, $exercise);
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
            $out['message'] = 'Same number of rows, but the values differ.';
            return $out;
        }
    }
    $out['status'] = 'correct';
    $out['message'] = 'Correct!';
    return $out;
}

/**
 * The verdict block for exercise.php (also sent alone to fetch() requests).
 * The exam page passes $showExpected = false: the expected result is the answer.
 */
function renderCheck(array $check, bool $showExpected = true): string
{
    $labels = ['correct' => 'Correct', 'wrong' => 'Not yet', 'error' => 'Error', 'invalid' => 'Cannot check'];
    $out = '<div class="verdict ' . h($check['status']) . '"><strong>' . $labels[$check['status']] . '</strong> ' . h($check['message']) . '</div>';
    if ($check['student'] !== null) {
        $out .= '<div class="result"><div class="head"><span>Your result</span><span>'
            . number_format($check['student']['ms'], 1) . ' ms</span></div>' . renderTable($check['student'], CHECK_MAX_ROWS) . '</div>';
    }
    if ($showExpected && $check['status'] === 'wrong' && $check['expected'] !== null) {
        $out .= '<div class="result"><div class="head"><span>Expected result</span></div>' . renderTable($check['expected'], CHECK_MAX_ROWS) . '</div>';
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Database diagram (erd.php and the popup on the exam page)
// ---------------------------------------------------------------------------

/**
 * The diagram is inline SVG built from the cached schema (tables, columns,
 * primary and foreign keys), so it always matches what the playground sees.
 * erd.php shows it as a page; the exam page embeds it in a <dialog> (the exam
 * URL is the only one students can open). assets/erd.js adds hover
 * highlighting, "Fit to window", "Save .svg" and the dialog buttons.
 *
 * ERD_LAYOUT is a hand-made layout for Northwind: every foreign key joins two
 * neighbouring columns (or a table with itself), so no line crosses a box.
 * Tables that are not in it are appended in extra columns.
 */

/** Columns of tables, left to right, each top to bottom. */
const ERD_LAYOUT = [
    ['Suppliers', 'Categories'],
    ['Products', 'Shippers'],
    ['OrderDetails', 'Orders'],
    ['Customers', 'Employees'],
    ['CustomerCustomerDemo', 'EmployeeTerritories'],
    ['CustomerDemographics', 'Territories', 'Region'],
];

const ERD_BOX_W = 220;   // width of a table box
const ERD_COL_GAP = 60;  // horizontal space between columns of boxes (the lines live here)
const ERD_ROW_H = 18;    // height of one column row
const ERD_HEAD_H = 26;   // height of the table name bar
const ERD_V_GAP = 36;    // vertical space between boxes in one column
const ERD_PAD = 24;      // margin around the drawing
const ERD_EDITOR_Y_SCALE = 0.62;   // row height here / row height in assets/erd-editor.js
/** Colours of a table's name bar ("Table #green:" in a design); the same list is in assets/erd-editor.js. */
const ERD_COLORS = ['blue' => '#1d4ed8', 'green' => '#15803d', 'orange' => '#c2410c', 'purple' => '#7e22ce', 'red' => '#b91c1c', 'teal' => '#0f766e', 'grey' => '#475569'];

/**
 * Box per table: ['x','y','w','h'], plus the drawing size.
 * $layout replaces ERD_LAYOUT (diagrams in lessons, see lessonHtml()).
 * Returns [boxes, width, height].
 */
function erdBoxes(array $schema, ?array $layout = null, ?array $positions = null): array
{
    // Free placement (a design drawn in the editor): every table has its own spot.
    if ($positions !== null && !array_diff_key($schema['tables'], $positions)) {
        $minX = min(array_column($positions, 0));
        $minY = min(array_column($positions, 1));
        $boxes = [];
        $width = $height = 0;
        foreach ($schema['tables'] as $table => $cols) {
            $h = ERD_HEAD_H + max(1, count($cols)) * ERD_ROW_H + 6;
            // The editor's rows are taller than the rows drawn here: shrink the vertical distances with them.
            $x = ERD_PAD + $positions[$table][0] - $minX;
            $y = ERD_PAD + (int) round(($positions[$table][1] - $minY) * ERD_EDITOR_Y_SCALE);
            $boxes[$table] = ['x' => $x, 'y' => $y, 'w' => ERD_BOX_W, 'h' => $h];
            $width = max($width, $x + ERD_BOX_W);
            $height = max($height, $y + $h);
        }
        return [$boxes, $width + ERD_PAD, $height + ERD_PAD];
    }
    $columns = $layout ?? ERD_LAYOUT;
    $placed = array_merge(...$columns);
    $extra = array_values(array_diff(array_keys($schema['tables']), $placed));
    foreach (array_chunk($extra, 3) as $chunk) {
        $columns[] = $chunk;
    }

    $boxes = [];
    $x = ERD_PAD;
    $maxY = 0;
    foreach ($columns as $column) {
        $y = ERD_PAD;
        $used = false;
        foreach ($column as $table) {
            if (!isset($schema['tables'][$table])) {
                continue;
            }
            $h = ERD_HEAD_H + count($schema['tables'][$table]) * ERD_ROW_H + 6;
            $boxes[$table] = ['x' => $x, 'y' => $y, 'w' => ERD_BOX_W, 'h' => $h];
            $y += $h + ERD_V_GAP;
            $used = true;
        }
        if ($used) {
            $maxY = max($maxY, $y - ERD_V_GAP);
            $x += ERD_BOX_W + ERD_COL_GAP;
        }
    }
    return [$boxes, $x - ERD_COL_GAP + ERD_PAD, $maxY + ERD_PAD];
}

/** Type text shortened so "name  type" fits on one row (approximate glyph widths). */
function erdFitType(string $name, string $type): string
{
    $room = ERD_BOX_W - 26 - 8 - 6 - strlen($name) * 6.6;   // px left for the type at ~5.7 px/char
    $chars = (int) floor($room / 5.7);
    if (strlen($type) <= $chars) {
        return $type;
    }
    return $chars < 4 ? '' : substr($type, 0, $chars - 1) . '…';
}

/** The whole diagram as an <svg> element. $layout: columns of table names (default: ERD_LAYOUT). */
function renderErd(array $schema, ?array $layout = null, ?array $positions = null): string
{
    [$boxes, $width, $height] = erdBoxes($schema, $layout, $positions);
    $keys = keyColumns($schema);

    $rowIndex = [];
    foreach ($schema['tables'] as $table => $cols) {
        foreach ($cols as $i => [$col]) {
            $rowIndex[$table][$col] = $i;
        }
    }
    $rowY = fn(string $table, string $col): float =>
        $boxes[$table]['y'] + ERD_HEAD_H + 3 + ($rowIndex[$table][$col] ?? 0) * ERD_ROW_H + ERD_ROW_H / 2;

    $style = <<<'CSS'
        .erd text { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; fill: #1f2328; }
        .erd .tbl rect.box { fill: #fff; stroke: #c9cfd6; }
        .erd .tbl path.head { fill: #1d4ed8; }
        .erd .tbl text.name { fill: #fff; font: 600 12.5px system-ui, -apple-system, "Segoe UI", sans-serif; }
        .erd .tbl text.type { fill: #6b7280; font-size: 9.5px; }
        .erd .tbl text.tag { font-size: 8.5px; font-weight: 700; }
        .erd .tbl text.tag.pk { fill: #1d4ed8; }
        .erd .tbl text.tag.fk { fill: #6d28d9; }
        .erd .tbl text.key { font-weight: 700; }
        .erd .tbl line.sep { stroke: #eceff3; }
        .erd .rel path, .erd .rel line { stroke: #64748b; stroke-width: 1.4; fill: none; stroke-linecap: round; }
        .erd .rel.hi path, .erd .rel.hi line { stroke: #1d4ed8; stroke-width: 2.4; }
        .erd.hover .rel:not(.hi) { opacity: .2; }
        .erd.hover .tbl:not(.hi) { opacity: .4; }
        CSS;

    $out = '<svg class="erd" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height
        . '" role="img" aria-label="Entity-relationship diagram of the database">';
    $out .= '<style>' . $style . '</style>';
    $out .= '<rect width="100%" height="100%" fill="#fff"/>';

    // Relationships first, so the boxes paint over them.
    // Many-to-many relationships without a link table: name bar to name bar, a crow's foot at both ends.
    foreach ($schema['many'] ?? [] as [$table, $refTable]) {
        if (!isset($boxes[$table], $boxes[$refTable])) {
            continue;
        }
        $a = $boxes[$table];
        $b = $boxes[$refTable];
        [$da, $db] = $a['x'] < $b['x'] ? [1, -1] : ($a['x'] > $b['x'] ? [-1, 1] : [1, 1]);
        $ax = $a['x'] + ($da > 0 ? $a['w'] : 0);
        $ay = $a['y'] + ERD_HEAD_H / 2;
        $bx = $b['x'] + ($db > 0 ? $b['w'] : 0);
        $by = $b['y'] + ERD_HEAD_H / 2;
        $x1 = $ax + $da * 16;
        $x2 = $bx + $db * 16;
        $k = max(50, abs($x2 - $x1) / 2);
        $out .= '<g class="rel" data-from="' . h($table) . '" data-to="' . h($refTable) . '">';
        $out .= '<title>' . h("$table and $refTable: many-to-many") . '</title>';
        $out .= sprintf('<path d="M%.1f %.1f C%.1f %.1f %.1f %.1f %.1f %.1f"/>', $x1, $ay, $x1 + $da * $k, $ay, $x2 + $db * $k, $by, $x2, $by);
        foreach ([-7, 0, 7] as $dy) {
            $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $x1, $ay, $ax, $ay + $dy);
            $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $x2, $by, $bx, $by + $dy);
        }
        $out .= '</g>';
    }
    foreach ($schema['foreign'] ?? [] as $foreign) {
        [$table, $col, $refTable, $refCol] = $foreign;
        $one = $foreign[4] ?? false;     // a UNIQUE foreign key: one-to-one
        if (!isset($boxes[$table], $boxes[$refTable])) {
            continue;
        }
        $a = $boxes[$table];
        $b = $boxes[$refTable];
        if ($a['x'] < $b['x']) {
            [$da, $db] = [1, -1];        // child left of parent: leave right, enter left
        } elseif ($a['x'] > $b['x']) {
            [$da, $db] = [-1, 1];
        } else {
            [$da, $db] = [1, 1];         // same column or same table: loop out on the right
        }
        $ax = $a['x'] + ($da > 0 ? $a['w'] : 0);
        $ay = $rowY($table, $col);
        $bx = $b['x'] + ($db > 0 ? $b['w'] : 0);
        $by = $rowY($refTable, $refCol);
        $x1 = $ax + $da * 16;            // where the curve starts (after the crow's foot)
        $x2 = $bx + $db * 12;            // where it ends (before the "one" bar)
        $k = max(50, abs($x2 - $x1) / 2);
        $path = sprintf('M%.1f %.1f C%.1f %.1f %.1f %.1f %.1f %.1f',
            $x1, $ay, $x1 + $da * $k, $ay, $x2 + $db * $k, $by, $x2, $by);

        $out .= '<g class="rel" data-from="' . h($table) . '" data-to="' . h($refTable) . '">';
        $out .= '<title>' . h("$table.$col → $refTable.$refCol  (" . ($one ? 'one' : 'many') . " $table row" . ($one ? '' : 's') . " per $refTable row)") . '</title>';
        $out .= '<path d="' . $path . '"/>';
        if ($one) {
            // One-to-one: a bar at the child end too.
            $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $x1, $ay, $ax, $ay);
            $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $ax + $da * 7, $ay - 7, $ax + $da * 7, $ay + 7);
        } else {
            // Crow's foot at the child ("many") end.
            foreach ([-7, 0, 7] as $dy) {
                $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $x1, $ay, $ax, $ay + $dy);
            }
        }
        // Stub and bar at the parent ("one") end.
        $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $x2, $by, $bx, $by);
        $out .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $bx + $db * 7, $by - 7, $bx + $db * 7, $by + 7);
        $out .= '</g>';
    }

    foreach ($boxes as $table => $b) {
        $w = $b['w'];
        $out .= '<g class="tbl" data-table="' . h($table) . '" transform="translate(' . $b['x'] . ' ' . $b['y'] . ')">';
        $out .= '<rect class="box" width="' . $w . '" height="' . $b['h'] . '" rx="6"/>';
        $fill = ERD_COLORS[$schema['colors'][$table] ?? ''] ?? null;
        $out .= '<path class="head"' . ($fill ? ' style="fill:' . $fill . '"' : '') . ' d="M0 6 Q0 0 6 0 H' . ($w - 6) . ' Q' . $w . ' 0 ' . $w . ' 6 V' . ERD_HEAD_H . ' H0 Z"/>';
        $out .= '<text class="name" x="' . ($w / 2) . '" y="17.5" text-anchor="middle">' . h($table) . '</text>';
        foreach ($schema['tables'][$table] as $i => [$col, $type]) {
            $top = ERD_HEAD_H + 3 + $i * ERD_ROW_H;
            $base = $top + 13;
            $key = $keys[$table][$col] ?? '';
            if ($i > 0) {
                $out .= '<line class="sep" x1="8" y1="' . $top . '" x2="' . ($w - 8) . '" y2="' . $top . '"/>';
            }
            if ($key !== '') {
                $out .= '<text class="tag ' . $key . '" x="7" y="' . $base . '">' . strtoupper($key) . '</text>';
            }
            $out .= '<text class="' . ($key !== '' ? 'key' : '') . '" x="26" y="' . $base . '">' . h($col) . '</text>';
            $short = erdFitType($col, $type);
            if ($short !== '') {
                $out .= '<text class="type" x="' . ($w - 8) . '" y="' . $base . '" text-anchor="end">'
                    . ($short !== $type ? '<title>' . h($type) . '</title>' : '') . h($short) . '</text>';
            }
        }
        $out .= '</g>';
    }
    return $out . '</svg>';
}

/** One-paragraph explanation of the notation, with the two line ends drawn inline. */
function renderErdLegend(array $schema, string $dbName): string
{
    $foot = '<svg class="legend" width="26" height="16" viewBox="0 0 26 16"><line x1="2" y1="8" x2="24" y2="8"/><line x1="12" y1="8" x2="24" y2="1"/><line x1="12" y1="8" x2="24" y2="15"/></svg>';
    $bar = '<svg class="legend" width="26" height="16" viewBox="0 0 26 16"><line x1="2" y1="8" x2="24" y2="8"/><line x1="6" y1="1" x2="6" y2="15"/></svg>';
    return count($schema['tables']) . ' tables and ' . count($schema['foreign'] ?? []) . ' relationships of the <code>' . h($dbName) . '</code> database. '
        . 'A line joins a foreign key (<em class="tag fk">FK</em>) to the primary key (<em class="tag pk">PK</em>) it points to: '
        . 'the crow\'s foot ' . $foot . ' is the <strong>many</strong> side, the bar ' . $bar . ' the <strong>one</strong> side. '
        . 'Those are the columns to join on. Hover a table to highlight its relationships.';
}

/** "Fit to window" + "Save .svg" controls; assets/erd.js wires them to the nearest .erd-wrap. */
function renderErdTools(bool $fit): string
{
    return '<label><input type="checkbox" data-erd-fit' . ($fit ? ' checked' : '') . '> Fit to window</label>'
        . '<button type="button" class="secondary" data-erd-save title="Download the diagram as an SVG file">Save .svg</button>';
}

/**
 * The diagram as a modal popup, added to every page by pageEnd(). On the exam
 * page it keeps the diagram inside the one URL that students may open.
 * Opened by [data-erd-open].
 */
function renderErdDialog(array $schema, string $dbName): string
{
    if (!$schema['tables']) {
        return '';
    }
    return '<dialog class="erd-dialog" id="erd-dialog" aria-label="Database diagram">'
        . '<div class="erd-dialog-head"><strong>Database diagram</strong><div class="erd-tools">' . renderErdTools(true) . '</div>'
        . '<button type="button" class="secondary" data-erd-close title="Close (Esc)">Close</button></div>'
        . '<p class="lead">' . renderErdLegend($schema, $dbName) . '</p>'
        . '<div class="erd-wrap fit">' . renderErd($schema) . '</div>'
        . '</dialog>';
}

// ---------------------------------------------------------------------------
// Diagrams inside lessons: the <erd> tag
// ---------------------------------------------------------------------------

/**
 * Lesson HTML (intro, example notes, exercise tasks) may contain <erd> tags;
 * each one is replaced by a diagram drawn by renderErd().
 *
 * 1. Tables of the course database, as they really are:
 *
 *      <erd tables="Orders, OrderDetails, Products"></erd>
 *      <erd tables="Orders, Customers" columns="keys"></erd>     only PK / FK columns
 *
 * 2. A design of your own, one table per line:
 *
 *      <erd>
 *      Members: MemberID PK, FirstName, LastName, Email varchar(100)
 *      Loans: LoanID PK, MemberID > Members, BookID > Books, LoanDate date
 *      Books: BookID PK, Title
 *      + Authors: AuthorID PK, Name
 *      </erd>
 *
 *    Table: column, column, ... A column is a name, optionally followed by a
 *    data type, PK, and "> Table" (foreign key to that table's primary key) or
 *    "> Table.Column" ("->" works too). UNIQUE after a foreign key makes the
 *    relationship one-to-one. "Table #green: ..." colours the table (blue,
 *    green, orange, purple, red, teal, grey). A line "A <> B" draws a
 *    many-to-many relationship without a link table. Tables are placed left to right in the order written;
 *    a line that starts with + goes under the previous table instead.
 *    Lines are drawn best between neighbours, so write linked tables next to
 *    each other.
 *
 * A mistake (unknown table, bad line) shows as a red box on the page and is
 * reported by tools/check-solutions.php.
 */
function lessonHtml(string $html, array $schema): string
{
    if (stripos($html, '<erd') === false) {
        return $html;
    }
    return preg_replace_callback('/<erd\b([^>]*)>(.*?)<\/erd>/is', function (array $m) use ($schema): string {
        preg_match_all('/([\w-]+)\s*=\s*"([^"]*)"/', $m[1], $pairs, PREG_SET_ORDER);
        $attr = [];
        foreach ($pairs as [, $name, $value]) {
            $attr[strtolower($name)] = html_entity_decode($value);
        }
        $layout = null;
        if (isset($attr['tables'])) {
            $diagram = erdSubset($schema, preg_split('/\s*,\s*/', trim($attr['tables']), -1, PREG_SPLIT_NO_EMPTY));
        } else {
            // Only real tags are dropped: "A <> B" (many-to-many) and "x > Table" belong to the notation.
            $diagram = parseErd(html_entity_decode(preg_replace('/<\/?[a-z][^>]*>/i', '', $m[2])));
            $layout = is_array($diagram) ? $diagram['layout'] : null;
        }
        if (is_string($diagram)) {
            return '<div class="erd-error">Diagram: ' . h($diagram) . '</div>';
        }
        if (($attr['columns'] ?? '') === 'keys') {
            $keys = keyColumns($diagram);
            foreach ($diagram['tables'] as $table => $cols) {
                $diagram['tables'][$table] = array_values(array_filter($cols, fn($c) => isset($keys[$table][$c[0]])));
            }
        }
        return '<div class="erd-wrap erd-inline">' . renderErd($diagram, $layout) . '</div>';
    }, $html);
}

/** The named tables of the course database as a schema of their own, or an error text. */
function erdSubset(array $schema, array $names): array|string
{
    $byLower = [];
    foreach (array_keys($schema['tables']) as $table) {
        $byLower[strtolower($table)] = $table;
    }
    $out = ['tables' => [], 'primary' => [], 'foreign' => []];
    foreach ($names as $name) {
        $table = $byLower[strtolower($name)] ?? null;
        if ($table === null) {
            return $schema['tables'] ? "table \"$name\" does not exist in the database." : 'the database is not reachable.';
        }
        $out['tables'][$table] = $schema['tables'][$table];
        $out['primary'][$table] = $schema['primary'][$table] ?? [];
    }
    // Foreign keys to tables outside the selection keep their FK tag, without a line.
    $out['foreign'] = array_values(array_filter($schema['foreign'] ?? [], fn($f) => isset($out['tables'][$f[0]])));
    return $out;
}

/**
 * The text notation of lessonHtml() as ['tables', 'primary', 'foreign', 'layout'], or an error text.
 * $lenient (the live drawing of a design exercise, where the last line is
 * usually half typed): what cannot be read is skipped instead, and the first
 * such problem is returned in 'problem'.
 */
function parseErd(string $text, bool $lenient = false): array|string
{
    $out = ['tables' => [], 'primary' => [], 'foreign' => [], 'many' => [], 'layout' => [], 'positions' => [], 'colors' => [], 'problem' => null];
    $manyRefs = [];
    $fail = function (string $message) use (&$out, $lenient): ?string {
        $out['problem'] ??= $message;
        return $lenient ? null : $message;
    };
    $refs = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $stack = $line[0] === '+';
        // "A <> B": a many-to-many relationship as it is drawn before the link table exists.
        if (preg_match('/^(\w+)\s*<>\s*(\w+)$/', $line, $m)) {
            $manyRefs[] = [$m[1], $m[2]];
            continue;
        }
        if (!preg_match('/^\+?\s*([\w ]+?)\s*(?:@\s*(-?\d+)\s*,\s*(-?\d+)\s*)?(?:#(\w+)\s*)?:\s*(.*)$/', $line, $m)) {
            if (($error = $fail("cannot read the line \"$line\". Write: Table: column, column, ...")) !== null) {
                return $error;
            }
            continue;
        }
        [, $table, $x, $y, $color, $columns] = $m;
        if (isset($out['tables'][$table])) {
            if (($error = $fail("table \"$table\" is defined twice.")) !== null) {
                return $error;
            }
            continue;
        }
        $out['tables'][$table] = [];
        if ($x !== '') {
            $out['positions'][$table] = [(int) $x, (int) $y];   // "Table @x,y:" as the editor writes it
        }
        if ($color !== '') {
            $out['colors'][$table] = strtolower($color);        // "Table #green:"
        }
        foreach (preg_split('/,(?![^()]*\))/', $columns) as $column) {
            if (!preg_match('/^(\S+)\s*(.*)$/', trim($column), $c)) {
                continue;
            }
            [, $name, $rest] = $c;
            if (preg_match('/-?>\s*(\w+)(?:\.(\w+))?/', $rest, $r)) {
                $rest = str_replace($r[0], '', $rest);
                // UNIQUE: at most one row here per row there, a one-to-one relationship.
                $refs[] = [$table, $name, $r[1], $r[2] ?? null, (bool) preg_match('/\bUNIQUE\b/i', $rest)];
            }
            if (preg_match('/\bPK\b/i', $rest)) {
                $out['primary'][$table][] = $name;
            }
            $type = trim(preg_replace('/\b(PK|FK|UNIQUE)\b/i', '', $rest));
            $out['tables'][$table][] = [$name, strtolower($type)];
        }
        if ($stack && $out['layout']) {
            $out['layout'][count($out['layout']) - 1][] = $table;
        } else {
            $out['layout'][] = [$table];
        }
    }
    if (!$out['tables']) {
        return $out['problem'] ?? 'no tables. Write one line per table: Table: column, column, ...';
    }
    // "> team" finds the table Teams: names are matched as erdName() compares them.
    $tableByName = [];
    foreach (array_keys($out['tables']) as $table) {
        $tableByName[erdName($table, true)] ??= $table;
    }
    foreach ($manyRefs as [$a, $b]) {
        $ta = $tableByName[erdName($a, true)] ?? null;
        $tb = $tableByName[erdName($b, true)] ?? null;
        if ($ta === null || $tb === null) {
            if (($error = $fail("the many-to-many line $a <> $b names a table that is not in this diagram.")) !== null) {
                return $error;
            }
            continue;
        }
        $out['many'][] = [$ta, $tb];
    }
    foreach ($refs as [$table, $column, $refName, $refColumn, $unique]) {
        $refTable = $tableByName[erdName($refName, true)] ?? null;
        if ($refTable === null) {
            if (($error = $fail("$table.$column points to \"$refName\", which is not in this diagram.")) !== null) {
                return $error;
            }
            continue;
        }
        $columnByName = [];
        foreach ($out['tables'][$refTable] as [$name]) {
            $columnByName[erdName($name)] ??= $name;
        }
        $wanted = $refColumn ?? $out['primary'][$refTable][0] ?? $column;
        $refColumn = $columnByName[erdName($wanted)] ?? null;
        if ($refColumn === null) {
            if (($error = $fail("$table.$column points to $refTable.$wanted, but $refTable has no column \"$wanted\".")) !== null) {
                return $error;
            }
            continue;
        }
        $out['foreign'][] = [$table, $column, $refTable, $refColumn, $unique];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Design exercises: the student writes an ERD ('type' => 'erd')
// ---------------------------------------------------------------------------

/**
 * A name as it is compared: case, spaces and underscores do not matter
 * (first_name = FirstName), and for tables singular = plural (Category =
 * Categories, Loan = Loans).
 */
function erdName(string $name, bool $table = false): string
{
    $n = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    if ($table) {
        $n = preg_replace('/ies$/', 'y', $n, 1, $done);
        if (!$done) {
            $n = preg_replace('/s$/', '', $n);
        }
    }
    return $n;
}

/**
 * A data type as it is compared: the kind of value, not the exact type.
 * varchar(40) = varchar(100) = text, int = smallint, decimal = float, datetime = timestamp.
 */
function erdTypeGroup(string $type): string
{
    $t = strtolower(trim(preg_replace('/\(.*$/', '', $type)));
    return match (true) {
        in_array($t, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'], true) => 'int',
        in_array($t, ['decimal', 'numeric', 'float', 'double', 'real', 'money'], true) => 'decimal',
        in_array($t, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'string'], true) => 'text',
        in_array($t, ['datetime', 'timestamp'], true) => 'datetime',
        in_array($t, ['bool', 'boolean', 'bit'], true) => 'boolean',
        default => $t,
    };
}

/** A parsed design in comparable form: tables with columns (and their kind of type) and key, the foreign keys, the many-to-many lines. */
function erdShape(array $design): array
{
    $shape = ['tables' => [], 'foreign' => [], 'many' => []];
    foreach ($design['many'] ?? [] as [$a, $b]) {
        $pair = [erdName($a, true), erdName($b, true)];
        sort($pair);
        $shape['many'][implode('<>', $pair)] = [$a, $b];
    }
    foreach ($design['tables'] as $table => $cols) {
        $columns = [];
        $types = [];
        foreach ($cols as [$col, $type]) {
            $columns[erdName($col)] = $col;
            $types[erdName($col)] = erdTypeGroup($type);
        }
        $pk = array_map('erdName', $design['primary'][$table] ?? []);
        sort($pk);
        $shape['tables'][erdName($table, true)] = ['name' => $table, 'columns' => $columns, 'types' => $types, 'pk' => $pk];
    }
    foreach ($design['foreign'] as $foreign) {
        [$table, $col, $refTable] = $foreign;
        $shape['foreign'][erdName($table, true) . '.' . erdName($col)] = [erdName($table, true), erdName($refTable, true), $table, $col, $refTable, $foreign[4] ?? false];
    }
    return $shape;
}

/**
 * Compare the student's design (the notation of parseErd()) with the model
 * design in $exercise['solution']: same tables, same columns, same primary
 * keys, same foreign keys with the same ends (one or many), and the same kind
 * of data type for every column that has a type in the model design
 * (erdTypeGroup()). The order and place of tables and columns are free. The message names the first difference without giving the answer away.
 * Returns ['status' => correct|wrong|error|invalid, 'message', 'design' => ?parsed design].
 */
function checkErdExercise(array $exercise, string $text): array
{
    $out = ['status' => 'invalid', 'message' => '', 'design' => null];
    if (trim($text) === '') {
        $out['message'] = 'Draw your design first: start with an entity.';
        return $out;
    }
    $model = parseErd($exercise['solution']);
    if (is_string($model)) {
        $out['status'] = 'error';
        $out['message'] = 'The model design of this exercise is broken, tell your teacher: ' . $model;
        return $out;
    }
    $design = parseErd($text);
    if (is_string($design)) {
        $out['status'] = 'error';
        $out['message'] = ucfirst($design);
        return $out;
    }
    $out['design'] = $design;
    $out['status'] = 'wrong';
    $m = erdShape($model);
    $s = erdShape($design);

    // Tables.
    foreach ($s['tables'] as $key => $t) {
        if (!isset($m['tables'][$key])) {
            $out['message'] = "Table \"{$t['name']}\" is not in the model design. Use the table names from the task.";
            return $out;
        }
    }
    $mc = count($m['tables']);
    $sc = count($s['tables']);
    if ($mc !== $sc) {
        $out['message'] = "The model design has $mc table" . ($mc === 1 ? '' : 's') . ", yours has $sc.";
        return $out;
    }

    // A many-to-many line: fine as a first sketch, but tables cannot store it.
    foreach ($s['many'] as $key => [$a, $b]) {
        if (!isset($m['many'][$key])) {
            $out['message'] = "$a and $b are linked many-to-many. Tables cannot store that directly: put a link table between them, with a one-to-many relationship to each side.";
            return $out;
        }
    }
    foreach ($m['many'] as $key => [$a, $b]) {
        if (!isset($s['many'][$key])) {
            $out['message'] = "$a and $b are not linked yet.";
            return $out;
        }
    }

    // A foreign key on the wrong side would otherwise be reported as a strange column.
    $pairs = [];                                   // "from>to" of the model
    foreach ($m['foreign'] as [$from, $to]) {
        $pairs["$from>$to"] = true;
    }
    foreach ($s['foreign'] as [$from, $to, $table, , $refTable]) {
        if ($from !== $to && isset($pairs["$to>$from"]) && !isset($pairs["$from>$to"])) {
            $out['message'] = "The foreign key between $table and $refTable is on the wrong side. It belongs in the table on the \"many\" side.";
            return $out;
        }
    }

    // Columns, then the primary key, per table.
    foreach ($s['tables'] as $key => $t) {
        $expected = $m['tables'][$key];
        foreach ($t['columns'] as $col => $name) {
            if (!isset($expected['columns'][$col])) {
                $out['message'] = "Table {$t['name']}: column \"$name\" is not in the model design. Does it belong in another table, or is the name different in the task?";
                return $out;
            }
        }
        $ec = count($expected['columns']);
        $tc = count($t['columns']);
        if ($ec !== $tc) {
            $out['message'] = "Table {$t['name']}: $ec column" . ($ec === 1 ? '' : 's') . " expected, you have $tc.";
            return $out;
        }
    }
    foreach ($s['tables'] as $key => $t) {
        if (!$t['pk']) {
            $out['message'] = "Table {$t['name']} has no primary key. Mark it with PK.";
            return $out;
        }
        if ($t['pk'] !== $m['tables'][$key]['pk']) {
            $out['message'] = "Table {$t['name']}: the primary key is not right. Which column, or which columns together, are unique for every row?";
            return $out;
        }
    }

    // Foreign keys.
    foreach ($s['foreign'] as $key => [$from, $to, $table, $col, $refTable]) {
        if (isset($m['foreign'][$key]) && $m['foreign'][$key][1] === $to) {
            continue;
        }
        if (isset($m['foreign'][$key])) {
            $out['message'] = "$table.$col points to the wrong table.";
        } else {
            $out['message'] = "$table.$col should not be a foreign key to $refTable.";
        }
        return $out;
    }
    foreach ($m['foreign'] as $key => [$from, $to]) {
        if (!isset($s['foreign'][$key])) {
            $a = $s['tables'][$from]['name'];
            $b = $s['tables'][$to]['name'];
            $out['message'] = $from === $to
                ? "Table $a should be linked to itself: a foreign key is missing."
                : "$a and $b are not linked yet: a foreign key is missing.";
            return $out;
        }
    }

    // One or many at the end of each relationship.
    foreach ($s['foreign'] as $key => [, , $table, , $refTable, $one]) {
        if ($one !== $m['foreign'][$key][5]) {
            $out['message'] = "Look at the ends of the relationship between $table and $refTable: one $refTable has "
                . ($one ? 'one' : 'many') . " $table in your design. Is that what the task says?";
            return $out;
        }
    }

    // Data types: the kind of value must match wherever the model design gives a type.
    foreach ($s['tables'] as $key => $t) {
        foreach ($t['columns'] as $col => $name) {
            $expected = $m['tables'][$key]['types'][$col];
            if ($expected === '') {
                continue;
            }
            if ($t['types'][$col] === '') {
                $out['message'] = "Table {$t['name']}: attribute \"$name\" has no data type yet.";
                return $out;
            }
            if ($t['types'][$col] !== $expected) {
                $out['message'] = "Table {$t['name']}: the data type of \"$name\" is not right. What kind of value does it hold?";
                return $out;
            }
        }
    }

    $out['status'] = 'correct';
    $out['message'] = 'Correct!';
    return $out;
}

/**
 * Check an answer of either exercise type, in the shape of checkExercise()
 * (plus 'design' for a design exercise). Used by the exam pages, where an
 * exam set may mix queries and designs. $pdo is only needed for a query.
 */
function checkAnswer(?PDO $pdo, array $exercise, string $text): array
{
    if (($exercise['type'] ?? 'sql') === 'erd') {
        return checkErdExercise($exercise, $text) + ['student' => null, 'expected' => null];
    }
    return checkExercise($pdo, $exercise, $text);
}

/** The editor text as it is stored and checked: a design keeps its lines, a query is tidied by normalizeSql(). */
function answerText(array $exercise, string $text): string
{
    return ($exercise['type'] ?? 'sql') === 'erd' ? trim(str_replace("\r", '', $text)) : normalizeSql($text);
}

/** The diagram of a parsed design, as in a lesson. */
function renderErdDesign(array $design): string
{
    return '<div class="erd-wrap erd-inline">' . renderErd($design, $design['layout'], $design['positions'] ?: null) . '</div>';
}

/**
 * Verdict + the student's diagram (also sent alone to fetch() requests).
 * $diagram = false: the page shows the design in the editor already.
 */
function renderErdCheck(array $check, bool $diagram = true): string
{
    $labels = ['correct' => 'Correct', 'wrong' => 'Not yet', 'error' => 'Error', 'invalid' => 'Cannot check'];
    $out = '<div class="verdict ' . h($check['status']) . '"><strong>' . $labels[$check['status']] . '</strong> ' . h($check['message']) . '</div>';
    if ($diagram && $check['design'] !== null) {
        $out .= renderErdDesign($check['design']);
    }
    return $out;
}

/** A design as a drawing, whatever state it is in (teacher page, submitted exam): what cannot be read is named under it. */
function renderErdPreview(string $text): string
{
    if (trim($text) === '') {
        return '';
    }
    $design = parseErd($text, true);
    if (is_string($design)) {
        return '<div class="result"><div class="notice">' . h(ucfirst($design)) . '</div></div>';
    }
    return renderErdDesign($design)
        . ($design['problem'] !== null ? '<div class="result"><div class="notice">Not drawn yet: ' . h($design['problem']) . '</div></div>' : '');
}

/** Sidebar of a design exercise: how the editor works, instead of the table list. */
function renderErdHelp(): string
{
    return '<h2>How to draw</h2><div class="erd-help"><ul>'
        . '<li><strong>Entity:</strong> drag the Entity block onto the canvas (or click it) and type its name.</li>'
        . '<li><strong>Attribute:</strong> press "+ attribute", type the name and choose its data type. Enter goes to the next one.</li>'
        . '<li><strong>Primary key:</strong> click PK in front of the attribute. Two PKs make a composite key.</li>'
        . '<li><strong>Relationship:</strong> drag a round connector on the side of the "one" entity onto the "many" entity (or click the connector, then the entity). The foreign key is added for you; rename it if needed.</li>'
        . '<li><strong>One or many:</strong> click an end of the line. Bar = one, crow\'s foot = many.</li>'
        . '<li><strong>Remove a relationship:</strong> the × in the middle of its line.</li>'
        . '<li><strong>Colour:</strong> click the dot in the entity\'s bar. Colours are not checked.</li>'
        . '<li><strong>Move:</strong> drag an entity by its coloured bar.</li>'
        . '</ul>'
        . '<h2>Data types</h2><div class="erd-types">'
        . '<div><code>INT</code> whole number, numeric key</div>'
        . '<div><code>DECIMAL</code> number with decimals, money</div>'
        . '<div><code>VARCHAR</code> short text</div>'
        . '<div><code>TEXT</code> long text</div>'
        . '<div><code>DATE</code> a day</div>'
        . '<div><code>DATETIME</code> a day and a time</div>'
        . '<div><code>BOOLEAN</code> yes or no</div>'
        . '</div></div>';
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

/**
 * Tables that a task text talks about: the table name or its singular
 * ("order" for Orders), or a column that exists in exactly one table.
 */
function mentionedTables(array $schema, string $text): array
{
    $owner = [];                                   // column => [tables]
    foreach ($schema['tables'] as $table => $cols) {
        foreach ($cols as [$col]) {
            $owner[$col][] = $table;
        }
    }
    $found = [];
    foreach ($schema['tables'] as $table => $cols) {
        $names = [$table, preg_replace('/s$/i', '', $table)];
        foreach ($cols as [$col]) {
            if (count($owner[$col]) === 1) {
                $names[] = $col;
            }
        }
        $alt = implode('|', array_map(fn($n) => preg_quote($n, '/'), array_unique($names)));
        if (preg_match('/\b(' . $alt . ')\b/i', $text)) {
            $found[] = $table;
        }
    }
    return $found;
}

/**
 * Sidebar block with every table and its columns (PK / FK marked), and the
 * way to the diagram: a link to erd.php that erd.js turns into the popup, or
 * in exam mode a plain button for the popup (see renderErdDialog()).
 */
function renderSchemaSidebar(array $schema, array $open = [], bool $exam = false): string
{
    $keys = keyColumns($schema);
    $out = '<h2>Tables (' . count($schema['tables']) . ')</h2>';
    $title = 'Entity-relationship diagram: all tables and how they are linked';
    $out .= $exam
        ? '<p class="erd-link"><button type="button" class="link" data-erd-open title="' . $title . '">Diagram (ERD)</button></p>'
        : '<p class="erd-link"><a href="erd.php" data-erd-open title="' . $title . '">Diagram (ERD)</a></p>';
    foreach ($schema['tables'] as $name => $cols) {
        $out .= '<details' . (in_array($name, $open, true) ? ' open' : '') . '><summary>' . h($name) . '</summary><ul>';
        foreach ($cols as [$col, $type]) {
            $key = $keys[$name][$col] ?? '';
            $out .= '<li><em class="' . $key . '">' . strtoupper($key) . '</em>' . h($col) . ' <span>' . h($type) . '</span></li>';
        }
        $out .= '</ul></details>';
    }
    return $out;
}

/**
 * <head> + header + opening of the layout.
 * $active: 'home' | 'playground' | 'erd' | 'exam'. With $sidebar the two-column
 * layout is used and the given HTML goes into <aside>. 'exam' renders the
 * header without links: no way to reach the course from the exam page.
 * $bodyClass lets a page adjust the layout (erd.php: full width).
 */
function pageStart(string $title, string $active, ?string $sidebar = null, bool $editor = false, string $bodyClass = ''): void
{
    global $config;
    $exam = $active === 'exam';
    $nav = $exam ? [] : [
        'home' => ['index.php', 'Course'],
        'playground' => ['playground.php', 'Playground'],
        'erd' => ['erd.php', 'Diagram'],
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
<body<?= $bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : '' ?>>
<header>
<?php if ($exam): ?>
  <span class="brand">Northwind SQL · Exam</span>
<?php else: ?>
  <a class="brand" href="index.php">Northwind SQL</a>
  <nav>
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= $href ?>"<?= $active === $key ? ' class="active"' : ($key === 'erd' ? ' data-erd-open' : '') ?>><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
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

/**
 * Close the layout, load scripts. $hintTables feeds the autocomplete; null = no
 * editor on this page. Every page gets the diagram popup ([data-erd-open]
 * opens it), except erd.php itself ($erdPage), which shows the diagram inline.
 * $scripts: extra files from assets/, loaded last (exam pages).
 */
function pageEnd(?array $hintTables = null, string $primaryAction = 'run', bool $erdPage = false, array $scripts = []): void
{
    global $config;
    $pdo = null;
    $connectError = null;
    $schema = $erdPage ? null : schemaOrConnect($config, false, $pdo, $connectError);
    $pdo = null;
    ?>
</main>
</div>
<?= $schema !== null ? renderErdDialog($schema, $config['DB_NAME']) : '' ?>
<script src="assets/erd.js?v=<?= assetVersion('erd.js') ?>"></script>
<?php if ($hintTables !== null): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/sql-hint.min.js"></script>
<script src="assets/sqlformat.js?v=<?= assetVersion('sqlformat.js') ?>"></script>
<script>window.NW = { tables: <?= json_encode($hintTables, JSON_UNESCAPED_SLASHES) ?>, primaryAction: <?= json_encode($primaryAction) ?> };</script>
<script src="assets/app.js?v=<?= assetVersion('app.js') ?>"></script>
<?php endif; ?>
<?php foreach ($scripts as $script): ?>
<script src="assets/<?= h($script) ?>?v=<?= assetVersion($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
