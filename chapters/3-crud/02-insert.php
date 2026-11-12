<?php
return [
    'title'    => 'INSERT: adding rows',
    'summary'  => 'One row, several rows, columns you leave out, AUTO_INCREMENT, copying rows with INSERT ... SELECT.',
    'runnable' => false,
    'intro'    => <<<'HTML'
<h3>CRUD</h3>
<p>The four things you do with data are <strong>C</strong>reate, <strong>R</strong>ead, <strong>U</strong>pdate and <strong>D</strong>elete.
Reading is <code>SELECT</code>, the subject of chapter 1. The other three are <code>INSERT</code>, <code>UPDATE</code> and <code>DELETE</code>.</p>

<h3>One row</h3>
<p>Name the table, list the columns, give a value for each, in the same order:</p>
<pre><code>INSERT INTO Shippers (CompanyName, Phone)
VALUES ('Bpost', '(02) 201 23 45');</code></pre>
<p>Text and dates go between single quotes, numbers do not. A quote inside text is doubled: <code>'Chef Anton''s Mix'</code>.</p>

<h3>Columns you leave out</h3>
<p>A column that is not in the list gets its <code>DEFAULT</code> value, or <code>NULL</code> when it has none. Above,
<code>ShipperID</code> is missing: it is <code>AUTO_INCREMENT</code>, so the database fills in the next number.
A <code>NOT NULL</code> column without a default <em>must</em> be in the list.</p>
<p>The column list may be dropped when you give a value for <strong>every</strong> column in table order. Do not: the statement
breaks as soon as a column is added to the table.</p>

<h3>Several rows</h3>
<pre><code>INSERT INTO Region (RegionID, RegionDescription)
VALUES (5, 'Central'),
       (6, 'Overseas');</code></pre>
<p>It is one statement: when one row is refused, none of them is added.</p>

<h3>Constraints say no</h3>
<p>Every constraint is checked for every new row. Typical errors:</p>
<table>
<tr><th>Error</th><th>Cause</th></tr>
<tr><td><code>Duplicate entry '5' for key 'PRIMARY'</code></td><td>The primary key value exists already.</td></tr>
<tr><td><code>Column 'ProductName' cannot be null</code> / <code>Field ... doesn't have a default value</code></td><td>A <code>NOT NULL</code> column got no value.</td></tr>
<tr><td><code>Cannot add or update a child row: a foreign key constraint fails</code></td><td>The foreign key value does not exist in the other table.</td></tr>
<tr><td><code>Data too long for column</code></td><td>Text longer than the <code>VARCHAR</code> allows.</td></tr>
</table>
<p>Because of the foreign keys the <strong>order</strong> matters: first the row on the "one" side (the category), then the rows that
point to it (its products).</p>

<h3>Copying rows: INSERT ... SELECT</h3>
<p>Instead of <code>VALUES</code> you can use a query. Every row of its result is inserted:</p>
<pre><code>INSERT INTO DiscontinuedProducts (ProductID, ProductName, UnitPrice)
SELECT ProductID, ProductName, UnitPrice
FROM Products
WHERE Discontinued = 1;</code></pre>
<p>Run the <code>SELECT</code> on its own first to see what will be added.</p>
HTML,
    'examples' => [
        [
            'title' => 'A new category and a product in it',
            'sql'   => "INSERT INTO Categories (CategoryName, Description)\nVALUES ('Vegan', 'Plant-based products');\n\nINSERT INTO Products (ProductName, CategoryID, UnitPrice, Discontinued)\nVALUES ('Oat drink', LAST_INSERT_ID(), 2.5, 0);",
            'note'  => '<code>LAST_INSERT_ID()</code> returns the <code>AUTO_INCREMENT</code> number that the previous insert handed out.',
        ],
        [
            'title' => 'A row in a link table',
            'sql'   => "INSERT INTO EmployeeTerritories (EmployeeID, TerritoryID)\nVALUES (1, '10019');",
            'note'  => 'Both values must exist already: employee 1 in <code>Employees</code>, territory 10019 in <code>Territories</code>.',
        ],
    ],
];
