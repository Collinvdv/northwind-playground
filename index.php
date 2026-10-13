<?php
/**
 * Northwind SQL playground.
 *
 * One file, no dependencies. Students type SQL, the page runs it on a real
 * MySQL server and shows the result. The database user is SELECT-only
 * (see setup.sql), so INSERT/UPDATE/DELETE/CREATE are refused by MySQL.
 *
 * Configuration: a .env file next to this script (see .env.example), or the
 * same names as environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS). A value in .env always wins over an environment variable.
 */

declare(strict_types=1);

const MAX_ROWS = 500;          // rows shown per result set
const QUERY_TIMEOUT_MS = 5000; // SELECTs slower than this are killed
const SCHEMA_CACHE_TTL = 600;  // seconds the table list is cached (APCu or temp file)

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

$dotenv = loadDotEnv(__DIR__ . '/.env');
$setting = fn(string $key, string $default) => ($dotenv[$key] ?? '') ?: (getenv($key) ?: $default);

$config = [
    'DB_HOST' => $setting('DB_HOST', '127.0.0.1'),
    'DB_PORT' => $setting('DB_PORT', '3306'),
    'DB_NAME' => $setting('DB_NAME', 'northwind'),
    'DB_USER' => $setting('DB_USER', 'northwind_ro'),
    'DB_PASS' => $setting('DB_PASS', 'northwind'),
];

// Only reading statements are allowed. The DB user on shared hosting has full
// rights, so this allowlist (plus SET SESSION TRANSACTION READ ONLY) is what
// keeps 30 students from changing each other's data.
const ALLOWED_STATEMENTS = ['SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];

// ---------------------------------------------------------------------------
// Helpers
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
        // Unbuffered: rows stream from MySQL, PHP never holds more than MAX_ROWS.
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

/** True when the statement starts with one of ALLOWED_STATEMENTS (after optional "(" ). */
function isAllowedStatement(string $sql): bool
{
    if (!preg_match('/^[\s(]*([a-z]+)/i', $sql, $m)) {
        return false;
    }
    return in_array(strtoupper($m[1]), ALLOWED_STATEMENTS, true);
}

/** Run one statement, return ['columns'=>[], 'rows'=>[], 'affected'=>int, 'truncated'=>bool, 'ms'=>float] */
function runStatement(PDO $pdo, string $sql): array
{
    $t0 = microtime(true);
    $stmt = $pdo->query($sql);
    $result = ['columns' => [], 'rows' => [], 'affected' => 0, 'truncated' => false, 'ms' => 0.0];

    if ($stmt->columnCount() > 0) {
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $result['columns'][] = $stmt->getColumnMeta($i)['name'] ?? "col$i";
        }
        while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
            if (count($result['rows']) >= MAX_ROWS) {
                $result['truncated'] = true;
                break;
            }
            $result['rows'][] = $row;
        }
        if ($result['truncated']) {
            // Stop MySQL from producing the rest of the result set, then drop the cursor.
            killRunningQuery();
        }
    } else {
        $result['affected'] = $stmt->rowCount();
    }
    $stmt->closeCursor();
    $result['ms'] = (microtime(true) - $t0) * 1000;
    return $result;
}

/** Kill the statement still running on the main connection, via a second connection. */
function killRunningQuery(): void
{
    global $config, $connectionId;
    try {
        $killer = connect($config);
        $killer->exec('KILL QUERY ' . (int) $connectionId);
    } catch (PDOException $e) {
        // Not fatal: closeCursor() then drains the rest, bounded by MAX_EXECUTION_TIME.
    }
}

/** Tables with their columns, for the sidebar and autocomplete (views are queryable but not listed). */
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

/** The results block under the editor. Also sent on its own to the page's fetch() requests. */
function renderResults(array $results, ?string $connectError): string
{
    ob_start();
    ?>
  <?php if ($connectError): ?>
    <div class="result"><div class="error">Cannot connect to MySQL: <?= h($connectError) ?>

Check DB_HOST / DB_PORT / DB_USER / DB_PASS in .env (or the environment variables).</div></div>
  <?php endif; ?>
  <?php foreach ($results as $i => $r): ?>
    <div class="result">
      <div class="head">
        <span>#<?= $i + 1 ?></span>
        <code><?= h($r['sql']) ?></code>
        <?php if ($r['result']): ?>
          <span><?= number_format($r['result']['ms'], 1) ?> ms</span>
        <?php endif; ?>
      </div>
      <?php if ($r['error'] !== null): ?>
        <div class="error"><?= h($r['error']) ?></div>
      <?php elseif ($r['result']['columns']): ?>
        <div class="scroll">
          <table>
            <thead><tr><?php foreach ($r['result']['columns'] as $c): ?><th><?= h($c) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($r['result']['rows'] as $row): ?>
              <tr><?php foreach ($row as $v) echo formatCell($v); ?></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="notice">
          <?= count($r['result']['rows']) ?> row<?= count($r['result']['rows']) === 1 ? '' : 's' ?>
          <?php if ($r['result']['truncated']): ?> · only the first <?= MAX_ROWS ?> rows are shown, add <code>LIMIT</code> to narrow it down<?php endif; ?>
        </div>
      <?php else: ?>
        <div class="notice">OK · <?= $r['result']['affected'] ?> rows affected</div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
    <?php
    return (string) ob_get_clean();
}

// ---------------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------------

$sqlInput = $_POST['sql'] ?? "SELECT * FROM Customers LIMIT 10;";
$sqlInput = str_replace("\r\n", "\n", (string) $sqlInput);
$results = [];     // list of ['sql'=>..., 'result'=>..., 'error'=>...]
$connectError = null;
$connectionId = 0;
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
// The page's own JavaScript posts with this header and only wants the results block back.
$isFetch = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

// A plain page load with a warm cache does not touch MySQL at all.
$schema = readSchemaCache($config['DB_NAME']);

try {
    if ($schema === null || $isPost) {
        $pdo = connect($config);
        if ($schema === null) {
            $schema = loadSchema($pdo, $config['DB_NAME']);
            writeSchemaCache($config['DB_NAME'], $schema);
        }
    }

    if ($isPost) {
        $connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        foreach (splitStatements($sqlInput) as $statement) {
            if (!isAllowedStatement($statement)) {
                $results[] = ['sql' => $statement, 'result' => null,
                    'error' => 'Only ' . implode(', ', ALLOWED_STATEMENTS) . ' statements are allowed in this playground (the database is read-only).'];
                break;
            }
            try {
                $results[] = ['sql' => $statement, 'result' => runStatement($pdo, $statement), 'error' => null];
            } catch (PDOException $e) {
                // Show MySQL's own message, without the PDO prefix "SQLSTATE[42000]: ..."
                $msg = preg_replace('/^SQLSTATE\[\w+\]:?\s*(?:[^:]+:\s*\d+\s*)?/', '', $e->getMessage());
                $results[] = ['sql' => $statement, 'result' => null, 'error' => $msg];
                break; // stop at first error, like Workbench does
            }
        }
    }
} catch (PDOException $e) {
    $connectError = $e->getMessage();
}
$schema = $schema ?? ['tables' => [], 'views' => []];

if ($isFetch) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo renderResults($results, $connectError);
    exit;
}

$hintTables = [];
foreach (['tables'] as $kind) {
    foreach ($schema[$kind] as $name => $cols) {
        $hintTables[$name] = array_column($cols, 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Northwind SQL playground</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
<style>
  :root { --bg:#f6f7f9; --panel:#fff; --line:#dde1e6; --text:#1f2328; --muted:#6b7280; --accent:#1d4ed8; --err:#b91c1c; --errbg:#fef2f2; }
  * { box-sizing: border-box; }
  body { margin:0; font: 14px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--text); background:var(--bg); }
  header { display:flex; align-items:baseline; gap:14px; padding:10px 18px; background:var(--panel); border-bottom:1px solid var(--line); }
  header h1 { font-size:17px; margin:0; }
  header .muted { color:var(--muted); font-size:13px; }
  .layout { display:grid; grid-template-columns: 260px 1fr; min-height: calc(100vh - 44px); }
  aside { background:var(--panel); border-right:1px solid var(--line); padding:12px 10px; overflow:auto; position:sticky; top:0; max-height:100vh; font-size:13px; }
  aside h2 { font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin:10px 6px 4px; }
  aside details { margin:0; }
  aside summary { cursor:pointer; padding:3px 6px; border-radius:4px; font-family: ui-monospace, Menlo, monospace; }
  aside summary:hover { background:var(--bg); }
  aside ul { list-style:none; margin:0 0 4px; padding:0 0 0 18px; }
  aside li { font-family: ui-monospace, Menlo, monospace; font-size:12px; white-space:nowrap; }
  aside li span { color:var(--muted); }
  main { padding:16px 18px; min-width:0; }
  form { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:10px; }
  textarea { width:100%; min-height:160px; font: 13px/1.5 ui-monospace, Menlo, monospace; border:1px solid var(--line); border-radius:6px; padding:8px; }
  .CodeMirror { height:auto; min-height:160px; border:1px solid var(--line); border-radius:6px; font-size:13px; }
  .CodeMirror-scroll { min-height:160px; max-height:50vh; }
  .bar { display:flex; align-items:center; gap:12px; margin-top:8px; }
  button { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:7px 16px; font-size:14px; cursor:pointer; }
  button:hover { filter:brightness(1.1); }
  button:disabled { opacity:.6; cursor:default; filter:none; }
  .hint { color:var(--muted); font-size:12px; }
  .result { margin-top:16px; background:var(--panel); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
  .result .head { display:flex; gap:12px; align-items:baseline; padding:8px 12px; border-bottom:1px solid var(--line); font-size:12px; color:var(--muted); }
  .result .head code { color:var(--text); font-size:12px; white-space:pre-wrap; flex:1; }
  .scroll { overflow:auto; max-height:60vh; }
  table { border-collapse:collapse; width:100%; font-size:13px; }
  th, td { padding:5px 10px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; }
  th { position:sticky; top:0; background:#f0f2f5; font-weight:600; }
  tr:hover td { background:#f9fafb; }
  td.num { text-align:right; font-variant-numeric: tabular-nums; }
  td.null { color:var(--muted); font-style:italic; }
  .error { background:var(--errbg); color:var(--err); padding:10px 12px; font-family: ui-monospace, Menlo, monospace; font-size:13px; white-space:pre-wrap; }
  .notice { padding:8px 12px; color:var(--muted); font-size:12px; }
  @media (max-width: 800px) { .layout { grid-template-columns:1fr; } aside { position:static; max-height:none; border-right:0; border-bottom:1px solid var(--line); } }
</style>
</head>
<body>
<header>
  <h1>Northwind SQL playground</h1>
  <span class="muted">MySQL · database <code><?= h($config['DB_NAME']) ?></code> · read-only</span>
</header>

<div class="layout">
<aside>
  <?php foreach (['tables' => 'Tables'] as $kind => $label): ?>
    <h2><?= $label ?> (<?= count($schema[$kind]) ?>)</h2>
    <?php foreach ($schema[$kind] as $name => $cols): ?>
      <details>
        <summary><?= h($name) ?></summary>
        <ul>
          <?php foreach ($cols as [$col, $type]): ?>
            <li><?= h($col) ?> <span><?= h($type) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; ?>
  <?php endforeach; ?>
</aside>

<main>
  <form method="post" id="sqlform">
    <textarea name="sql" id="sql" spellcheck="false"><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit">Run</button>
      <span class="hint">Ctrl+Enter / ⌘+Enter runs · Ctrl+Space autocompletes · several statements separated by <code>;</code> are run one after another · max <?= MAX_ROWS ?> rows shown</span>
    </div>
  </form>

  <div id="results">
    <?= renderResults($results, $connectError) ?>
  </div>
</main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/sql-hint.min.js"></script>
<script>
(function () {
  var form = document.getElementById('sqlform');
  var textarea = document.getElementById('sql');
  var button = form.querySelector('button[type=submit]');
  var resultsBox = document.getElementById('results');
  var tables = <?= json_encode($hintTables, JSON_UNESCAPED_SLASHES) ?>;
  var editor = null;
  var busy = false;

  function run() { if (busy) return; form.requestSubmit ? form.requestSubmit() : form.submit(); }

  function setBusy(on) {
    busy = on;
    button.disabled = on;
    button.textContent = on ? 'Running…' : 'Run';
  }

  function notice(text, isError) {
    resultsBox.innerHTML = '';
    var box = document.createElement('div');
    box.className = 'result';
    var inner = document.createElement('div');
    inner.className = isError ? 'error' : 'notice';
    inner.textContent = text;
    box.appendChild(inner);
    resultsBox.appendChild(box);
  }

  function wait(seconds) { return new Promise(function (r) { setTimeout(r, seconds * 1000); }); }

  // Send the query without reloading the page. Shared hosts answer 429/503
  // when a whole classroom (one public IP) runs queries at once: wait for
  // Retry-After and try once more instead of showing the host's error page.
  function submitViaFetch(attempt) {
    return fetch(location.href, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (res) {
      if (res.status === 429 || res.status === 503) {
        var retry = parseInt(res.headers.get('Retry-After'), 10);
        if (!(retry > 0)) retry = 3;
        retry = Math.min(retry, 30);
        if (attempt < 2) {
          notice('Server busy (HTTP ' + res.status + '), retrying in ' + retry + ' s…');
          return wait(retry).then(function () { return submitViaFetch(attempt + 1); });
        }
        notice('Server busy (HTTP ' + res.status + '). Wait a few seconds and press Run again.', true);
        return;
      }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text().then(function (html) { resultsBox.innerHTML = html; });
    });
  }

  form.addEventListener('submit', function (e) {
    if (editor) editor.save();
    if (!window.fetch || !window.FormData) return; // old browser: normal form post
    e.preventDefault();
    if (busy) return;
    setBusy(true);
    notice('Running…');
    submitViaFetch(0)
      .catch(function (err) { notice('Request failed: ' + err.message + '. Press Run again.', true); })
      .then(function () { setBusy(false); });
  });

  // Plain textarea fallback (no CDN): Ctrl/Cmd+Enter still runs the query.
  textarea.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); run(); }
  });

  if (!window.CodeMirror) return;

  editor = CodeMirror.fromTextArea(textarea, {
    mode: 'text/x-mysql',
    lineNumbers: true,
    indentWithTabs: false,
    tabSize: 2,
    viewportMargin: Infinity,
    extraKeys: {
      'Ctrl-Enter': run,
      'Cmd-Enter': run,
      'Ctrl-Space': 'autocomplete'
    },
    hintOptions: { tables: tables, completeSingle: false }
  });
  editor.focus();
})();
</script>
</body>
</html>
