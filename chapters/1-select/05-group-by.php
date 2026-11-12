<?php
return [
    'title'   => 'Aggregates, GROUP BY and HAVING',
    'summary' => 'Count, sum and average over many rows; one result row per group; filter groups.',
    'intro'   => <<<'HTML'
<h3>Aggregate functions</h3>
<p>An aggregate function collapses many rows into one value:</p>
<table>
<tr><th>Function</th><th>Result</th></tr>
<tr><td><code>COUNT(*)</code></td><td>number of rows</td></tr>
<tr><td><code>COUNT(column)</code></td><td>number of rows where the column is not NULL</td></tr>
<tr><td><code>COUNT(DISTINCT column)</code></td><td>number of different values</td></tr>
<tr><td><code>SUM(x)</code>, <code>AVG(x)</code></td><td>total, average (NULLs are ignored)</td></tr>
<tr><td><code>MIN(x)</code>, <code>MAX(x)</code></td><td>smallest, largest (also for text and dates)</td></tr>
</table>
<pre><code>SELECT COUNT(*) AS Orders, MIN(OrderDate) AS First, MAX(OrderDate) AS Last
FROM Orders;</code></pre>
<p>Without <code>GROUP BY</code> the whole table is one group, so the result has exactly one row.</p>

<h3>One row per group: GROUP BY</h3>
<p><code>GROUP BY column</code> makes one group per different value of that column, and the aggregates are computed per group:</p>
<pre><code>SELECT Country, COUNT(*) AS Customers
FROM Customers
GROUP BY Country;</code></pre>
<p><strong>Rule:</strong> every column in the select list is either in the <code>GROUP BY</code> or inside an aggregate function.
MySQL refuses the query otherwise (<em>"not in GROUP BY clause"</em>). Group by several columns to get one row per combination.</p>

<h3>Filtering groups: HAVING</h3>
<p><code>WHERE</code> filters rows <em>before</em> grouping; <code>HAVING</code> filters groups <em>after</em> grouping and may use aggregates:</p>
<pre><code>SELECT Country, COUNT(*) AS Customers
FROM Customers
WHERE Region IS NULL         -- rows
GROUP BY Country
HAVING COUNT(*) &gt;= 5         -- groups
ORDER BY Customers DESC;</code></pre>

<h3>Order of the clauses</h3>
<pre><code>SELECT ...  FROM ...  WHERE ...  GROUP BY ...  HAVING ...  ORDER BY ...  LIMIT ...</code></pre>
HTML,
    'examples' => [
        [
            'title' => 'Aggregates over the whole table',
            'sql'   => "SELECT COUNT(*) AS Products, ROUND(AVG(UnitPrice), 2) AS AvgPrice, MIN(UnitPrice) AS Cheapest, MAX(UnitPrice) AS Dearest\nFROM Products;",
        ],
        [
            'title' => 'GROUP BY',
            'sql'   => "SELECT CategoryID, COUNT(*) AS Products, ROUND(AVG(UnitPrice), 2) AS AvgPrice\nFROM Products\nGROUP BY CategoryID\nORDER BY CategoryID;",
            'note'  => 'One row per category. Try adding <code>ProductName</code> to the select list: MySQL refuses, because a group has many product names.',
        ],
        [
            'title' => 'WHERE, GROUP BY, HAVING, ORDER BY together',
            'sql'   => "SELECT EmployeeID, COUNT(*) AS Orders\nFROM Orders\nWHERE YEAR(OrderDate) = 1997\nGROUP BY EmployeeID\nHAVING COUNT(*) >= 40\nORDER BY Orders DESC;",
            'note'  => 'Only 1997 orders are counted (WHERE); only employees with 40 or more of them are shown (HAVING).',
        ],
    ],
    'exercises' => [
        [
            'title'    => 'How many orders?',
            'task'     => '<p>Show the total number of orders in the <code>Orders</code> table (one row, one column).</p>',
            'hint'     => '<code>COUNT(*)</code> over the <code>Orders</code> table.',
            'solution' => "SELECT COUNT(*) AS Orders\nFROM Orders;",
        ],
        [
            'title'    => 'Number of countries',
            'task'     => '<p>How many different countries do the customers come from? One row, one column.</p>',
            'hint'     => '<code>COUNT(DISTINCT Country)</code>.',
            'solution' => "SELECT COUNT(DISTINCT Country) AS Countries\nFROM Customers;",
        ],
        [
            'title'    => 'First and last order',
            'task'     => '<p>Show the date of the earliest order and the date of the latest order (one row, two columns, in that order).</p>',
            'hint'     => '<code>MIN</code> and <code>MAX</code> also work on dates.',
            'solution' => "SELECT MIN(OrderDate) AS FirstOrder, MAX(OrderDate) AS LastOrder\nFROM Orders;",
        ],
        [
            'title'    => 'Price statistics',
            'task'     => '<p>Show, for all products, the average unit price rounded to 2 decimals, the lowest and the highest unit price (one row, three columns, in that order).</p>',
            'hint'     => '<code>ROUND(AVG(UnitPrice), 2)</code>, <code>MIN(UnitPrice)</code>, <code>MAX(UnitPrice)</code>.',
            'solution' => "SELECT ROUND(AVG(UnitPrice), 2) AS AvgPrice, MIN(UnitPrice) AS MinPrice, MAX(UnitPrice) AS MaxPrice\nFROM Products;",
        ],
        [
            'title'    => 'Products per category',
            'task'     => '<p>Show <code>CategoryID</code> and the number of products in that category, one row per category.</p>',
            'hint'     => '<code>GROUP BY CategoryID</code> with <code>COUNT(*)</code>.',
            'solution' => "SELECT CategoryID, COUNT(*) AS Products\nFROM Products\nGROUP BY CategoryID;",
        ],
        [
            'title'    => 'Orders per shipper',
            'task'     => '<p>Show <code>ShipVia</code> and the number of orders shipped by that shipper, one row per shipper.</p>',
            'hint'     => 'Group the <code>Orders</code> table by <code>ShipVia</code>.',
            'solution' => "SELECT ShipVia, COUNT(*) AS Orders\nFROM Orders\nGROUP BY ShipVia;",
        ],
        [
            'title'    => 'Units sold per product',
            'task'     => '<p>From <code>OrderDetails</code>, show <code>ProductID</code> and the total <code>Quantity</code> ever ordered of that product, one row per product.</p>',
            'hint'     => '<code>SUM(Quantity)</code> grouped by <code>ProductID</code>.',
            'solution' => "SELECT ProductID, SUM(Quantity) AS TotalQuantity\nFROM OrderDetails\nGROUP BY ProductID;",
        ],
        [
            'title'    => 'Stock per category',
            'task'     => '<p>Show <code>CategoryID</code>, the number of products and the total <code>UnitsInStock</code> of all products in that category (three columns, in that order), one row per category.</p>',
            'hint'     => 'Two aggregates in one query: <code>COUNT(*)</code> and <code>SUM(UnitsInStock)</code>.',
            'solution' => "SELECT CategoryID, COUNT(*) AS Products, SUM(UnitsInStock) AS Stock\nFROM Products\nGROUP BY CategoryID;",
        ],
        [
            'title'    => 'Average price per supplier',
            'task'     => '<p>Show <code>SupplierID</code> and the average <code>UnitPrice</code> of that supplier\'s products, rounded to 2 decimals, one row per supplier.</p>',
            'hint'     => '<code>ROUND(AVG(UnitPrice), 2)</code> grouped by <code>SupplierID</code>.',
            'solution' => "SELECT SupplierID, ROUND(AVG(UnitPrice), 2) AS AvgPrice\nFROM Products\nGROUP BY SupplierID;",
        ],
        [
            'title'    => 'Orders and freight per year',
            'task'     => '<p>Show the year of the order date, the number of orders and the total <code>Freight</code> rounded to 2 decimals (three columns, in that order), one row per year.</p>',
            'hint'     => 'You may group by an expression: <code>GROUP BY YEAR(OrderDate)</code>.',
            'solution' => "SELECT YEAR(OrderDate) AS Yr, COUNT(*) AS Orders, ROUND(SUM(Freight), 2) AS TotalFreight\nFROM Orders\nGROUP BY YEAR(OrderDate);",
        ],
        [
            'title'    => 'Customers per employee',
            'task'     => '<p>Show <code>EmployeeID</code> and the number of <em>different</em> customers that employee has taken orders from, one row per employee.</p>',
            'hint'     => 'A customer with 10 orders counts once: <code>COUNT(DISTINCT CustomerID)</code>.',
            'solution' => "SELECT EmployeeID, COUNT(DISTINCT CustomerID) AS Customers\nFROM Orders\nGROUP BY EmployeeID;",
        ],
        [
            'title'    => 'Shipped and unshipped orders',
            'task'     => '<p>Show, per <code>EmployeeID</code>, the columns <code>EmployeeID</code>, <code>Orders</code> (all orders), <code>Shipped</code> (orders with a <code>ShippedDate</code>) and <code>NotShipped</code> (orders without one), in that order. Use exactly these column names.</p>',
            'hint'     => '<code>COUNT(*)</code> counts every row, <code>COUNT(ShippedDate)</code> skips the NULLs. The difference is the number of unshipped orders.',
            'solution' => "SELECT EmployeeID, COUNT(*) AS Orders, COUNT(ShippedDate) AS Shipped, COUNT(*) - COUNT(ShippedDate) AS NotShipped\nFROM Orders\nGROUP BY EmployeeID;",
            'columns'  => true,
        ],
        [
            'title'    => 'Orders per country per year',
            'task'     => '<p>Show <code>ShipCountry</code>, the year of the order date, and the number of orders for that country in that year (three columns, in that order), one row per combination.</p>',
            'hint'     => 'Group by two things: <code>GROUP BY ShipCountry, YEAR(OrderDate)</code>.',
            'solution' => "SELECT ShipCountry, YEAR(OrderDate) AS Yr, COUNT(*) AS Orders\nFROM Orders\nGROUP BY ShipCountry, YEAR(OrderDate);",
        ],
        [
            'title'    => 'Countries with many customers',
            'task'     => '<p>Show <code>Country</code> and the number of customers, but only for countries with more than 5 customers.</p>',
            'hint'     => 'A condition on an aggregate goes in <code>HAVING</code>, not in <code>WHERE</code>.',
            'solution' => "SELECT Country, COUNT(*) AS Customers\nFROM Customers\nGROUP BY Country\nHAVING COUNT(*) > 5;",
        ],
        [
            'title'    => 'Big orders',
            'task'     => '<p>From <code>OrderDetails</code>, show <code>OrderID</code> and the value of the order (the sum of <code>UnitPrice * Quantity</code> over its lines), but only for orders worth more than 10000. Two columns, in that order.</p>',
            'hint'     => 'Aggregate over a calculation: <code>SUM(UnitPrice * Quantity)</code>, then filter with <code>HAVING</code>.',
            'solution' => "SELECT OrderID, SUM(UnitPrice * Quantity) AS OrderValue\nFROM OrderDetails\nGROUP BY OrderID\nHAVING SUM(UnitPrice * Quantity) > 10000;",
        ],
        [
            'title'    => 'Busy employees in 1997',
            'task'     => '<p>Show <code>EmployeeID</code> and the number of orders that employee handled in 1997, only for employees with more than 50 such orders.</p>',
            'hint'     => '<code>WHERE YEAR(OrderDate) = 1997</code> before the <code>GROUP BY</code>, <code>HAVING COUNT(*) &gt; 50</code> after it.',
            'solution' => "SELECT EmployeeID, COUNT(*) AS Orders\nFROM Orders\nWHERE YEAR(OrderDate) = 1997\nGROUP BY EmployeeID\nHAVING COUNT(*) > 50;",
        ],
        [
            'title'    => 'Top 3 customers',
            'task'     => '<p>Show <code>CustomerID</code> and the number of orders of the 3 customers with the most orders, biggest first (ties by <code>CustomerID</code> A→Z).</p>',
            'hint'     => 'Group, then <code>ORDER BY COUNT(*) DESC, CustomerID</code>, then <code>LIMIT 3</code>.',
            'solution' => "SELECT CustomerID, COUNT(*) AS Orders\nFROM Orders\nGROUP BY CustomerID\nORDER BY Orders DESC, CustomerID\nLIMIT 3;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Average freight per shipper per year',
            'task'     => '<p>Show <code>ShipVia</code>, the year of the order date and the average <code>Freight</code> rounded to 2 decimals (three columns, in that order), one row per shipper per year. Sort by <code>ShipVia</code>, then by year, both ascending.</p>',
            'hint'     => 'Group by a column and an expression; <code>ROUND(AVG(Freight), 2)</code>.',
            'solution' => "SELECT ShipVia, YEAR(OrderDate) AS Yr, ROUND(AVG(Freight), 2) AS AvgFreight\nFROM Orders\nGROUP BY ShipVia, YEAR(OrderDate)\nORDER BY ShipVia, Yr;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Repeatedly late customers',
            'task'     => '<p>An order is late when its <code>ShippedDate</code> is after its <code>RequiredDate</code>. Show <code>CustomerID</code> and the number of late orders, only for customers with 2 or more late orders. Most late orders first, ties by <code>CustomerID</code> A→Z.</p>',
            'hint'     => 'The lateness test is a row condition (<code>WHERE</code>); the "2 or more" test is a group condition (<code>HAVING</code>).',
            'solution' => "SELECT CustomerID, COUNT(*) AS LateOrders\nFROM Orders\nWHERE ShippedDate > RequiredDate\nGROUP BY CustomerID\nHAVING COUNT(*) >= 2\nORDER BY LateOrders DESC, CustomerID;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Most expensive countries to ship to in 1997',
            'task'     => '<p>For orders placed in 1997 only, show <code>ShipCountry</code>, the number of orders and the average <code>Freight</code> rounded to 2 decimals (three columns, in that order). Keep only countries with at least 10 such orders, and show the 5 countries with the highest average freight, highest first (ties by <code>ShipCountry</code> A→Z).</p>',
            'hint'     => 'All clauses in one query: <code>WHERE</code> (year), <code>GROUP BY</code>, <code>HAVING COUNT(*) &gt;= 10</code>, <code>ORDER BY</code> the average <code>DESC</code>, <code>LIMIT 5</code>.',
            'solution' => "SELECT ShipCountry, COUNT(*) AS Orders, ROUND(AVG(Freight), 2) AS AvgFreight\nFROM Orders\nWHERE YEAR(OrderDate) = 1997\nGROUP BY ShipCountry\nHAVING COUNT(*) >= 10\nORDER BY AvgFreight DESC, ShipCountry\nLIMIT 5;",
            'ordered'  => true,
        ],
    ],
];
