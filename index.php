<?php
/**
 * Course home: one card per chapter, plus the playground.
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$chapters = loadChapters();
$total = array_sum(array_map(fn($c) => count($c['exercises']), $chapters));

pageStart('Course', 'home');
?>
  <h1>Learn SQL with Northwind</h1>
  <p class="lead">
    <?= count($chapters) ?> chapters, <?= $total ?> exercises on the classic Northwind database
    (customers, orders, products, employees). Every chapter explains one topic with runnable
    examples, then lets you solve exercises one by one. Your query is checked against the
    expected result. Each exercise has a hint and a solution. Use the playground to try anything.
  </p>

  <div class="cards">
    <?php foreach ($chapters as $ch): ?>
      <a class="card" href="chapter.php?c=<?= $ch['number'] ?>">
        <span class="num">Chapter <?= $ch['number'] ?></span>
        <h2><?= h($ch['title']) ?></h2>
        <p><?= h($ch['summary']) ?></p>
        <span class="meta"><?= count($ch['exercises']) ?> exercises · <?= count($ch['examples']) ?> examples</span>
      </a>
    <?php endforeach; ?>
    <a class="card playground" href="playground.php">
      <span class="num">Free practice</span>
      <h2>Playground</h2>
      <p>Run any SELECT on the whole database. Several statements, autocomplete, table list.</p>
      <span class="meta">Open playground</span>
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
      <li>When your answer is wrong you see your result and the expected result side by side.</li>
      <li>Stuck? Open the <strong>hint</strong>. Still stuck? Open the <strong>solution</strong>, read it, then
        try to write it yourself again.</li>
    </ul>
  </div>
<?php
pageEnd();
