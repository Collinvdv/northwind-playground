<?php
return [
    'title'    => 'ALTER TABLE and DROP TABLE: changing the structure',
    'summary'  => 'Add, change and remove columns and constraints on an existing table, and remove tables.',
    'runnable' => false,
    'intro'    => <<<'HTML'
<h3>Data or structure?</h3>
<p><code>INSERT</code>, <code>UPDATE</code> and <code>DELETE</code> change the <strong>rows</strong> of a table.
<code>CREATE</code>, <code>ALTER</code> and <code>DROP</code> change the <strong>table itself</strong>: its columns and constraints.</p>
<table>
<tr><th></th><th>Structure (DDL)</th><th>Data (DML)</th></tr>
<tr><td>Make</td><td><code>CREATE TABLE</code></td><td><code>INSERT</code></td></tr>
<tr><td>Change</td><td><code>ALTER TABLE</code></td><td><code>UPDATE</code></td></tr>
<tr><td>Remove</td><td><code>DROP TABLE</code></td><td><code>DELETE</code></td></tr>
</table>

<h3>Adding a column</h3>
<pre><code>ALTER TABLE Customers
ADD COLUMN Email VARCHAR(100);</code></pre>
<p>Existing rows get <code>NULL</code> in the new column, or the default when you give one. A new <code>NOT NULL</code> column therefore
needs a <code>DEFAULT</code>:</p>
<pre><code>ALTER TABLE Products
ADD COLUMN IsOrganic BOOLEAN NOT NULL DEFAULT FALSE;</code></pre>

<h3>Changing and removing a column</h3>
<pre><code>ALTER TABLE Customers MODIFY COLUMN Email VARCHAR(150) NOT NULL;   -- new type or options
ALTER TABLE Customers RENAME COLUMN Email TO EmailAddress;         -- new name
ALTER TABLE Customers DROP COLUMN Fax;                             -- column and its data are gone</code></pre>
<p><code>MODIFY</code> restates the <strong>whole</strong> column definition: what you leave out (such as <code>NOT NULL</code>) is dropped.
A change is refused when the existing data does not fit: making a column <code>NOT NULL</code> while rows have <code>NULL</code> in it,
or shortening a <code>VARCHAR</code> below the longest value.</p>

<h3>Adding and removing constraints</h3>
<pre><code>ALTER TABLE Customers
ADD CONSTRAINT uq_customers_email UNIQUE (EmailAddress);

ALTER TABLE Reviews
ADD CONSTRAINT fk_reviews_products
    FOREIGN KEY (ProductID) REFERENCES Products (ProductID);

ALTER TABLE Reviews
DROP FOREIGN KEY fk_reviews_products;</code></pre>
<p>Give constraints a name with <code>CONSTRAINT name</code>: you need the name to remove them later. Adding a constraint fails when
existing rows break the rule.</p>

<h3>Removing a table</h3>
<pre><code>DROP TABLE Reviews;
DROP TABLE IF EXISTS Reviews;   -- no error when it is not there</code></pre>
<p>The table, all its rows and its constraints are gone, without a question and without <code>ROLLBACK</code>.
A table that foreign keys point to cannot be dropped: drop the tables in the <strong>reverse order</strong> of creating them,
the "many" side first.</p>
<table>
<tr><th>Statement</th><th>Rows</th><th>Table</th></tr>
<tr><td><code>DELETE FROM Reviews WHERE ...</code></td><td>some removed</td><td>stays</td></tr>
<tr><td><code>DELETE FROM Reviews</code> / <code>TRUNCATE TABLE Reviews</code></td><td>all removed</td><td>stays</td></tr>
<tr><td><code>DROP TABLE Reviews</code></td><td>all removed</td><td>removed</td></tr>
</table>

<h3>A script you can run again</h3>
<p>While you are still designing, keep all statements in one <code>.sql</code> file that rebuilds everything from scratch:
drops first (many side first), then creates (one side first), then inserts.</p>
HTML,
    'examples' => [
        [
            'title' => 'A rebuild script',
            'sql'   => "DROP TABLE IF EXISTS ProductTags;\nDROP TABLE IF EXISTS Tags;\n\nCREATE TABLE Tags (\n    TagID INT         NOT NULL AUTO_INCREMENT,\n    Name  VARCHAR(30) NOT NULL UNIQUE,\n    PRIMARY KEY (TagID)\n);\n\nCREATE TABLE ProductTags (\n    ProductID INT NOT NULL,\n    TagID     INT NOT NULL,\n    PRIMARY KEY (ProductID, TagID),\n    FOREIGN KEY (ProductID) REFERENCES Products (ProductID),\n    FOREIGN KEY (TagID)     REFERENCES Tags (TagID) ON DELETE CASCADE\n);\n\nINSERT INTO Tags (Name) VALUES ('Organic'), ('Fair trade');\nINSERT INTO ProductTags (ProductID, TagID) VALUES (1, 1), (1, 2), (2, 1);",
            'note'  => 'Drop in reverse order of creation. Run it as often as you like: the result is always the same.',
        ],
        [
            'title' => 'Add a column, then fill it',
            'sql'   => "ALTER TABLE Products\nADD COLUMN StockValue DECIMAL(12,2);\n\nUPDATE Products\nSET StockValue = UnitPrice * UnitsInStock;",
            'note'  => 'Structure first, data second. (A calculated value like this one is better not stored at all, see chapter 2.)',
        ],
    ],
];
