<?php
/**
 * Free SQL playground: type any reading query, run it, see the result.
 * ?sql=... pre-fills the editor (used by the "Open in playground" links).
 *
 * exam.php includes this file with EXAM_MODE defined: same page, but without
 * the navigation (no course links), with an empty editor, and with the
 * database diagram as a popup (the exam URL is the only one students may open).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$exam = defined('EXAM_MODE') && EXAM_MODE;
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$defaultSql = $exam ? '' : "SELECT * FROM Customers LIMIT 10;";
$sqlInput = $isPost ? ($_POST['sql'] ?? '') : ($_GET['sql'] ?? $defaultSql);
$sqlInput = normalizeSql((string) $sqlInput);

// A POST needs MySQL: take a slot first (503 + retry when all are busy).
$slot = $isPost ? acquireDbSlot($config['DB_SLOTS']) : true;
if ($slot === null) {
    sendBusyIfFetch();
}

$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, $isPost && $slot !== null, $pdo, $connectError);

$results = [];
$resultsHtml = '';
if ($isPost) {
    if ($slot === null) {
        $resultsHtml = busyNotice();
    } else {
        if ($pdo !== null) {
            $results = runScript($pdo, $sqlInput);
        }
        $resultsHtml = renderResults($results, $connectError);
    }
    $pdo = null;              // close the connection before rendering the page
    releaseDbSlot($slot);
}

if (isFetchRequest()) {
    sendFragment($resultsHtml);
}

pageStart($exam ? 'Exam' : 'Playground', $exam ? 'exam' : 'playground', renderSchemaSidebar($schema, [], $exam), true);
?>
  <form method="post" id="sqlform" class="sqlform">
    <textarea name="sql" id="sql" spellcheck="false" placeholder="SELECT ..."><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="run">Run</button>
      <button type="button" class="secondary editor-only" data-cmd="undo" title="Undo (Ctrl+Z / ⌘+Z)">Undo</button>
      <button type="button" class="secondary editor-only" data-cmd="redo" title="Redo (Ctrl+Y / ⇧⌘+Z)">Redo</button>
      <button type="button" class="secondary editor-only" data-cmd="format" title="Format the query (⇧Ctrl+F / ⇧⌘+F)">Format</button>
      <button type="button" class="secondary" data-cmd="save" data-filename="<?= $exam ? 'exam' : 'playground' ?>.sql" title="Download the editor's text as a .sql file">Save .sql</button>
      <span class="hint">Ctrl+Enter / ⌘+Enter runs · ⇧Ctrl+F / ⇧⌘+F formats · several statements separated by <code>;</code> are run one after another · max <?= MAX_ROWS ?> rows shown</span>
    </div>
  </form>

  <div id="results">
    <?= $resultsHtml ?>
  </div>
<?php
pageEnd(hintTables($schema), 'run');
