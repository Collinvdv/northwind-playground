<?php
/**
 * Free SQL playground: type any reading query, run it, see the result.
 * ?sql=... pre-fills the editor (used by the "Open in playground" links).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$sqlInput = $isPost ? ($_POST['sql'] ?? '') : ($_GET['sql'] ?? "SELECT * FROM Customers LIMIT 10;");
$sqlInput = normalizeSql((string) $sqlInput);

$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, $isPost, $pdo, $connectError);

$results = [];
if ($isPost && $pdo !== null) {
    $results = runScript($pdo, $sqlInput);
}

if (isFetchRequest()) {
    sendFragment(renderResults($results, $connectError));
}

pageStart('Playground', 'playground', renderSchemaSidebar($schema), true);
?>
  <form method="post" id="sqlform" class="sqlform">
    <textarea name="sql" id="sql" spellcheck="false"><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="run">Run</button>
      <button type="button" class="secondary editor-only" data-cmd="undo" title="Undo (Ctrl+Z / ⌘+Z)">Undo</button>
      <button type="button" class="secondary editor-only" data-cmd="redo" title="Redo (Ctrl+Y / ⇧⌘+Z)">Redo</button>
      <button type="button" class="secondary editor-only" data-cmd="format" title="Format the query (⇧Ctrl+F / ⇧⌘+F)">Format</button>
      <span class="hint">Ctrl+Enter / ⌘+Enter runs · ⇧Ctrl+F / ⇧⌘+F formats · several statements separated by <code>;</code> are run one after another · max <?= MAX_ROWS ?> rows shown</span>
    </div>
  </form>

  <div id="results">
    <?= renderResults($results, $connectError) ?>
  </div>
<?php
pageEnd(hintTables($schema), 'run');
