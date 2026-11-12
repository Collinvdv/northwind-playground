<?php
return [
    'title'   => 'WHERE: filtering rows',
    'summary' => 'Keep only the rows you want: comparisons, AND/OR/NOT, IN, BETWEEN, LIKE, NULL, dates.',
    'intro'   => <<<'HTML'
<h3>Conditions</h3>
<p><code>WHERE</code> comes after <code>FROM</code> and keeps only the rows for which the condition is true:</p>
<pre><code>SELECT CompanyName, City
FROM Customers
WHERE Country = 'Germany';</code></pre>
<p>Text goes between <strong>single quotes</strong> and is compared as it is spelled (<code>'germany'</code> also matches in MySQL,
because the default collation ignores case, but write it the way it is stored). Numbers and dates are compared with
<code>=</code>, <code>&lt;&gt;</code> (not equal), <code>&lt;</code>, <code>&lt;=</code>, <code>&gt;</code>, <code>&gt;=</code>.
Dates are written as <code>'1997-12-31'</code>.</p>

<h3>Combining conditions</h3>
<p><code>AND</code> needs both, <code>OR</code> needs at least one, <code>NOT</code> turns a condition around. Use parentheses
when you mix <code>AND</code> and <code>OR</code>, because <code>AND</code> binds stronger:</p>
<pre><code>WHERE (Country = 'Germany' OR Country = 'Austria') AND City &lt;&gt; 'Berlin'</code></pre>

<h3>Shortcuts</h3>
<table>
<tr><th>Operator</th><th>Meaning</th><th>Example</th></tr>
<tr><td><code>IN (...)</code></td><td>equal to one of the values</td><td><code>Country IN ('Germany', 'Austria', 'Switzerland')</code></td></tr>
<tr><td><code>BETWEEN a AND b</code></td><td>a ≤ value ≤ b (both ends included)</td><td><code>UnitPrice BETWEEN 10 AND 20</code></td></tr>
<tr><td><code>LIKE</code></td><td>text pattern: <code>%</code> = any text, <code>_</code> = one character</td><td><code>ProductName LIKE 'Ch%'</code></td></tr>
<tr><td><code>IS NULL</code> / <code>IS NOT NULL</code></td><td>the value is missing / present</td><td><code>ShippedDate IS NULL</code></td></tr>
</table>
<p><strong>NULL</strong> means "no value". It is not 0 and not an empty string, and <code>= NULL</code> is never true. Always use <code>IS NULL</code>.</p>

<h3>Dates</h3>
<p>Compare a date column with a date constant, or take a part out of it with <code>YEAR()</code>, <code>MONTH()</code>, <code>DAY()</code>:</p>
<pre><code>WHERE OrderDate &gt;= '1998-01-01'
WHERE YEAR(OrderDate) = 1997 AND MONTH(OrderDate) = 12</code></pre>
HTML,
    'examples' => [
        [
            'title' => 'Text comparison',
            'sql'   => "SELECT CompanyName, City\nFROM Customers\nWHERE Country = 'Germany';",
            'note'  => '11 German customers.',
        ],
        [
            'title' => 'AND / OR with parentheses',
            'sql'   => "SELECT ProductName, CategoryID, UnitPrice\nFROM Products\nWHERE (CategoryID = 1 OR CategoryID = 2) AND UnitPrice > 20;",
            'note'  => 'Remove the parentheses and see how the result changes: <code>AND</code> is evaluated before <code>OR</code>.',
        ],
        [
            'title' => 'LIKE and NULL',
            'sql'   => "SELECT CompanyName, Region\nFROM Customers\nWHERE CompanyName LIKE 'B%' AND Region IS NULL;",
            'note'  => 'Customers whose name starts with B and that have no region.',
        ],
        [
            'title' => 'Dates',
            'sql'   => "SELECT OrderID, OrderDate, ShippedDate\nFROM Orders\nWHERE OrderDate BETWEEN '1997-12-01' AND '1997-12-31';",
            'note'  => 'Orders from December 1997. <code>YEAR(OrderDate) = 1997 AND MONTH(OrderDate) = 12</code> gives the same rows.',
        ],
    ],
    'exercises' => [
        [
            'title'    => 'Customers in France',
            'task'     => '<p>Show <code>CompanyName</code> and <code>City</code> of all customers in France.</p>',
            'hint'     => "<code>WHERE Country = 'France'</code>. Single quotes around text.",
            'solution' => "SELECT CompanyName, City\nFROM Customers\nWHERE Country = 'France';",
        ],
        [
            'title'    => 'Employees in London',
            'task'     => '<p>Show <code>FirstName</code>, <code>LastName</code> and <code>Title</code> of the employees whose <code>City</code> is London.</p>',
            'hint'     => "Same idea as the previous exercise, but on the <code>Employees</code> table: <code>WHERE City = 'London'</code>.",
            'solution' => "SELECT FirstName, LastName, Title\nFROM Employees\nWHERE City = 'London';",
        ],
        [
            'title'    => 'Expensive products',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the products that cost more than 50.</p>',
            'hint'     => 'Compare the price with a number: <code>UnitPrice &gt; 50</code>. Numbers do not get quotes.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE UnitPrice > 50;",
        ],
        [
            'title'    => 'Products out of stock',
            'task'     => '<p>Show <code>ProductID</code> and <code>ProductName</code> of the products with exactly 0 units in stock (<code>UnitsInStock</code>).</p>',
            'hint'     => '<code>UnitsInStock = 0</code>. Zero is a value, not <code>NULL</code>.',
            'solution' => "SELECT ProductID, ProductName\nFROM Products\nWHERE UnitsInStock = 0;",
        ],
        [
            'title'    => 'Discontinued products',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the products that are discontinued (<code>Discontinued</code> is 1).</p>',
            'hint'     => '<code>Discontinued</code> is a bit column: 1 means true, 0 means false. <code>WHERE Discontinued = 1</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE Discontinued = 1;",
        ],
        [
            'title'    => 'Cheap drinks',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the products in category 1 (Beverages) that cost less than 10.</p>',
            'hint'     => 'Two conditions that must both be true: <code>CategoryID = 1 AND UnitPrice &lt; 10</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE CategoryID = 1 AND UnitPrice < 10;",
        ],
        [
            'title'    => 'German-speaking countries',
            'task'     => '<p>Show <code>CompanyName</code> and <code>Country</code> of the customers in Germany, Austria or Switzerland.</p>',
            'hint'     => "<code>Country IN ('Germany', 'Austria', 'Switzerland')</code>, or three conditions with <code>OR</code>.",
            'solution' => "SELECT CompanyName, Country\nFROM Customers\nWHERE Country IN ('Germany', 'Austria', 'Switzerland');",
        ],
        [
            'title'    => 'Freight between 500 and 1000',
            'task'     => '<p>Show <code>OrderID</code> and <code>Freight</code> of the orders whose freight cost is between 500 and 1000 (both included).</p>',
            'hint'     => '<code>Freight BETWEEN 500 AND 1000</code>.',
            'solution' => "SELECT OrderID, Freight\nFROM Orders\nWHERE Freight BETWEEN 500 AND 1000;",
        ],
        [
            'title'    => 'Products starting with Ch',
            'task'     => '<p>Show the <code>ProductName</code> of every product whose name starts with <code>Ch</code>.</p>',
            'hint'     => "<code>LIKE 'Ch%'</code>: the <code>%</code> stands for anything that follows.",
            'solution' => "SELECT ProductName\nFROM Products\nWHERE ProductName LIKE 'Ch%';",
        ],
        [
            'title'    => 'Orders not yet shipped',
            'task'     => '<p>Show <code>OrderID</code>, <code>CustomerID</code> and <code>OrderDate</code> of the orders that have no shipping date yet.</p>',
            'hint'     => 'A missing value is <code>NULL</code>: <code>ShippedDate IS NULL</code>.',
            'solution' => "SELECT OrderID, CustomerID, OrderDate\nFROM Orders\nWHERE ShippedDate IS NULL;",
        ],
        [
            'title'    => 'Orders from 1998',
            'task'     => '<p>Show <code>OrderID</code> and <code>OrderDate</code> of all orders placed in 1998.</p>',
            'hint'     => "<code>YEAR(OrderDate) = 1998</code>, or <code>OrderDate &gt;= '1998-01-01'</code> (there are no later years).",
            'solution' => "SELECT OrderID, OrderDate\nFROM Orders\nWHERE YEAR(OrderDate) = 1998;",
        ],
        [
            'title'    => 'Hired in 1993 or 1994',
            'task'     => '<p>Show <code>FirstName</code>, <code>LastName</code> and <code>HireDate</code> of the employees who were hired in 1993 or in 1994.</p>',
            'hint'     => 'Take the year out of the date and compare it with a list: <code>YEAR(HireDate) IN (1993, 1994)</code>.',
            'solution' => "SELECT FirstName, LastName, HireDate\nFROM Employees\nWHERE YEAR(HireDate) IN (1993, 1994);",
        ],
        [
            'title'    => 'First quarter of 1997',
            'task'     => '<p>Show <code>OrderID</code>, <code>CustomerID</code> and <code>OrderDate</code> of the orders placed from 1 January 1997 up to and including 31 March 1997.</p>',
            'hint'     => "<code>OrderDate BETWEEN '1997-01-01' AND '1997-03-31'</code>, or combine <code>YEAR()</code> and <code>MONTH()</code>.",
            'solution' => "SELECT OrderID, CustomerID, OrderDate\nFROM Orders\nWHERE OrderDate BETWEEN '1997-01-01' AND '1997-03-31';",
        ],
        [
            'title'    => 'Second letter is a',
            'task'     => '<p>Show <code>ContactName</code> and <code>CompanyName</code> of the customers whose contact name has the letter <code>a</code> as its <em>second</em> character (for example <em>Maria Anders</em>).</p>',
            'hint'     => "<code>_</code> stands for exactly one character: <code>ContactName LIKE '_a%'</code>.",
            'solution' => "SELECT ContactName, CompanyName\nFROM Customers\nWHERE ContactName LIKE '_a%';",
        ],
        [
            'title'    => 'Single-word product names',
            'task'     => '<p>Show <code>ProductID</code> and <code>ProductName</code> of the products whose name does <em>not</em> contain a space (one-word names such as <em>Chai</em> or <em>Tofu</em>).</p>',
            'hint'     => "A space is just a character in a pattern: <code>ProductName NOT LIKE '% %'</code>.",
            'solution' => "SELECT ProductID, ProductName\nFROM Products\nWHERE ProductName NOT LIKE '% %';",
        ],
        [
            'title'    => 'Shipped to Brazil or Argentina, not by shipper 1',
            'task'     => '<p>Show <code>OrderID</code>, <code>ShipCountry</code> and <code>ShipVia</code> of the orders shipped to Brazil or Argentina that were <em>not</em> shipped by shipper 1 (<code>ShipVia</code>).</p>',
            'hint'     => 'Put the <code>OR</code> part in parentheses: <code>(ShipCountry = ... OR ShipCountry = ...) AND ShipVia &lt;&gt; 1</code>.',
            'solution' => "SELECT OrderID, ShipCountry, ShipVia\nFROM Orders\nWHERE ShipCountry IN ('Brazil', 'Argentina') AND ShipVia <> 1;",
        ],
        [
            'title'    => 'Owners outside the USA and the UK',
            'task'     => '<p>Show <code>CompanyName</code>, <code>ContactName</code> and <code>Country</code> of the customers whose <code>ContactTitle</code> is exactly <code>Owner</code> and whose country is neither USA nor UK.</p>',
            'hint'     => "<code>NOT IN</code> excludes a list of values: <code>ContactTitle = 'Owner' AND Country NOT IN ('USA', 'UK')</code>.",
            'solution' => "SELECT CompanyName, ContactName, Country\nFROM Customers\nWHERE ContactTitle = 'Owner' AND Country NOT IN ('USA', 'UK');",
        ],
        [
            'title'    => 'Shipped too late',
            'task'     => '<p>Show <code>OrderID</code>, <code>RequiredDate</code> and <code>ShippedDate</code> of the orders that were shipped <em>after</em> their required date. Orders that have not been shipped yet must not appear.</p>',
            'hint'     => 'You can compare two columns with each other: <code>ShippedDate &gt; RequiredDate</code>. A comparison with <code>NULL</code> is never true, so unshipped orders drop out by themselves.',
            'solution' => "SELECT OrderID, RequiredDate, ShippedDate\nFROM Orders\nWHERE ShippedDate > RequiredDate;",
        ],
        [
            'title'    => 'Stock worth more than 2000',
            'task'     => '<p>Show <code>ProductName</code>, <code>UnitPrice</code> and <code>UnitsInStock</code> of the products whose stock value (<code>UnitPrice</code> multiplied by <code>UnitsInStock</code>) is strictly greater than 2000.</p>',
            'hint'     => 'A condition can contain a calculation: <code>WHERE UnitPrice * UnitsInStock &gt; 2000</code>.',
            'solution' => "SELECT ProductName, UnitPrice, UnitsInStock\nFROM Products\nWHERE UnitPrice * UnitsInStock > 2000;",
        ],
        [
            'title'    => 'Large order lines',
            'task'     => '<p>From <code>OrderDetails</code>, show <code>OrderID</code>, <code>ProductID</code>, <code>UnitPrice</code>, <code>Quantity</code> and the line total (<code>UnitPrice</code> multiplied by <code>Quantity</code>) with the alias <code>LineTotal</code>, for the lines whose line total is 5000 or more.</p>',
            'hint'     => 'Calculate in the <code>SELECT</code> list with <code>AS LineTotal</code> and repeat the calculation in the <code>WHERE</code>: <code>UnitPrice * Quantity &gt;= 5000</code>. You cannot use the alias inside <code>WHERE</code>.',
            'solution' => "SELECT OrderID, ProductID, UnitPrice, Quantity, UnitPrice * Quantity AS LineTotal\nFROM OrderDetails\nWHERE UnitPrice * Quantity >= 5000;",
            'columns'  => true,
        ],
    ],
];
