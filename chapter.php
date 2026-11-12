<?php
/**
 * One chapter: theory, runnable examples, list of exercises.
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$c = (int) ($_GET['c'] ?? 0);
$chapter = getChapter($c);
if ($chapter === null) {
    notFound('Chapter');
}
$chapters = loadChapters();
$prev = $chapters[$c - 2] ?? null;
$next = $chapters[$c] ?? null;

pageStart('Chapter ' . $c . ': ' . $chapter['title'], 'home');
?>
  <div class="breadcrumb"><a href="index.php">Course</a> › Chapter <?= $c ?></div>
  <h1><?= h($chapter['title']) ?></h1>
  <p class="lead"><?= h($chapter['summary']) ?></p>

  <div class="theory">
    <?= $chapter['intro'] ?>
  </div>

  <?php if ($chapter['examples']): ?>
    <h2>Examples</h2>
    <?php foreach ($chapter['examples'] as $ex): ?>
      <div class="example">
        <div class="head">
          <strong><?= h($ex['title']) ?></strong>
          <a href="playground.php?sql=<?= rawurlencode($ex['sql']) ?>">Open in playground →</a>
        </div>
        <pre><code><?= h($ex['sql']) ?></code></pre>
        <?php if (!empty($ex['note'])): ?><div class="note"><?= $ex['note'] ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2>Exercises</h2>
  <ol class="exercise-list">
    <?php foreach ($chapter['exercises'] as $ex): ?>
      <li><a href="exercise.php?c=<?= $c ?>&amp;e=<?= $ex['number'] ?>"><?= h($ex['title']) ?></a>
        <button type="button" class="fav-toggle" data-fav="<?= $c ?>.<?= $ex['number'] ?>" data-fav-title="<?= $c ?>.<?= $ex['number'] ?> <?= h($ex['title']) ?>" hidden>☆</button></li>
    <?php endforeach; ?>
  </ol>
  <?php if ($chapter['exercises']): ?>
    <p><a href="exercise.php?c=<?= $c ?>&amp;e=1"><button type="button">Start exercise 1</button></a></p>
  <?php endif; ?>

  <div class="pager">
    <span><?php if ($prev): ?><a href="chapter.php?c=<?= $prev['number'] ?>">← <?= h($prev['title']) ?></a><?php endif; ?></span>
    <span><?php if ($next): ?><a href="chapter.php?c=<?= $next['number'] ?>"><?= h($next['title']) ?> →</a><?php endif; ?></span>
  </div>
<?php
pageEnd(null, 'run', false, ['favourites.js']);
