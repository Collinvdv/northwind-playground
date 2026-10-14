<?php
/**
 * One exercise: task, editor, Check (compare with the reference solution) or
 * Run only (no comparison), hint and solution, previous/next.
 *
 * POST sql=... action=check|run. With the X-Requested-With: fetch header only
 * the results block is returned (see assets/app.js).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$c = (int) ($_GET['c'] ?? 0);
$e = (int) ($_GET['e'] ?? 0);
$chapter = getChapter($c);
$exercise = getExercise($c, $e);
if ($chapter === null || $exercise === null) {
    notFound('Exercise');
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = ($_POST['action'] ?? 'check') === 'run' ? 'run' : 'check';
$sqlInput = $isPost ? ($_POST['sql'] ?? '') : ($exercise['starter'] ?? '');
$sqlInput = normalizeSql((string) $sqlInput);

$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, $isPost, $pdo, $connectError);

$resultsHtml = '';
if ($isPost) {
    if ($pdo === null) {
        $resultsHtml = renderResults([], $connectError);
    } elseif ($action === 'run') {
        $resultsHtml = renderResults(runScript($pdo, $sqlInput), null);
    } else {
        $resultsHtml = renderCheck(checkExercise($pdo, $exercise, $sqlInput));
    }
}

if (isFetchRequest()) {
    sendFragment($resultsHtml);
}

// Sidebar: this chapter's exercises, then the table list.
$sidebar = '<h2>Chapter ' . $c . ' · ' . h($chapter['title']) . '</h2><ol class="exercises">';
foreach ($chapter['exercises'] as $ex) {
    $sidebar .= '<li><a href="exercise.php?c=' . $c . '&amp;e=' . $ex['number'] . '"'
        . ($ex['number'] === $e ? ' class="current"' : '') . '>' . h($ex['title']) . '</a></li>';
}
$sidebar .= '</ol>' . renderSchemaSidebar($schema);

$prev = $chapter['exercises'][$e - 2] ?? null;
$next = $chapter['exercises'][$e] ?? null;
$nextChapter = getChapter($c + 1);

pageStart('Exercise ' . $c . '.' . $e . ': ' . $exercise['title'], 'home', $sidebar, true);
?>
  <div class="breadcrumb">
    <a href="index.php">Course</a> › <a href="chapter.php?c=<?= $c ?>">Chapter <?= $c ?>: <?= h($chapter['title']) ?></a> › Exercise <?= $e ?> of <?= count($chapter['exercises']) ?>
  </div>
  <h1><?= $c ?>.<?= $e ?> <?= h($exercise['title']) ?></h1>

  <div class="task">
    <?= $exercise['task'] ?>
  </div>

  <form method="post" id="sqlform" class="sqlform">
    <textarea name="sql" id="sql" spellcheck="false" placeholder="SELECT ..."><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="check">Check</button>
      <button type="submit" name="action" value="run" class="secondary">Run only</button>
      <button type="button" class="secondary editor-only" data-cmd="undo" title="Undo (Ctrl+Z / ⌘+Z)">Undo</button>
      <button type="button" class="secondary editor-only" data-cmd="redo" title="Redo (Ctrl+Y / ⇧⌘+Z)">Redo</button>
      <button type="button" class="secondary editor-only" data-cmd="format" title="Format the query (⇧Ctrl+F / ⇧⌘+F)">Format</button>
      <span class="hint">Ctrl+Enter / ⌘+Enter checks · ⇧Ctrl+F / ⇧⌘+F formats · one statement per exercise</span>
    </div>
  </form>

  <?php if (!empty($exercise['hint'])): ?>
    <details class="help">
      <summary>Hint</summary>
      <p><?= $exercise['hint'] ?></p>
    </details>
  <?php endif; ?>
  <details class="help">
    <summary>Show solution</summary>
    <pre><code><?= h($exercise['solution']) ?></code></pre>
    <p class="hint">Read it, close it, and write it yourself. Other correct queries are accepted too.</p>
  </details>

  <div id="results">
    <?= $resultsHtml ?>
  </div>

  <div class="pager">
    <span><?php if ($prev): ?><a href="exercise.php?c=<?= $c ?>&amp;e=<?= $prev['number'] ?>">← <?= h($prev['title']) ?></a><?php endif; ?></span>
    <span>
      <?php if ($next): ?>
        <a href="exercise.php?c=<?= $c ?>&amp;e=<?= $next['number'] ?>"><?= h($next['title']) ?> →</a>
      <?php elseif ($nextChapter): ?>
        <a href="chapter.php?c=<?= $nextChapter['number'] ?>">Next chapter: <?= h($nextChapter['title']) ?> →</a>
      <?php else: ?>
        <a href="index.php">Back to the course →</a>
      <?php endif; ?>
    </span>
  </div>
<?php
pageEnd(hintTables($schema), 'check');
