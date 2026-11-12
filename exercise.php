<?php
/**
 * One exercise: task, editor, Check (compare with the reference solution) or
 * Run only (no comparison), hint and solution, previous/next.
 *
 * An exercise with 'type' => 'erd' asks for a design instead of a query: the
 * student draws entities, attributes, keys and links in the editor of
 * assets/erd-editor.js, which keeps the design as text (the notation of
 * parseErd()) in the textarea. Check compares it with the model design
 * (checkErdExercise). No database is involved.
 *
 * POST sql=... action=check|run. With the X-Requested-With: fetch header only
 * the results block is returned (see assets/app.js).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$c = (int) ($_GET['c'] ?? 0);
$e = (int) ($_GET['e'] ?? 0);
$chapter = getLesson($c);
$label = $chapter['label'] ?? '';
$exercise = getExercise($c, $e);
if ($chapter === null || $exercise === null) {
    notFound('Exercise');
}

$isErd = $exercise['type'] === 'erd';   // the student writes a design instead of a query: no database needed
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = ($_POST['action'] ?? 'check') === 'run' ? 'run' : 'check';
$sqlInput = $isPost ? ($_POST['sql'] ?? '') : ($exercise['starter'] ?? '');
$sqlInput = answerText($exercise, (string) $sqlInput);

// A POST needs MySQL: take a slot first (503 + retry when all are busy).
$slot = $isPost && !$isErd ? acquireDbSlot($config['DB_SLOTS']) : true;
if ($slot === null) {
    sendBusyIfFetch();
}

$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, $isPost && !$isErd && $slot !== null, $pdo, $connectError);

$resultsHtml = '';
if ($isErd) {
    // The editor (assets/erd-editor.js) shows the design itself and sends visual=1: the verdict is enough.
    $resultsHtml = $isPost ? renderErdCheck(checkErdExercise($exercise, $sqlInput), empty($_POST['visual'])) : '';
} elseif ($isPost) {
    if ($slot === null) {
        $resultsHtml = busyNotice();
    } elseif ($pdo === null) {
        $resultsHtml = renderResults([], $connectError);
    } elseif ($action === 'run') {
        $resultsHtml = renderResults(runScript($pdo, $sqlInput), null);
    } else {
        $resultsHtml = renderCheck(checkExercise($pdo, $exercise, $sqlInput));
    }
    $pdo = null;              // close the connection before rendering the page
    releaseDbSlot($slot);
}

if (isFetchRequest()) {
    sendFragment($resultsHtml);
}

// Sidebar: a compact numbered grid of this lesson's exercises, then the
// table list, with the tables that the task mentions already opened.
$sidebar = '<h2>' . $label . ' · ' . h($chapter['title']) . '</h2><nav class="exnav" aria-label="Exercises">';
foreach ($chapter['exercises'] as $ex) {
    $sidebar .= '<a href="exercise.php?c=' . $c . '&amp;e=' . $ex['number'] . '" title="' . h($ex['title']) . '" data-fav="' . $c . '.' . $ex['number'] . '"'
        . ($ex['number'] === $e ? ' class="current" aria-current="page"' : '') . '>' . $ex['number'] . '</a>';
}
$sidebar .= '</nav>';
if ($isErd) {
    $sidebar .= renderErdHelp();
} else {
    $mentioned = mentionedTables($schema, strip_tags($exercise['task']));
    $sidebar .= renderSchemaSidebar($schema, $mentioned);
}

$prev = $chapter['exercises'][$e - 2] ?? null;
$next = $chapter['exercises'][$e] ?? null;
$nextChapter = getLesson($c + 1);

pageStart('Exercise ' . $label . '.' . $e . ': ' . $exercise['title'], 'home', $sidebar, !$isErd);
?>
  <div class="breadcrumb">
    <a href="index.php">Course</a> › <a href="index.php#chapter-<?= $chapter['chapter'] ?>">Chapter <?= $chapter['chapter'] ?>: <?= h($chapter['chapterTitle']) ?></a> › <a href="chapter.php?c=<?= $c ?>"><?= $label ?> <?= h($chapter['title']) ?></a> › Exercise <?= $e ?> of <?= count($chapter['exercises']) ?>
  </div>
  <h1><?= $label ?>.<?= $e ?> <?= h($exercise['title']) ?>
    <button type="button" class="fav-toggle" data-fav="<?= $c ?>.<?= $e ?>" data-fav-title="<?= $label ?>.<?= $e ?> <?= h($exercise['title']) ?>" hidden>☆</button></h1>

  <div class="task">
    <?= lessonHtml($exercise['task'], $schema) ?>
  </div>

  <?php if ($isErd): ?>
  <form method="post" id="sqlform" class="sqlform erdform">
    <textarea name="sql" id="sql" spellcheck="false" autocapitalize="off" autocomplete="off" placeholder="Teams: TeamID PK, Name&#10;Players: PlayerID PK, Name, TeamID &gt; Teams"><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="check">Check</button>
      <span class="hint">Drag an entity onto the canvas, add attributes, mark the key, link the foreign keys · Ctrl+Enter / ⌘+Enter checks</span>
    </div>
  </form>
  <?php else: ?>
  <form method="post" id="sqlform" class="sqlform">
    <textarea name="sql" id="sql" spellcheck="false" placeholder="SELECT ..."><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="check">Check</button>
      <button type="submit" name="action" value="run" class="secondary">Run only</button>
      <button type="button" class="secondary editor-only" data-cmd="undo" title="Undo (Ctrl+Z / ⌘+Z)">Undo</button>
      <button type="button" class="secondary editor-only" data-cmd="redo" title="Redo (Ctrl+Y / ⇧⌘+Z)">Redo</button>
      <button type="button" class="secondary editor-only" data-cmd="format" title="Format the query (⇧Ctrl+F / ⇧⌘+F)">Format</button>
      <button type="button" class="secondary" data-cmd="save" data-filename="exercise-<?= $c ?>-<?= $e ?>.sql" title="Download the editor's text as a .sql file">Save .sql</button>
      <span class="hint">Ctrl+Enter / ⌘+Enter checks · ⇧Ctrl+F / ⇧⌘+F formats · one statement per exercise</span>
    </div>
  </form>
  <?php endif; ?>

  <?php if (!empty($exercise['hint'])): ?>
    <details class="help">
      <summary>Hint</summary>
      <p><?= $exercise['hint'] ?></p>
    </details>
  <?php endif; ?>
  <details class="help">
    <summary>Show solution</summary>
    <pre><code><?= h($exercise['solution']) ?></code></pre>
    <?php if ($isErd): ?>
      <?= lessonHtml('<erd>' . h($exercise['solution']) . '</erd>', $schema) ?>
      <p class="hint">Read it, close it, and write it yourself. The order of tables and columns is free.</p>
    <?php else: ?>
      <p class="hint">Read it, close it, and write it yourself. Other correct queries are accepted too.</p>
    <?php endif; ?>
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
        <a href="chapter.php?c=<?= $nextChapter['number'] ?>">Next lesson: <?= $nextChapter['label'] ?> <?= h($nextChapter['title']) ?> →</a>
      <?php else: ?>
        <a href="index.php">Back to the course →</a>
      <?php endif; ?>
    </span>
  </div>
<?php
$isErd
    ? pageEnd(null, 'check', false, ['favourites.js', 'erd-editor.js'])
    : pageEnd(hintTables($schema), 'check', false, ['favourites.js']);
