<?php
/**
 * Course home: per chapter one card per lesson, plus the playground and the diagram.
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$course = loadCourse();
$lessons = loadLessons();
$total = array_sum(array_map(fn($l) => count($l['exercises']), $lessons));

pageStart('Course', 'home');
?>
  <h1>Learn SQL with Northwind</h1>
  <p class="lead">
    <?= count($course) ?> chapters, <?= count($lessons) ?> lessons, <?= $total ?> exercises on the classic Northwind database
    (customers, orders, products, employees). Every lesson explains one topic with
    examples, then lets you solve exercises one by one. Your query is checked against the
    expected result. Each exercise has a hint and a solution. Use the playground to try anything.
  </p>

  <section id="favourites" hidden>
    <h2>Favourite exercises</h2>
    <ol class="exercise-list"></ol>
  </section>

  <?php foreach ($course as $ch): ?>
    <section class="chapter" id="chapter-<?= $ch['number'] ?>">
      <h2><span class="num">Chapter <?= $ch['number'] ?></span> <?= h($ch['title']) ?></h2>
      <p class="lead"><?= h($ch['summary']) ?></p>
      <div class="cards">
        <?php foreach ($ch['lessons'] as $l): ?>
          <a class="card" href="chapter.php?c=<?= $l['number'] ?>">
            <span class="num">Lesson <?= $l['label'] ?></span>
            <h2><?= h($l['title']) ?></h2>
            <p><?= h($l['summary']) ?></p>
            <span class="meta"><?= $l['exercises'] ? count($l['exercises']) . ' exercises' : 'Theory' ?> · <?= count($l['examples']) ?> examples</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <h2>Tools</h2>
  <div class="cards">
    <a class="card playground" href="playground.php">
      <span class="num">Free practice</span>
      <h2>Playground</h2>
      <p>Run any SELECT on the whole database. Several statements, autocomplete, table list.</p>
      <span class="meta">Open playground</span>
    </a>
    <a class="card playground" href="erd.php" data-erd-open>
      <span class="num">Reference</span>
      <h2>Database diagram</h2>
      <p>All tables with their primary and foreign keys, and the lines between them: which columns to join on.</p>
      <span class="meta">Open diagram</span>
    </a>
  </div>

  <h2>How checking works</h2>
  <div class="theory">
    <ul>
      <li><strong>Check</strong> runs your query and the reference solution, then compares the two result sets:
        same number of columns, same number of rows, same values. Column names and row order do not
        matter unless the exercise says so (exercises about <code>ORDER BY</code> check the order, exercises
        about aliases check the names).</li>
      <li><strong>Run only</strong> runs your query without comparing, like the playground.</li>
      <li>In a <strong>design exercise</strong> (chapter 2) you draw an ERD: drag entities onto a canvas, add
        attributes, mark the keys and link the foreign keys. Check compares your entities, attributes, primary keys and
        foreign keys with a model design.</li>
      <li>When your answer is wrong you see your result and the expected result side by side.</li>
      <li>Stuck? Open the <strong>hint</strong>. Still stuck? Open the <strong>solution</strong>, read it, then
        try to write it yourself again.</li>
    </ul>
  </div>
  <?php
  // Current titles of all exercises, so that favourites stored under an older
  // numbering or title are listed with the present one (see favourites.js).
  $favTitles = [];
  foreach ($lessons as $l) {
      foreach ($l['exercises'] as $ex) {
          $favTitles[$l['number'] . '.' . $ex['number']] = $l['label'] . '.' . $ex['number'] . ' ' . $ex['title'];
      }
  }
  ?>
  <script>window.NW_FAV_TITLES = <?= json_encode($favTitles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php
pageEnd(null, 'run', false, ['favourites.js']);
