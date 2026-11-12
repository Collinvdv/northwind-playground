<?php
/**
 * Database diagram (ERD) as a page of the course. The drawing itself lives in
 * lib.php (renderErd), because every other page embeds the same diagram in a
 * popup. assets/erd.js adds hover highlighting, "Fit to window" and "Save .svg".
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, false, $pdo, $connectError);
$pdo = null;

$trySql = "SELECT o.OrderID, c.CompanyName, e.LastName\nFROM Orders o\nJOIN Customers c ON c.CustomerID = o.CustomerID\nJOIN Employees e ON e.EmployeeID = o.EmployeeID\nLIMIT 20;";

pageStart('Diagram', 'erd', null, false, 'erd-page');
?>
  <h1>Database diagram</h1>
  <p class="lead"><?= renderErdLegend($schema, $config['DB_NAME']) ?></p>

  <?php if ($connectError): ?>
    <div class="result"><div class="error">Cannot connect to MySQL: <?= h($connectError) ?></div></div>
  <?php elseif (!$schema['tables']): ?>
    <div class="result"><div class="notice">No tables found.</div></div>
  <?php else: ?>
    <div class="erd-tools">
      <?= renderErdTools(false) ?>
      <span class="hint">Try it: open the <a href="playground.php?sql=<?= rawurlencode($trySql) ?>">join of Orders, Customers and Employees</a> in the playground.</span>
    </div>
    <div class="erd-wrap"><?= renderErd($schema) ?></div>
  <?php endif; ?>
<?php
pageEnd(null, 'run', true);
