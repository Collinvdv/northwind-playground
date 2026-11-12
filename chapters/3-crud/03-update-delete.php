<?php
return [
    'title'    => 'UPDATE and DELETE: changing and removing rows',
    'summary'  => 'Change values, remove rows, and why the WHERE is the most important line. Transactions as a safety net.',
    'runnable' => false,
    'intro'    => <<<'HTML'
<h3>UPDATE</h3>
<p>Change values in existing rows: the table, the new values, and <strong>which rows</strong>:</p>
<pre><code>UPDATE Products
SET UnitPrice = 20
WHERE ProductID = 1;</code></pre>
<p>Several columns at once are separated by commas, and a new value may be calculated from the old one:</p>
<pre><code>UPDATE Products
SET UnitPrice = UnitPrice * 1.05,
    UnitsOnOrder = 0
WHERE CategoryID = 1;</code></pre>

<h3>The WHERE decides everything</h3>
<p>The <code>WHERE</code> works exactly as in a <code>SELECT</code>. <strong>Without a <code>WHERE</code> every row of the table is changed</strong>,
and there is no undo button. Make it a habit:</p>
<ol>
<li>Write a <code>SELECT</code> with the <code>WHERE</code> you plan to use and check that it returns exactly the rows you mean.</li>
<li>Turn it into the <code>UPDATE</code> or <code>DELETE</code>, keeping the <code>WHERE</code>.</li>
<li>Read the answer of the database: <code>3 rows affected</code> should be the number you expected.</li>
</ol>
<p>To change one row, filter on its primary key: that can never hit a second row.</p>

<h3>DELETE</h3>
<p>Removes whole rows. There is no column list: a row is there or it is not.</p>
<pre><code>DELETE FROM Shippers
WHERE ShipperID = 4;</code></pre>
<p>To empty one column, use <code>UPDATE ... SET column = NULL</code>. <code>DELETE FROM Shippers;</code> without a <code>WHERE</code>
empties the whole table.</p>

<h3>Foreign keys protect rows</h3>
<p>A row that other rows point to cannot simply disappear. Deleting a customer who has orders gives
<code>Cannot delete or update a parent row: a foreign key constraint fails</code>, unless the foreign key was created with
<code>ON DELETE CASCADE</code> or <code>SET NULL</code> (see lesson 3.1). Delete the "many" side first:</p>
<pre><code>DELETE FROM OrderDetails WHERE OrderID = 10248;
DELETE FROM Orders WHERE OrderID = 10248;</code></pre>

<h3>A safety net: transactions</h3>
<p>A <strong>transaction</strong> groups statements so that they succeed or fail together. Until you say <code>COMMIT</code>,
you can take everything back with <code>ROLLBACK</code>:</p>
<pre><code>START TRANSACTION;

UPDATE Products SET UnitPrice = UnitPrice * 1.05 WHERE CategoryID = 1;
SELECT ProductName, UnitPrice FROM Products WHERE CategoryID = 1;   -- look at the result

ROLLBACK;   -- not what you wanted: undo
-- or
COMMIT;     -- fine: make it permanent</code></pre>
<p>Transactions also keep data consistent: moving stock from one product to another is two updates that must both happen or
neither. <code>CREATE</code>, <code>ALTER</code> and <code>DROP</code> cannot be rolled back.</p>
HTML,
    'examples' => [
        [
            'title' => 'Check first, then change',
            'sql'   => "SELECT ProductID, ProductName, UnitsInStock\nFROM Products\nWHERE UnitsInStock = 0 AND Discontinued = 0;\n\nUPDATE Products\nSET Discontinued = 1\nWHERE UnitsInStock = 0 AND Discontinued = 0;",
            'note'  => 'The same <code>WHERE</code> twice: the <code>SELECT</code> shows which rows the <code>UPDATE</code> will touch.',
        ],
        [
            'title' => 'Delete rows chosen with a subquery',
            'sql'   => "DELETE FROM EmployeeTerritories\nWHERE TerritoryID IN (SELECT TerritoryID FROM Territories WHERE RegionID = 4);",
            'note'  => 'Everything that works in the <code>WHERE</code> of a <code>SELECT</code> works here.',
        ],
    ],
];
