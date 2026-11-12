<?php
return [
    'title'   => 'Subqueries and CTEs',
    'summary' => 'A query inside a query: scalar, IN, EXISTS, derived tables, correlated subqueries, WITH.',
    'intro'   => <<<'HTML'
<h3>A query as a value</h3>
<p>A subquery is a <code>SELECT</code> between parentheses inside another statement. When it returns exactly one value
(one row, one column) you can use it like a number or a text:</p>
<pre><code>SELECT ProductName, UnitPrice
FROM Products
WHERE UnitPrice &gt; (SELECT AVG(UnitPrice) FROM Products);</code></pre>

<h3>A query as a list: IN, NOT IN, EXISTS</h3>
<p>When the subquery returns one column with many rows, use <code>IN</code>:</p>
<pre><code>SELECT CompanyName
FROM Customers
WHERE CustomerID IN (SELECT CustomerID FROM Orders WHERE ShipCountry = 'Brazil');</code></pre>
<p><code>NOT IN</code> finds the ones that are missing, but beware: if the subquery can return a <code>NULL</code>,
<code>NOT IN</code> returns nothing at all. <code>NOT EXISTS</code> does not have that problem:</p>
<pre><code>SELECT c.CompanyName
FROM Customers AS c
WHERE NOT EXISTS (SELECT 1 FROM Orders AS o WHERE o.CustomerID = c.CustomerID);</code></pre>

<h3>Correlated subqueries</h3>
<p>The <code>EXISTS</code> example above uses <code>c.CustomerID</code> from the outer query inside the subquery: the subquery is
run once per outer row. This is called <em>correlated</em>. It is the way to compare a row with its own group:</p>
<pre><code>SELECT ProductName, CategoryID, UnitPrice
FROM Products AS p
WHERE UnitPrice = (SELECT MAX(UnitPrice) FROM Products WHERE CategoryID = p.CategoryID);</code></pre>

<h3>A query as a table: derived tables and WITH</h3>
<p>A subquery in <code>FROM</code> acts as a table and must get an alias:</p>
<pre><code>SELECT ROUND(AVG(t.Orders), 2) AS AvgOrdersPerCustomer
FROM (SELECT CustomerID, COUNT(*) AS Orders FROM Orders GROUP BY CustomerID) AS t;</code></pre>
<p>The same thing reads better as a <strong>common table expression</strong> (CTE) with <code>WITH</code>:</p>
<pre><code>WITH t AS (
  SELECT CustomerID, COUNT(*) AS Orders
  FROM Orders
  GROUP BY CustomerID
)
SELECT ROUND(AVG(Orders), 2) AS AvgOrdersPerCustomer
FROM t;</code></pre>
<p>Several CTEs are separated by commas; a CTE can use an earlier one.</p>

<h3>Views</h3>
<p>Northwind also contains saved queries called <strong>views</strong>; you query them like tables. Names with spaces need backticks,
for example <code>SELECT * FROM `Order Subtotals`</code>. The sidebar lists only the base tables.</p>
HTML,
    'examples' => [
        [
            'title' => 'Scalar subquery',
            'sql'   => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE UnitPrice > (SELECT AVG(UnitPrice) FROM Products)\nORDER BY UnitPrice DESC;",
        ],
        [
            'title' => 'IN with a subquery',
            'sql'   => "SELECT CompanyName, Country\nFROM Customers\nWHERE CustomerID IN (SELECT CustomerID FROM Orders WHERE ShipVia = 3 AND YEAR(OrderDate) = 1998);",
            'note'  => 'Customers that received at least one 1998 order via shipper 3. The same with a JOIN would need DISTINCT.',
        ],
        [
            'title' => 'CTE and a view',
            'sql'   => "WITH big AS (\n  SELECT OrderID, Subtotal\n  FROM `Order Subtotals`\n  WHERE Subtotal > 10000\n)\nSELECT o.OrderID, o.CustomerID, big.Subtotal\nFROM big\nJOIN Orders AS o ON o.OrderID = big.OrderID\nORDER BY big.Subtotal DESC;",
            'note'  => '<code>Order Subtotals</code> is a view that already computes the total per order (including discounts).',
        ],
    ],
    'exercises' => [
        [
            'title'    => 'Most expensive product',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the most expensive product. Do not use <code>ORDER BY ... LIMIT</code>: compare the price with a subquery that returns the highest unit price.</p>',
            'hint'     => '<code>WHERE UnitPrice = (SELECT MAX(UnitPrice) FROM Products)</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE UnitPrice = (SELECT MAX(UnitPrice) FROM Products);",
        ],
        [
            'title'    => 'Above average price',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the products that are more expensive than the average unit price of all products.</p>',
            'hint'     => '<code>WHERE UnitPrice &gt; (SELECT AVG(UnitPrice) FROM Products)</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE UnitPrice > (SELECT AVG(UnitPrice) FROM Products);",
        ],
        [
            'title'    => 'Customers with heavy freight',
            'task'     => '<p>Show the <code>CompanyName</code> of every customer that has at least one order with a freight cost above 500. One row per customer.</p>',
            'hint'     => '<code>CustomerID IN (SELECT CustomerID FROM Orders WHERE Freight &gt; 500)</code>.',
            'solution' => "SELECT CompanyName\nFROM Customers\nWHERE CustomerID IN (SELECT CustomerID FROM Orders WHERE Freight > 500);",
        ],
        [
            'title'    => 'Customers without orders, again',
            'task'     => '<p>Show the <code>CompanyName</code> of the customers that never placed an order, this time with a subquery instead of a LEFT JOIN.</p>',
            'hint'     => '<code>NOT EXISTS (SELECT 1 FROM Orders AS o WHERE o.CustomerID = c.CustomerID)</code> or <code>NOT IN</code>.',
            'solution' => "SELECT c.CompanyName\nFROM Customers AS c\nWHERE NOT EXISTS (SELECT 1 FROM Orders AS o WHERE o.CustomerID = c.CustomerID);",
        ],
        [
            'title'    => 'Countries without suppliers',
            'task'     => '<p>Show every <code>Country</code> (from <code>Customers</code>) in which at least one customer is located but no supplier. One row per country, no duplicates.</p>',
            'hint'     => '<code>SELECT DISTINCT Country ... WHERE Country NOT IN (SELECT Country FROM Suppliers)</code>. No supplier has a <code>NULL</code> country, so <code>NOT IN</code> is safe here.',
            'solution' => "SELECT DISTINCT Country\nFROM Customers\nWHERE Country NOT IN (SELECT Country FROM Suppliers);",
        ],
        [
            'title'    => 'Busy shippers',
            'task'     => '<p>Show the <code>CompanyName</code> of the shippers that shipped more than 250 orders (<code>Orders.ShipVia</code> refers to the shipper). Use <code>IN</code> with a subquery that groups the orders per shipper; do not join.</p>',
            'hint'     => '<code>ShipperID IN (SELECT ShipVia FROM Orders GROUP BY ShipVia HAVING COUNT(*) &gt; 250)</code>.',
            'solution' => "SELECT CompanyName\nFROM Shippers\nWHERE ShipperID IN (SELECT ShipVia FROM Orders GROUP BY ShipVia HAVING COUNT(*) > 250);",
        ],
        [
            'title'    => 'Employees without subordinates',
            'task'     => '<p>Show <code>FirstName</code> and <code>LastName</code> of the employees to whom nobody reports, i.e. no other employee has their <code>EmployeeID</code> in <code>ReportsTo</code>.</p>',
            'hint'     => 'A correlated <code>NOT EXISTS</code> on the same table: <code>NOT EXISTS (SELECT 1 FROM Employees AS s WHERE s.ReportsTo = e.EmployeeID)</code>.',
            'solution' => "SELECT e.FirstName, e.LastName\nFROM Employees AS e\nWHERE NOT EXISTS (SELECT 1 FROM Employees AS s WHERE s.ReportsTo = e.EmployeeID);",
        ],
        [
            'title'    => 'Suppliers without discontinued products',
            'task'     => '<p>Show the <code>CompanyName</code> of the suppliers that have no discontinued product (<code>Discontinued = 1</code>). A supplier without any product would also qualify.</p>',
            'hint'     => '<code>NOT EXISTS (SELECT 1 FROM Products AS p WHERE p.SupplierID = s.SupplierID AND p.Discontinued = 1)</code>.',
            'solution' => "SELECT s.CompanyName\nFROM Suppliers AS s\nWHERE NOT EXISTS (SELECT 1 FROM Products AS p WHERE p.SupplierID = s.SupplierID AND p.Discontinued = 1);",
        ],
        [
            'title'    => 'Product count in the SELECT list',
            'task'     => '<p>Show <code>CategoryName</code> and the number of products of that category as <code>Products</code>, one row per category (categories without products would show 0). Compute the count with a subquery in the <code>SELECT</code> list: no <code>JOIN</code>, no <code>GROUP BY</code>. Use exactly these column names.</p>',
            'hint'     => '<code>SELECT c.CategoryName, (SELECT COUNT(*) FROM Products AS p WHERE p.CategoryID = c.CategoryID) AS Products FROM Categories AS c</code>.',
            'solution' => "SELECT c.CategoryName,\n       (SELECT COUNT(*) FROM Products AS p WHERE p.CategoryID = c.CategoryID) AS Products\nFROM Categories AS c;",
            'columns'  => true,
        ],
        [
            'title'    => 'Average number of products per category',
            'task'     => '<p>Show the average number of products per category, rounded to 2 decimals (one row, one column). First count the products per category, then take the average of those counts.</p>',
            'hint'     => 'Put the <code>GROUP BY CategoryID</code> query in the <code>FROM</code> with an alias, then <code>ROUND(AVG(...), 2)</code> over it.',
            'solution' => "SELECT ROUND(AVG(t.Products), 2) AS AvgProducts\nFROM (SELECT CategoryID, COUNT(*) AS Products FROM Products GROUP BY CategoryID) AS t;",
        ],
        [
            'title'    => 'Big orders with a CTE',
            'task'     => '<p>Using <code>WITH</code>, compute per <code>OrderID</code> the total <code>UnitPrice * Quantity</code> from <code>OrderDetails</code>, then show <code>OrderID</code> and that total (not rounded) for the orders with a total above 10000.</p>',
            'hint'     => '<code>WITH totals AS (SELECT OrderID, SUM(UnitPrice * Quantity) AS Total FROM OrderDetails GROUP BY OrderID) SELECT ... FROM totals WHERE Total &gt; 10000</code>.',
            'solution' => "WITH totals AS (\n  SELECT OrderID, SUM(UnitPrice * Quantity) AS Total\n  FROM OrderDetails\n  GROUP BY OrderID\n)\nSELECT OrderID, Total\nFROM totals\nWHERE Total > 10000;",
        ],
        [
            'title'    => 'Most expensive product per category',
            'task'     => '<p>Show <code>CategoryID</code>, <code>ProductName</code> and <code>UnitPrice</code> of the most expensive product of each category (8 rows).</p>',
            'hint'     => 'Correlated subquery: <code>WHERE UnitPrice = (SELECT MAX(UnitPrice) FROM Products WHERE CategoryID = p.CategoryID)</code>.',
            'solution' => "SELECT p.CategoryID, p.ProductName, p.UnitPrice\nFROM Products AS p\nWHERE p.UnitPrice = (SELECT MAX(UnitPrice) FROM Products WHERE CategoryID = p.CategoryID);",
        ],
        [
            'title'    => 'Products above their category average',
            'task'     => '<p>Show <code>ProductName</code>, <code>CategoryID</code> and <code>UnitPrice</code> of the products whose unit price is higher than the average unit price of their <em>own</em> category.</p>',
            'hint'     => 'Same idea as the previous exercise, but with <code>&gt;</code> and <code>AVG</code> instead of <code>=</code> and <code>MAX</code>.',
            'solution' => "SELECT p.ProductName, p.CategoryID, p.UnitPrice\nFROM Products AS p\nWHERE p.UnitPrice > (SELECT AVG(UnitPrice) FROM Products WHERE CategoryID = p.CategoryID);",
        ],
        [
            'title'    => 'Latest order per customer',
            'task'     => '<p>Show <code>CustomerID</code>, <code>OrderID</code> and <code>OrderDate</code> (all from <code>Orders</code>) of the most recent order of every customer. If a customer placed two orders on that last day, show both of them (90 rows in total).</p>',
            'hint'     => '<code>WHERE o.OrderDate = (SELECT MAX(OrderDate) FROM Orders WHERE CustomerID = o.CustomerID)</code>.',
            'solution' => "SELECT o.CustomerID, o.OrderID, o.OrderDate\nFROM Orders AS o\nWHERE o.OrderDate = (SELECT MAX(OrderDate) FROM Orders WHERE CustomerID = o.CustomerID);",
        ],
        [
            'title'    => 'Second-highest price without LIMIT',
            'task'     => '<p>Show <code>ProductName</code> and <code>UnitPrice</code> of the product with the second-highest unit price, without <code>ORDER BY</code> or <code>LIMIT</code>. The second-highest price is the highest price among the prices that are lower than the maximum.</p>',
            'hint'     => 'Nest two scalar subqueries: <code>UnitPrice = (SELECT MAX(UnitPrice) FROM Products WHERE UnitPrice &lt; (SELECT MAX(UnitPrice) FROM Products))</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products\nWHERE UnitPrice = (SELECT MAX(UnitPrice) FROM Products\n                   WHERE UnitPrice < (SELECT MAX(UnitPrice) FROM Products));",
        ],
        [
            'title'    => 'Employees with more orders than average',
            'task'     => '<p>Show <code>LastName</code> and the number of orders the employee handled as <code>Orders</code>, for the employees that handled more orders than the average number of orders per employee (the average of the nine per-employee counts). Use exactly these column names.</p>',
            'hint'     => 'Join <code>Employees</code> to a derived table <code>(SELECT EmployeeID, COUNT(*) AS Orders FROM Orders GROUP BY EmployeeID) AS t</code>, and compare <code>t.Orders</code> with <code>(SELECT AVG(n) FROM (SELECT COUNT(*) AS n FROM Orders GROUP BY EmployeeID) AS x)</code>.',
            'solution' => "SELECT e.LastName, t.Orders\nFROM Employees AS e\nJOIN (SELECT EmployeeID, COUNT(*) AS Orders FROM Orders GROUP BY EmployeeID) AS t\n  ON t.EmployeeID = e.EmployeeID\nWHERE t.Orders > (SELECT AVG(n) FROM (SELECT COUNT(*) AS n FROM Orders GROUP BY EmployeeID) AS x);",
            'columns'  => true,
        ],
        [
            'title'    => 'Compared with the category average',
            'task'     => '<p>Show <code>ProductName</code>, <code>UnitPrice</code> and the average unit price of the product\'s category rounded to 2 decimals as <code>CategoryAvg</code>, for the products whose price is above that (unrounded) average. Compute the average per category in a derived table or CTE and join it back to <code>Products</code>. Use exactly these column names.</p>',
            'hint'     => '<code>JOIN (SELECT CategoryID, AVG(UnitPrice) AS AvgPrice FROM Products GROUP BY CategoryID) AS a ON a.CategoryID = p.CategoryID WHERE p.UnitPrice &gt; a.AvgPrice</code>.',
            'solution' => "SELECT p.ProductName, p.UnitPrice, ROUND(a.AvgPrice, 2) AS CategoryAvg\nFROM Products AS p\nJOIN (SELECT CategoryID, AVG(UnitPrice) AS AvgPrice FROM Products GROUP BY CategoryID) AS a\n  ON a.CategoryID = p.CategoryID\nWHERE p.UnitPrice > a.AvgPrice;",
            'columns'  => true,
        ],
        [
            'title'    => 'Products never ordered in 1998',
            'task'     => '<p>Show the <code>ProductName</code> of the products that do not appear in any order placed in 1998 (year of <code>Orders.OrderDate</code>).</p>',
            'hint'     => 'The subquery needs two tables: <code>NOT EXISTS (SELECT 1 FROM OrderDetails AS od JOIN Orders AS o ON o.OrderID = od.OrderID WHERE od.ProductID = p.ProductID AND YEAR(o.OrderDate) = 1998)</code>.',
            'solution' => "SELECT p.ProductName\nFROM Products AS p\nWHERE NOT EXISTS (\n  SELECT 1\n  FROM OrderDetails AS od\n  JOIN Orders AS o ON o.OrderID = od.OrderID\n  WHERE od.ProductID = p.ProductID AND YEAR(o.OrderDate) = 1998\n);",
        ],
        [
            'title'    => 'Customers who never bought seafood',
            'task'     => '<p>Show the <code>CompanyName</code> of the customers that never ordered a product of the category named <code>Seafood</code>. Customers that never placed any order also qualify.</p>',
            'hint'     => 'Inside <code>NOT EXISTS</code>, join <code>Orders</code>, <code>OrderDetails</code>, <code>Products</code> and <code>Categories</code>, and correlate on <code>o.CustomerID = c.CustomerID</code>.',
            'solution' => "SELECT c.CompanyName\nFROM Customers AS c\nWHERE NOT EXISTS (\n  SELECT 1\n  FROM Orders AS o\n  JOIN OrderDetails AS od ON od.OrderID = o.OrderID\n  JOIN Products AS p ON p.ProductID = od.ProductID\n  JOIN Categories AS ca ON ca.CategoryID = p.CategoryID\n  WHERE o.CustomerID = c.CustomerID AND ca.CategoryName = 'Seafood'\n);",
        ],
        [
            'title'    => 'Customers above the average customer total',
            'task'     => '<p>Use two CTEs. The first computes per customer the total <code>SUM(UnitPrice * Quantity)</code> over the <code>OrderDetails</code> of all their orders; the second computes the average of those totals (customers without orders do not count). Show <code>CompanyName</code> and the customer\'s total rounded to 2 decimals as <code>Total</code> for the customers whose (unrounded) total is above that average. Use exactly these column names.</p>',
            'hint'     => '<code>WITH totals AS (...), avg_total AS (SELECT AVG(Total) AS AvgTotal FROM totals) SELECT ... FROM totals AS t JOIN Customers AS c ON ... WHERE t.Total &gt; (SELECT AvgTotal FROM avg_total)</code>.',
            'solution' => "WITH totals AS (\n  SELECT o.CustomerID, SUM(od.UnitPrice * od.Quantity) AS Total\n  FROM Orders AS o\n  JOIN OrderDetails AS od ON od.OrderID = o.OrderID\n  GROUP BY o.CustomerID\n),\navg_total AS (\n  SELECT AVG(Total) AS AvgTotal\n  FROM totals\n)\nSELECT c.CompanyName, ROUND(t.Total, 2) AS Total\nFROM totals AS t\nJOIN Customers AS c ON c.CustomerID = t.CustomerID\nWHERE t.Total > (SELECT AvgTotal FROM avg_total);",
            'columns'  => true,
        ],
    ],
];
