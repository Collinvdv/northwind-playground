<?php
/**
 * One lesson of a chapter: theory, examples, list of exercises.
 * (c= is the lesson's number through the whole course, see loadCourse().)
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$c = (int) ($_GET['c'] ?? 0);
$chapter = getLesson($c);
if ($chapter === null) {
    notFound('Lesson');
}
$prev = getLesson($c - 1);
$next = getLesson($c + 1);
$label = $chapter['label'];

// The cached table list: <erd tables="..."> in the theory draws real tables.
$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, false, $pdo, $connectError);
$pdo = null;

pageStart($label . ' ' . $chapter['title'], 'home');
?>
  <div class="breadcrumb"><a href="index.php">Course</a> › <a href="index.php#chapter-<?= $chapter['chapter'] ?>">Chapter <?= $chapter['chapter'] ?>: <?= h($chapter['chapterTitle']) ?></a> › Lesson <?= $label ?></div>
  <h1><?= $label ?> <?= h($chapter['title']) ?></h1>
  <p class="lead"><?= h($chapter['summary']) ?></p>

  <div class="theory">
    <?= lessonHtml($chapter['intro'], $schema) ?>
  </div>

  <?php if ($chapter['examples']): ?>
    <h2>Examples</h2>
    <?php if (!$chapter['runnable']): ?>
      <p class="hint">The course database is read-only, so these statements cannot run in the playground. Try them on a MySQL database of your own.</p>
    <?php endif; ?>
    <?php foreach ($chapter['examples'] as $ex): ?>
      <div class="example">
        <div class="head">
          <strong><?= h($ex['title']) ?></strong>
          <?php if ($chapter['runnable']): ?><a href="playground.php?sql=<?= rawurlencode($ex['sql']) ?>">Open in playground →</a><?php endif; ?>
        </div>
        <pre><code><?= h($ex['sql']) ?></code></pre>
        <?php if (!empty($ex['note'])): ?><div class="note"><?= lessonHtml($ex['note'], $schema) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($chapter['exercises']): ?>
  <h2>Exercises</h2>
  <ol class="exercise-list">
    <?php foreach ($chapter['exercises'] as $ex): ?>
      <li><a href="exercise.php?c=<?= $c ?>&amp;e=<?= $ex['number'] ?>"><?= h($ex['title']) ?></a>
        <button type="button" class="fav-toggle" data-fav="<?= $c ?>.<?= $ex['number'] ?>" data-fav-title="<?= $label ?>.<?= $ex['number'] ?> <?= h($ex['title']) ?>" hidden>☆</button></li>
    <?php endforeach; ?>
  </ol>
    <p><a href="exercise.php?c=<?= $c ?>&amp;e=1"><button type="button">Start exercise 1</button></a></p>
  <?php endif; ?>

  <div class="pager">
    <span><?php if ($prev): ?><a href="chapter.php?c=<?= $prev['number'] ?>">← <?= $prev['label'] ?> <?= h($prev['title']) ?></a><?php endif; ?></span>
    <span><?php if ($next): ?><a href="chapter.php?c=<?= $next['number'] ?>"><?= $next['label'] ?> <?= h($next['title']) ?> →</a><?php endif; ?></span>
  </div>
<?php
pageEnd(null, 'run', false, ['favourites.js']);
