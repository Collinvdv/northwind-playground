<?php
return [
    'title'    => 'CREATE TABLE: data types and constraints',
    'summary'  => 'Turn an entity of your ERD into a table: columns, data types, primary key, NOT NULL, DEFAULT, foreign keys.',
    'runnable' => false,
    'intro'    => <<<'HTML'
<h3>From ERD to tables</h3>
<p>Every entity of the diagram becomes one <code>CREATE TABLE</code> statement: the attributes become columns, the keys become
<strong>constraints</strong>, rules that the database enforces on every row.</p>
<pre><code>CREATE TABLE Reviews (
    ReviewID   INT           NOT NULL AUTO_INCREMENT,
    ProductID  INT           NOT NULL,
    CustomerID VARCHAR(5)    NOT NULL,
    Stars      TINYINT       NOT NULL,
    Comment    VARCHAR(500),
    ReviewDate DATE          NOT NULL DEFAULT (CURRENT_DATE),
    PRIMARY KEY (ReviewID),
    FOREIGN KEY (ProductID)  REFERENCES Products (ProductID),
    FOREIGN KEY (CustomerID) REFERENCES Customers (CustomerID),
    CHECK (Stars BETWEEN 1 AND 5)
);</code></pre>
<erd>
Products: ProductID PK int, ProductName varchar(40)
Reviews: ReviewID PK int, ProductID > Products int, CustomerID > Customers varchar(5), Stars tinyint, Comment varchar(500), ReviewDate date
Customers: CustomerID PK varchar(5), CompanyName varchar(40)
</erd>
<p>Between the parentheses: one line per column (name, data type, options), then the constraints, separated by commas.</p>

<h3>Data types</h3>
<table>
<tr><th>Type</th><th>Holds</th><th>Example</th></tr>
<tr><td><code>INT</code></td><td>Whole numbers (also <code>TINYINT</code>, <code>SMALLINT</code>, <code>BIGINT</code>)</td><td><code>42</code></td></tr>
<tr><td><code>DECIMAL(10,2)</code></td><td>Exact numbers: 10 digits in total, 2 after the point. Use it for money.</td><td><code>19.99</code></td></tr>
<tr><td><code>FLOAT</code>, <code>DOUBLE</code></td><td>Approximate numbers (measurements). Never for money.</td><td><code>3.14159</code></td></tr>
<tr><td><code>VARCHAR(40)</code></td><td>Text of at most 40 characters</td><td><code>'Chai'</code></td></tr>
<tr><td><code>TEXT</code></td><td>Long text</td><td>a description</td></tr>
<tr><td><code>DATE</code></td><td>A day</td><td><code>'2026-11-12'</code></td></tr>
<tr><td><code>DATETIME</code></td><td>A day and a time</td><td><code>'2026-11-12 14:30:00'</code></td></tr>
<tr><td><code>BOOLEAN</code></td><td>True or false (stored as 1 or 0)</td><td><code>TRUE</code></td></tr>
</table>
<p>Pick the smallest type that fits every possible value. A postal code or phone number is text, not a number: you never
calculate with it and it can start with a zero.</p>

<h3>Constraints</h3>
<table>
<tr><th>Constraint</th><th>Rule</th></tr>
<tr><td><code>PRIMARY KEY</code></td><td>Unique and never <code>NULL</code>. One per table; may list several columns for a composite key.</td></tr>
<tr><td><code>NOT NULL</code></td><td>The column must have a value in every row.</td></tr>
<tr><td><code>UNIQUE</code></td><td>No two rows with the same value (an e-mail address).</td></tr>
<tr><td><code>DEFAULT value</code></td><td>The value used when an <code>INSERT</code> does not give one.</td></tr>
<tr><td><code>CHECK (condition)</code></td><td>The condition must hold for every row.</td></tr>
<tr><td><code>FOREIGN KEY ... REFERENCES</code></td><td>The value must exist as a key in the other table.</td></tr>
</table>
<p><code>AUTO_INCREMENT</code> is not a constraint but a convenience: the database hands out the next number itself.</p>

<h3>Foreign keys</h3>
<p>The foreign key column must have the <strong>same data type</strong> as the key it points to (<code>Customers.CustomerID</code> is
<code>VARCHAR(5)</code>, so <code>Reviews.CustomerID</code> is too), and the other table must <strong>exist first</strong>: create the
"one" side before the "many" side.</p>
<p>What happens to the reviews when their product is deleted? Say it with <code>ON DELETE</code>:</p>
<pre><code>FOREIGN KEY (ProductID) REFERENCES Products (ProductID) ON DELETE CASCADE</code></pre>
<table>
<tr><th>Option</th><th>Deleting the product...</th></tr>
<tr><td><code>ON DELETE RESTRICT</code> (the default)</td><td>is refused while it has reviews</td></tr>
<tr><td><code>ON DELETE CASCADE</code></td><td>deletes its reviews too</td></tr>
<tr><td><code>ON DELETE SET NULL</code></td><td>empties the foreign key of its reviews (the column must allow <code>NULL</code>)</td></tr>
</table>

<h3>A link table</h3>
<p>A many-to-many relationship becomes a table with two foreign keys that together form the primary key:</p>
<pre><code>CREATE TABLE ProductTags (
    ProductID INT NOT NULL,
    TagID     INT NOT NULL,
    PRIMARY KEY (ProductID, TagID),
    FOREIGN KEY (ProductID) REFERENCES Products (ProductID),
    FOREIGN KEY (TagID)     REFERENCES Tags (TagID)
);</code></pre>

<h3>Looking at a table</h3>
<p><code>DESCRIBE Products;</code> lists the columns of a table, <code>SHOW CREATE TABLE Products;</code> shows the complete
<code>CREATE TABLE</code> statement with all constraints. Both work in the <a href="playground.php">playground</a>.</p>
HTML,
    'examples' => [
        [
            'title' => 'A table without foreign keys',
            'sql'   => "CREATE TABLE Tags (\n    TagID INT         NOT NULL AUTO_INCREMENT,\n    Name  VARCHAR(30) NOT NULL UNIQUE,\n    PRIMARY KEY (TagID)\n);",
            'note'  => 'Create this one first: <code>ProductTags</code> refers to it.',
        ],
        [
            'title' => 'A one-to-many relationship',
            'sql'   => "CREATE TABLE Warehouses (\n    WarehouseID INT         NOT NULL AUTO_INCREMENT,\n    City        VARCHAR(15) NOT NULL,\n    ManagerID   INT,\n    PRIMARY KEY (WarehouseID),\n    FOREIGN KEY (ManagerID) REFERENCES Employees (EmployeeID) ON DELETE SET NULL\n);",
            'note'  => '<code>ManagerID</code> allows <code>NULL</code>: a warehouse can be without a manager, and it stays when the manager leaves.',
        ],
    ],
];
