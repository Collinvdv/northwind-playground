<?php
return [
    'title'   => 'JOINs: combining tables',
    'summary' => 'INNER JOIN, several tables, LEFT JOIN for missing matches, self joins, join + GROUP BY.',
    'intro'   => <<<'HTML'
<h3>Why join?</h3>
<p>Data is spread over tables to avoid repetition: <code>Products</code> stores a <code>CategoryID</code>, not the category name.
A <code>JOIN</code> puts rows from two tables side by side when a condition (usually key = foreign key) holds:</p>
<pre><code>SELECT p.ProductName, c.CategoryName
FROM Products AS p
INNER JOIN Categories AS c ON c.CategoryID = p.CategoryID;</code></pre>
<p><code>p</code> and <code>c</code> are <strong>table aliases</strong>. They keep the query short and are required when both tables have a column
with the same name (<code>p.CategoryID</code> versus <code>c.CategoryID</code>). <code>INNER JOIN</code> and <code>JOIN</code> mean the same.</p>

<h3>More than two tables</h3>
<p>Chain the joins; every <code>JOIN</code> has its own <code>ON</code>:</p>
<pre><code>SELECT o.OrderID, c.CompanyName, e.LastName
FROM Orders AS o
JOIN Customers AS c ON c.CustomerID = o.CustomerID
JOIN Employees AS e ON e.EmployeeID = o.EmployeeID;</code></pre>

<h3>Keeping rows without a match: LEFT JOIN</h3>
<p>An inner join drops a customer that has no orders. <code>LEFT JOIN</code> keeps every row of the left table and fills the
right side with <code>NULL</code> when there is no match. That is how you find the ones <em>without</em>:</p>
<pre><code>SELECT c.CompanyName
FROM Customers AS c
LEFT JOIN Orders AS o ON o.CustomerID = c.CustomerID
WHERE o.OrderID IS NULL;</code></pre>

<h3>Self join</h3>
<p>A table can be joined with itself, using two aliases. <code>Employees.ReportsTo</code> is the <code>EmployeeID</code> of the manager:</p>
<pre><code>SELECT e.LastName AS Employee, m.LastName AS Manager
FROM Employees AS e
JOIN Employees AS m ON m.EmployeeID = e.ReportsTo;</code></pre>

<h3>Many-to-many</h3>
<p>An employee works in many territories and a territory has many employees, so there is a link table
<code>EmployeeTerritories</code> in between. Join through it: <code>Employees → EmployeeTerritories → Territories</code>.</p>

<h3>Join + GROUP BY</h3>
<p>Join first, then group. <code>Orders</code> joined with <code>OrderDetails</code> gives one row per order line, so
<code>SUM(od.UnitPrice * od.Quantity)</code> grouped by <code>o.OrderID</code> is the order total.</p>
HTML,
    'examples' => [
        [
            'title' => 'Two tables',
            'sql'   => "SELECT p.ProductName, c.CategoryName, p.UnitPrice\nFROM Products AS p\nJOIN Categories AS c ON c.CategoryID = p.CategoryID\nORDER BY c.CategoryName, p.ProductName;",
        ],
        [
            'title' => 'LEFT JOIN shows the NULLs',
            'sql'   => "SELECT c.CustomerID, c.CompanyName, o.OrderID\nFROM Customers AS c\nLEFT JOIN Orders AS o ON o.CustomerID = c.CustomerID\nWHERE c.Country = 'Spain'\nORDER BY c.CustomerID, o.OrderID;",
            'note'  => 'Compare with <code>JOIN</code> instead of <code>LEFT JOIN</code>: the Spanish customer without orders disappears.',
        ],
        [
            'title' => 'Join and group: order totals',
            'sql'   => "SELECT o.OrderID, c.CompanyName, SUM(od.UnitPrice * od.Quantity) AS Total\nFROM Orders AS o\nJOIN Customers AS c ON c.CustomerID = o.CustomerID\nJOIN OrderDetails AS od ON od.OrderID = o.OrderID\nWHERE c.Country = 'Belgium'\nGROUP BY o.OrderID, c.CompanyName\nORDER BY Total DESC;",
            'note'  => '<code>CompanyName</code> must be in the GROUP BY because it is in the select list (it is the same for the whole order anyway).',
        ],
    ],
    'exercises' => [
        [
            'title'    => 'Products with their category',
            'task'     => '<p>Show <code>ProductName</code> and <code>CategoryName</code> for every product (two columns, in that order).</p>',
            'hint'     => '<code>JOIN Categories ON Categories.CategoryID = Products.CategoryID</code>.',
            'solution' => "SELECT p.ProductName, c.CategoryName\nFROM Products AS p\nJOIN Categories AS c ON c.CategoryID = p.CategoryID;",
        ],
        [
            'title'    => 'Orders with the customer name',
            'task'     => '<p>Show <code>OrderID</code>, <code>OrderDate</code> and the customer\'s <code>CompanyName</code> for all orders placed in 1998.</p>',
            'hint'     => 'Join <code>Orders</code> and <code>Customers</code> on <code>CustomerID</code>, filter with <code>YEAR(OrderDate) = 1998</code>.',
            'solution' => "SELECT o.OrderID, o.OrderDate, c.CompanyName\nFROM Orders AS o\nJOIN Customers AS c ON c.CustomerID = o.CustomerID\nWHERE YEAR(o.OrderDate) = 1998;",
        ],
        [
            'title'    => 'Japanese suppliers',
            'task'     => '<p>Show <code>ProductName</code> and the supplier\'s <code>CompanyName</code> for all products that come from a supplier in Japan.</p>',
            'hint'     => 'Join on <code>SupplierID</code>; the country is a column of <code>Suppliers</code>, so filter on <code>s.Country</code>.',
            'solution' => "SELECT p.ProductName, s.CompanyName\nFROM Products AS p\nJOIN Suppliers AS s ON s.SupplierID = p.SupplierID\nWHERE s.Country = 'Japan';",
        ],
        [
            'title'    => 'Orders and their shipper',
            'task'     => '<p>For every order shipped to Belgium (<code>ShipCountry</code>), show <code>OrderID</code>, <code>ShipName</code> and the shipper\'s <code>CompanyName</code> (from <code>Shippers</code>).</p>',
            'hint'     => 'The join columns have different names: <code>Orders.ShipVia</code> refers to <code>Shippers.ShipperID</code>.',
            'solution' => "SELECT o.OrderID, o.ShipName, s.CompanyName\nFROM Orders AS o\nJOIN Shippers AS s ON s.ShipperID = o.ShipVia\nWHERE o.ShipCountry = 'Belgium';",
        ],
        [
            'title'    => 'Three tables',
            'task'     => '<p>For every order shipped to Brazil (<code>ShipCountry</code>), show <code>OrderID</code>, the customer\'s <code>CompanyName</code> and the employee\'s <code>LastName</code>.</p>',
            'hint'     => 'Two joins: <code>Customers</code> on <code>CustomerID</code>, <code>Employees</code> on <code>EmployeeID</code>.',
            'solution' => "SELECT o.OrderID, c.CompanyName, e.LastName\nFROM Orders AS o\nJOIN Customers AS c ON c.CustomerID = o.CustomerID\nJOIN Employees AS e ON e.EmployeeID = o.EmployeeID\nWHERE o.ShipCountry = 'Brazil';",
        ],
        [
            'title'    => 'Beverages and their supplier',
            'task'     => '<p>For every product in the category <code>Beverages</code>, show <code>ProductName</code>, the supplier\'s <code>CompanyName</code> (from <code>Suppliers</code>) and <code>CategoryName</code>, in that order.</p>',
            'hint'     => '<code>Products</code> is in the middle: join <code>Suppliers</code> on <code>SupplierID</code> and <code>Categories</code> on <code>CategoryID</code>, then filter on <code>CategoryName</code>.',
            'solution' => "SELECT p.ProductName, s.CompanyName, c.CategoryName\nFROM Products AS p\nJOIN Suppliers AS s ON s.SupplierID = p.SupplierID\nJOIN Categories AS c ON c.CategoryID = p.CategoryID\nWHERE c.CategoryName = 'Beverages';",
        ],
        [
            'title'    => "Fuller's territories",
            'task'     => '<p>Show the <code>TerritoryDescription</code> of every territory of the employee with last name <code>Fuller</code>.</p>',
            'hint'     => 'Three tables: <code>Employees</code> → <code>EmployeeTerritories</code> (on <code>EmployeeID</code>) → <code>Territories</code> (on <code>TerritoryID</code>).',
            'solution' => "SELECT t.TerritoryDescription\nFROM Employees AS e\nJOIN EmployeeTerritories AS et ON et.EmployeeID = e.EmployeeID\nJOIN Territories AS t ON t.TerritoryID = et.TerritoryID\nWHERE e.LastName = 'Fuller';",
        ],
        [
            'title'    => 'Who reports to whom',
            'task'     => '<p>For every employee that has a manager, show the employee\'s <code>LastName</code> and the manager\'s <code>LastName</code> (two columns, employee first).</p>',
            'hint'     => 'Self join: <code>Employees AS e JOIN Employees AS m ON m.EmployeeID = e.ReportsTo</code>.',
            'solution' => "SELECT e.LastName AS Employee, m.LastName AS Manager\nFROM Employees AS e\nJOIN Employees AS m ON m.EmployeeID = e.ReportsTo;",
        ],
        [
            'title'    => 'Orders per customer',
            'task'     => '<p>Show the <code>CompanyName</code> of every customer that has orders, with the number of orders (two columns: <code>CompanyName</code>, number of orders).</p>',
            'hint'     => 'Join, then <code>GROUP BY c.CompanyName</code> with <code>COUNT(*)</code>.',
            'solution' => "SELECT c.CompanyName, COUNT(*) AS Orders\nFROM Customers AS c\nJOIN Orders AS o ON o.CustomerID = c.CustomerID\nGROUP BY c.CompanyName;",
        ],
        [
            'title'    => 'Customers without orders',
            'task'     => '<p>Show the <code>CompanyName</code> of the customers that never placed an order.</p>',
            'hint'     => '<code>LEFT JOIN Orders</code> and keep the rows where <code>o.OrderID IS NULL</code>.',
            'solution' => "SELECT c.CompanyName\nFROM Customers AS c\nLEFT JOIN Orders AS o ON o.CustomerID = c.CustomerID\nWHERE o.OrderID IS NULL;",
        ],
        [
            'title'    => 'Territories without employees',
            'task'     => '<p>Show <code>TerritoryID</code> and <code>TerritoryDescription</code> of the territories to which no employee is assigned.</p>',
            'hint'     => '<code>LEFT JOIN EmployeeTerritories</code> on <code>TerritoryID</code> and keep the rows where <code>EmployeeID IS NULL</code>.',
            'solution' => "SELECT t.TerritoryID, t.TerritoryDescription\nFROM Territories AS t\nLEFT JOIN EmployeeTerritories AS et ON et.TerritoryID = t.TerritoryID\nWHERE et.EmployeeID IS NULL;",
        ],
        [
            'title'    => 'Everyone and their manager',
            'task'     => '<p>Show <em>all</em> employees with their manager, also the one that has no manager. Two columns: <code>Employee</code> (the employee\'s <code>LastName</code>) and <code>Manager</code> (the manager\'s <code>LastName</code>, <code>NULL</code> when there is none). Use exactly these column aliases and sort by <code>Employee</code>.</p>',
            'hint'     => 'Same self join as before, but with <code>LEFT JOIN</code> so that the employee without <code>ReportsTo</code> is kept.',
            'solution' => "SELECT e.LastName AS Employee, m.LastName AS Manager\nFROM Employees AS e\nLEFT JOIN Employees AS m ON m.EmployeeID = e.ReportsTo\nORDER BY e.LastName;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Employees in the Southern region',
            'task'     => '<p>Show the employee\'s <code>LastName</code> and the <code>TerritoryDescription</code> for every territory in the region with <code>RegionDescription</code> <code>Southern</code>. Sort by <code>LastName</code>, then by <code>TerritoryDescription</code>.</p>',
            'hint'     => 'Four tables: <code>Employees</code> → <code>EmployeeTerritories</code> → <code>Territories</code> → <code>Region</code> (on <code>RegionID</code>). Filter on <code>r.RegionDescription</code>.',
            'solution' => "SELECT e.LastName, t.TerritoryDescription\nFROM Employees AS e\nJOIN EmployeeTerritories AS et ON et.EmployeeID = e.EmployeeID\nJOIN Territories AS t ON t.TerritoryID = et.TerritoryID\nJOIN Region AS r ON r.RegionID = t.RegionID\nWHERE r.RegionDescription = 'Southern'\nORDER BY e.LastName, t.TerritoryDescription;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Order totals of ALFKI',
            'task'     => '<p>For every order of customer <code>ALFKI</code>, show <code>OrderID</code> and the order total: the sum of <code>UnitPrice * Quantity</code> over its order lines (two columns, in that order).</p>',
            'hint'     => 'Join <code>Orders</code> with <code>OrderDetails</code> on <code>OrderID</code>, filter on <code>CustomerID</code>, group by <code>OrderID</code>.',
            'solution' => "SELECT o.OrderID, SUM(od.UnitPrice * od.Quantity) AS Total\nFROM Orders AS o\nJOIN OrderDetails AS od ON od.OrderID = o.OrderID\nWHERE o.CustomerID = 'ALFKI'\nGROUP BY o.OrderID;",
        ],
        [
            'title'    => 'Revenue per category',
            'task'     => '<p>For every category, show <code>CategoryName</code> and <code>Revenue</code>: the sum of <code>UnitPrice * Quantity</code> of <code>OrderDetails</code> for all products of that category, rounded to 2 decimals. Use exactly these column names and sort from the highest to the lowest revenue.</p>',
            'hint'     => '<code>Categories</code> → <code>Products</code> (on <code>CategoryID</code>) → <code>OrderDetails</code> (on <code>ProductID</code>), then <code>GROUP BY c.CategoryName</code> and <code>ROUND(SUM(...), 2)</code>.',
            'solution' => "SELECT c.CategoryName, ROUND(SUM(od.UnitPrice * od.Quantity), 2) AS Revenue\nFROM Categories AS c\nJOIN Products AS p ON p.CategoryID = c.CategoryID\nJOIN OrderDetails AS od ON od.ProductID = p.ProductID\nGROUP BY c.CategoryName\nORDER BY Revenue DESC;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Revenue per employee per year',
            'task'     => '<p>For every employee and every year in which that employee took orders, show <code>LastName</code>, <code>OrderYear</code> (the year of <code>OrderDate</code>) and <code>Revenue</code>: the sum of <code>UnitPrice * Quantity</code> over the order lines of those orders, rounded to 2 decimals. Use exactly these column names and sort by <code>LastName</code>, then by <code>OrderYear</code>.</p>',
            'hint'     => '<code>Employees</code> → <code>Orders</code> → <code>OrderDetails</code>, then <code>GROUP BY e.LastName, YEAR(o.OrderDate)</code>.',
            'solution' => "SELECT e.LastName, YEAR(o.OrderDate) AS OrderYear, ROUND(SUM(od.UnitPrice * od.Quantity), 2) AS Revenue\nFROM Employees AS e\nJOIN Orders AS o ON o.EmployeeID = e.EmployeeID\nJOIN OrderDetails AS od ON od.OrderID = o.OrderID\nGROUP BY e.LastName, YEAR(o.OrderDate)\nORDER BY e.LastName, OrderYear;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Top 10 countries by revenue',
            'task'     => '<p>Show the 10 customer countries with the highest revenue: <code>Country</code> (from <code>Customers</code>, not <code>ShipCountry</code>) and <code>Revenue</code>, the sum of <code>UnitPrice * Quantity</code> over all order lines of the customers in that country, rounded to 2 decimals. Use exactly these column names and sort from the highest to the lowest revenue.</p>',
            'hint'     => '<code>Customers</code> → <code>Orders</code> → <code>OrderDetails</code>, <code>GROUP BY c.Country</code>, <code>ORDER BY Revenue DESC LIMIT 10</code>.',
            'solution' => "SELECT c.Country, ROUND(SUM(od.UnitPrice * od.Quantity), 2) AS Revenue\nFROM Customers AS c\nJOIN Orders AS o ON o.CustomerID = c.CustomerID\nJOIN OrderDetails AS od ON od.OrderID = o.OrderID\nGROUP BY c.Country\nORDER BY Revenue DESC\nLIMIT 10;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Freight per customer, zero included',
            'task'     => '<p>For every customer in Spain or France (<code>Customers.Country</code>), show <code>CompanyName</code> and <code>Freight</code>: the total <code>Freight</code> of all its orders, rounded to 2 decimals. Customers without orders must appear with <code>0</code>, not <code>NULL</code>. Use exactly these column names and sort from the highest to the lowest freight, then by <code>CompanyName</code>.</p>',
            'hint'     => '<code>LEFT JOIN Orders</code>, group by customer and wrap the sum: <code>COALESCE(ROUND(SUM(o.Freight), 2), 0)</code>.',
            'solution' => "SELECT c.CompanyName, COALESCE(ROUND(SUM(o.Freight), 2), 0) AS Freight\nFROM Customers AS c\nLEFT JOIN Orders AS o ON o.CustomerID = c.CustomerID\nWHERE c.Country IN ('Spain', 'France')\nGROUP BY c.CompanyName\nORDER BY Freight DESC, c.CompanyName;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'Best sellers',
            'task'     => '<p>Show the products of which more than 1000 units were sold in total: <code>ProductName</code>, <code>CategoryName</code> and <code>Units</code> (the sum of <code>Quantity</code> in <code>OrderDetails</code>). Use exactly these column names, sort from the most to the least units sold, then by <code>ProductName</code>, and show only the first 10 rows.</p>',
            'hint'     => 'Join <code>Products</code> with <code>Categories</code> and <code>OrderDetails</code>, group by product, then <code>HAVING Units &gt; 1000 ORDER BY ... LIMIT 10</code>.',
            'solution' => "SELECT p.ProductName, c.CategoryName, SUM(od.Quantity) AS Units\nFROM Products AS p\nJOIN Categories AS c ON c.CategoryID = p.CategoryID\nJOIN OrderDetails AS od ON od.ProductID = p.ProductID\nGROUP BY p.ProductName, c.CategoryName\nHAVING Units > 1000\nORDER BY Units DESC, p.ProductName\nLIMIT 10;",
            'ordered'  => true,
            'columns'  => true,
        ],
        [
            'title'    => 'German beverage buyers',
            'task'     => '<p>For every German customer (<code>Customers.Country</code>) that bought at least one product of the category <code>Beverages</code>, show the customer\'s <code>CompanyName</code> and <code>Units</code>: the total <code>Quantity</code> of beverages bought over all its orders. Use exactly these column names and sort from the most to the least units, then by <code>CompanyName</code>.</p>',
            'hint'     => 'Five tables: <code>Customers</code> → <code>Orders</code> → <code>OrderDetails</code> → <code>Products</code> → <code>Categories</code>. Filter on country and category, group by customer.',
            'solution' => "SELECT cu.CompanyName, SUM(od.Quantity) AS Units\nFROM Customers AS cu\nJOIN Orders AS o ON o.CustomerID = cu.CustomerID\nJOIN OrderDetails AS od ON od.OrderID = o.OrderID\nJOIN Products AS p ON p.ProductID = od.ProductID\nJOIN Categories AS ca ON ca.CategoryID = p.CategoryID\nWHERE cu.Country = 'Germany' AND ca.CategoryName = 'Beverages'\nGROUP BY cu.CompanyName\nORDER BY Units DESC, cu.CompanyName;",
            'ordered'  => true,
            'columns'  => true,
        ],
    ],
];
