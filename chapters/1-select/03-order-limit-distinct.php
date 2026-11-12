<?php
return [
    'title'   => 'ORDER BY, LIMIT and DISTINCT',
    'summary' => 'Sort the result, show only the first rows, remove duplicates.',
    'intro'   => <<<'HTML'
<h3>Sorting: ORDER BY</h3>
<p>Without <code>ORDER BY</code> the database returns rows in whatever order is convenient for it. Never rely on that.
<code>ORDER BY</code> goes at the end of the query; <code>ASC</code> (ascending, the default) or <code>DESC</code> (descending):</p>
<pre><code>SELECT ProductName, UnitPrice
FROM Products
ORDER BY UnitPrice DESC;</code></pre>
<p>Several sort keys: the second one decides when the first is equal.</p>
<pre><code>ORDER BY Country, CompanyName</code></pre>
<p>You can sort on a column that is not in the select list, and on a calculated column by its alias.</p>

<h3>Only the first rows: LIMIT</h3>
<p><code>LIMIT n</code> keeps the first n rows <em>of the sorted result</em>, so it only makes sense together with <code>ORDER BY</code>.
<code>LIMIT n OFFSET m</code> skips m rows first (rows m+1 to m+n), which is how web pages do paging.</p>
<pre><code>SELECT ProductName, UnitPrice
FROM Products
ORDER BY UnitPrice DESC
LIMIT 5;</code></pre>

<h3>No duplicates: DISTINCT</h3>
<p><code>SELECT DISTINCT</code> removes rows that are completely identical in the result:</p>
<pre><code>SELECT DISTINCT Country
FROM Customers;</code></pre>
<p>With two columns, <code>DISTINCT Country, City</code> keeps every different combination.</p>

<h3>Order of the clauses</h3>
<pre><code>SELECT ...  FROM ...  WHERE ...  ORDER BY ...  LIMIT ...</code></pre>
<p>In the exercises of this chapter the <strong>row order is checked</strong>. When two rows could tie, the exercise tells
you which column breaks the tie: add it as second sort key.</p>
HTML,
    'examples' => [
        [
            'title' => 'Sort descending',
            'sql'   => "SELECT ProductName, UnitPrice\nFROM Products\nORDER BY UnitPrice DESC;",
            'note'  => 'The most expensive product first.',
        ],
        [
            'title' => 'Two sort keys and a LIMIT',
            'sql'   => "SELECT CompanyName, Country, City\nFROM Customers\nORDER BY Country, City\nLIMIT 10;",
            'note'  => 'Countries A→Z, inside a country the cities A→Z, then only the first ten rows.',
        ],
        [
            'title' => 'DISTINCT',
            'sql'   => "SELECT DISTINCT ShipCountry\nFROM Orders\nORDER BY ShipCountry;",
            'note'  => 'Every country appears once, although there are 830 orders.',
        ],
    ],
    'exercises' => [
        [
            'title'    => 'Employees alphabetically',
            'task'     => '<p>Show <code>LastName</code> and <code>FirstName</code> of all employees, sorted by last name A→Z (every last name occurs once).</p>',
            'hint'     => 'Ascending is the default, <code>ASC</code> is optional.',
            'solution' => "SELECT LastName, FirstName\nFROM Employees\nORDER BY LastName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Suppliers by name',
            'task'     => '<p>Show <code>CompanyName</code> and <code>Country</code> of all suppliers, sorted by company name A→Z (every company name occurs once).</p>',
            'hint'     => '<code>ORDER BY CompanyName</code>.',
            'solution' => "SELECT CompanyName, Country\nFROM Suppliers\nORDER BY CompanyName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Products by price',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of all products, most expensive first. Products with the same price are sorted by name (A→Z).</p>',
            'hint'     => '<code>ORDER BY UnitPrice DESC, ProductName</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nORDER BY UnitPrice DESC, ProductName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Customers by country and name',
            'task'     => '<p>Show <code>Country</code> and <code>CompanyName</code> of all customers, sorted by country A→Z and, inside a country, by company name A→Z.</p>',
            'hint'     => 'Two sort keys separated by a comma.',
            'solution' => "SELECT Country, CompanyName\nFROM Customers\nORDER BY Country, CompanyName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Top 5 most expensive',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the 5 most expensive products, most expensive first (ties by name A→Z).</p>',
            'hint'     => 'Sort first, then <code>LIMIT 5</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nORDER BY UnitPrice DESC, ProductName\nLIMIT 5;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Places 6 to 10',
            'task'     => '<p>Same list as the previous exercise (<code>ProductName</code>, <code>UnitPrice</code>, most expensive first, ties by name A→Z), but now only the products in places 6 to 10.</p>',
            'hint'     => 'Skip the first five: <code>LIMIT 5 OFFSET 5</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nORDER BY UnitPrice DESC, ProductName\nLIMIT 5 OFFSET 5;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Titles of courtesy',
            'task'     => '<p>Which titles of courtesy (Mr., Ms., …) are used in the employee table? Show every title once. One column: <code>TitleOfCourtesy</code>. The order does not matter.</p>',
            'hint'     => '<code>SELECT DISTINCT</code> removes the duplicates.',
            'solution' => "SELECT DISTINCT TitleOfCourtesy\nFROM Employees;",
            'ordered'  => false,
        ],
        [
            'title'    => 'Customer countries',
            'task'     => '<p>Show every country that has at least one customer, once, sorted A→Z. One column: <code>Country</code>.</p>',
            'hint'     => '<code>SELECT DISTINCT Country</code> and sort.',
            'solution' => "SELECT DISTINCT Country\nFROM Customers\nORDER BY Country;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Oldest employees',
            'task'     => '<p>Show <code>LastName</code>, <code>FirstName</code> and <code>BirthDate</code> of the 3 oldest employees, oldest first (the birth dates are all different).</p>',
            'hint'     => 'The oldest person has the <em>smallest</em> birth date: sort ascending and take 3.',
            'solution' => "SELECT LastName, FirstName, BirthDate\nFROM Employees\nORDER BY BirthDate\nLIMIT 3;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Oldest open orders',
            'task'     => '<p>Show <code>OrderID</code>, <code>CustomerID</code> and <code>OrderDate</code> of the 3 oldest orders that have not been shipped yet (oldest first; the order dates are all different).</p>',
            'hint'     => 'Combine <code>WHERE ShippedDate IS NULL</code>, <code>ORDER BY OrderDate</code> and <code>LIMIT 3</code>, in that order.',
            'solution' => "SELECT OrderID, CustomerID, OrderDate\nFROM Orders\nWHERE ShippedDate IS NULL\nORDER BY OrderDate\nLIMIT 3;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Heaviest freight',
            'task'     => '<p>Show <code>OrderID</code>, <code>CustomerID</code> and <code>Freight</code> of the 10 orders with the highest freight cost, highest first. Orders with the same freight are sorted by <code>OrderID</code> (low→high).</p>',
            'hint'     => '<code>ORDER BY Freight DESC, OrderID LIMIT 10</code>.',
            'solution' => "SELECT OrderID, CustomerID, Freight\nFROM Orders\nORDER BY Freight DESC, OrderID\nLIMIT 10;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Below reorder level',
            'task'     => '<p>Show <code>ProductName</code>, <code>UnitsInStock</code> and <code>ReorderLevel</code> of the products whose stock is below their reorder level. Sort by <code>UnitsInStock</code> (low→high); products with the same stock by name A→Z.</p>',
            'hint'     => 'Compare two columns in the <code>WHERE</code>: <code>UnitsInStock &lt; ReorderLevel</code>. <code>WHERE</code> comes before <code>ORDER BY</code>.',
            'solution' => "SELECT ProductName, UnitsInStock, ReorderLevel\nFROM Products\nWHERE UnitsInStock < ReorderLevel\nORDER BY UnitsInStock, ProductName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Countries and cities of customers',
            'task'     => '<p>Show every different combination of <code>Country</code> and <code>City</code> that occurs in the customer table, once. Sort by country A→Z and, inside a country, by city A→Z.</p>',
            'hint'     => '<code>DISTINCT</code> works on the whole row: <code>SELECT DISTINCT Country, City</code>.',
            'solution' => "SELECT DISTINCT Country, City\nFROM Customers\nORDER BY Country, City;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Customers with expensive shipments',
            'task'     => '<p>Which customers have at least one order with a freight cost above 500? Show every <code>CustomerID</code> once. The order does not matter.</p>',
            'hint'     => 'Filter with <code>WHERE Freight &gt; 500</code>, then let <code>DISTINCT</code> remove the customers that occur several times.',
            'solution' => "SELECT DISTINCT CustomerID\nFROM Orders\nWHERE Freight > 500;",
            'ordered'  => false,
        ],
        [
            'title'    => 'Full names in order',
            'task'     => '<p>Show the full name of every employee as one column <code>FullName</code> (first name, a space, last name), sorted on that full name A→Z. The column must be called exactly <code>FullName</code>.</p>',
            'hint'     => 'Build the name with <code>CONCAT(FirstName, \' \', LastName) AS FullName</code>; you can sort on the alias: <code>ORDER BY FullName</code>.',
            'solution' => "SELECT CONCAT(FirstName, ' ', LastName) AS FullName\nFROM Employees\nORDER BY FullName;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Category, then price',
            'task'     => '<p>Show <code>CategoryID</code>, <code>ProductName</code> and <code>UnitPrice</code> of all products. Sort by category (low→high); inside a category the most expensive product comes first; products with the same category and price are sorted by name A→Z.</p>',
            'hint'     => 'Every sort key gets its own direction: <code>ORDER BY CategoryID, UnitPrice DESC, ProductName</code>.',
            'solution' => "SELECT CategoryID, ProductName, UnitPrice\nFROM Products\nORDER BY CategoryID, UnitPrice DESC, ProductName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Stock value',
            'task'     => '<p>Show <code>ProductID</code>, <code>ProductName</code> and the value of the stock as <code>StockValue</code> (units in stock × unit price) for the 10 products with the highest stock value, highest first. Ties by <code>ProductID</code> (low→high). The column must be called exactly <code>StockValue</code>.</p>',
            'hint'     => 'Calculate <code>UnitsInStock * UnitPrice AS StockValue</code> and sort on the alias: <code>ORDER BY StockValue DESC, ProductID</code>.',
            'solution' => "SELECT ProductID, ProductName, UnitsInStock * UnitPrice AS StockValue\nFROM Products\nORDER BY StockValue DESC, ProductID\nLIMIT 10;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Page 3 of recent orders',
            'task'     => '<p>A web page lists the orders newest first, 10 per page. Show <code>OrderID</code>, <code>OrderDate</code> and <code>CustomerID</code> of page 3 (rows 21 to 30). Sort by <code>OrderDate</code> newest first; several orders share a date, so break ties by <code>OrderID</code>, also highest first.</p>',
            'hint'     => 'Page 3 skips 2 × 10 rows: <code>LIMIT 10 OFFSET 20</code>. Without the tie-breaker the rows of a page could change between two runs.',
            'solution' => "SELECT OrderID, OrderDate, CustomerID\nFROM Orders\nORDER BY OrderDate DESC, OrderID DESC\nLIMIT 10 OFFSET 20;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Unshipped first',
            'task'     => '<p>Show <code>OrderID</code>, <code>OrderDate</code> and <code>ShippedDate</code> of all orders placed on or after 20 April 1998, sorted by <code>ShippedDate</code> ascending with <code>OrderID</code> (low→high) as tie-breaker. In MySQL a <code>NULL</code> sorts <em>before</em> every value when sorting ascending, so the orders that have not been shipped yet come first.</p>',
            'hint'     => '<code>WHERE OrderDate &gt;= \'1998-04-20\'</code> and <code>ORDER BY ShippedDate, OrderID</code>. Nothing special is needed for the NULLs: ascending puts them first, descending would put them last.',
            'solution' => "SELECT OrderID, OrderDate, ShippedDate\nFROM Orders\nWHERE OrderDate >= '1998-04-20'\nORDER BY ShippedDate, OrderID;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Destinations of employee 4',
            'task'     => '<p>To which places did employee 4 send orders with shipper 3? Show every different combination of <code>ShipCountry</code> and <code>ShipCity</code> once, sorted by country A→Z and, inside a country, by city A→Z.</p>',
            'hint'     => 'Filter first (<code>WHERE EmployeeID = 4 AND ShipVia = 3</code>), then <code>SELECT DISTINCT ShipCountry, ShipCity</code> and sort on both columns. Order of the clauses: SELECT, FROM, WHERE, ORDER BY.',
            'solution' => "SELECT DISTINCT ShipCountry, ShipCity\nFROM Orders\nWHERE EmployeeID = 4 AND ShipVia = 3\nORDER BY ShipCountry, ShipCity;",
            'ordered'  => true,
        ],
    ],
];
