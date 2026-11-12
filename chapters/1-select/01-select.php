<?php
return [
    'title'   => 'SELECT and FROM',
    'summary' => 'Choose columns from a table, rename them with AS, compute new values.',
    'intro'   => <<<'HTML'
<h3>The smallest query</h3>
<p>A query asks the database for a table of results. The two words you always need are
<code>SELECT</code> (which columns) and <code>FROM</code> (which table):</p>
<pre><code>SELECT ProductName, UnitPrice
FROM Products;</code></pre>
<p><code>*</code> means "all columns". Use it to explore a table, but in real queries name the columns you need:</p>
<pre><code>SELECT * FROM Shippers;</code></pre>
<p>Keywords are not case sensitive (<code>select</code> works too), but by convention we write them in capitals.
Table and column names are written exactly as they exist in the database. A statement ends with a semicolon.</p>

<h3>Renaming columns: AS</h3>
<p>Give a column a different name in the result with <code>AS</code>. The table itself does not change.</p>
<pre><code>SELECT CompanyName AS Supplier, City AS Town
FROM Suppliers;</code></pre>
<p>A name with spaces needs backticks: <code>AS `Unit price`</code>.</p>

<h3>Calculated columns</h3>
<p>The select list may contain expressions: arithmetic (<code>+ - * /</code>), text functions, constants.
Always give a calculated column a name with <code>AS</code>.</p>
<pre><code>SELECT ProductName, UnitPrice, UnitPrice * 1.21 AS PriceWithVat
FROM Products;</code></pre>
<p><code>CONCAT()</code> glues text together:</p>
<pre><code>SELECT CONCAT(FirstName, ' ', LastName) AS FullName
FROM Employees;</code></pre>

<h3>The Northwind database</h3>
<p>Northwind is a small trading company. The tables you will use most:</p>
<table>
<tr><th>Table</th><th>Contains</th><th>Key</th></tr>
<tr><td><code>Customers</code></td><td>companies that buy from Northwind</td><td><code>CustomerID</code> (text, e.g. ALFKI)</td></tr>
<tr><td><code>Orders</code></td><td>one row per order: customer, employee, dates, shipping</td><td><code>OrderID</code></td></tr>
<tr><td><code>OrderDetails</code></td><td>the lines of an order: product, unit price, quantity</td><td><code>OrderID</code> + <code>ProductID</code></td></tr>
<tr><td><code>Products</code></td><td>what is sold: price, stock, category, supplier</td><td><code>ProductID</code></td></tr>
<tr><td><code>Categories</code>, <code>Suppliers</code>, <code>Shippers</code>, <code>Employees</code></td><td>lookup tables</td><td>their <code>...ID</code></td></tr>
</table>
<p>The sidebar on every exercise page lists all tables with their columns.</p>
HTML,
    'examples' => [
        [
            'title' => 'All columns of a small table',
            'sql'   => "SELECT *\nFROM Categories;",
            'note'  => 'Eight categories. <code>Picture</code> is a binary column, the playground shows its size instead of the bytes.',
        ],
        [
            'title' => 'A few columns, renamed',
            'sql'   => "SELECT CompanyName AS Customer, City, Country\nFROM Customers;",
            'note'  => 'The result has a column called <code>Customer</code>; the table still has <code>CompanyName</code>.',
        ],
        [
            'title' => 'A calculated column',
            'sql'   => "SELECT ProductName, UnitPrice, UnitsInStock, UnitPrice * UnitsInStock AS StockValue\nFROM Products;",
            'note'  => 'Every row is computed separately. No <code>AS</code> would give the column the ugly name <code>UnitPrice * UnitsInStock</code>.',
        ],
    ],
    'exercises' => [
        [
            'title'    => 'All shippers',
            'task'     => '<p>Show all columns of the <code>Shippers</code> table.</p>',
            'hint'     => '<code>*</code> selects every column.',
            'solution' => "SELECT *\nFROM Shippers;",
        ],
        [
            'title'    => 'All regions',
            'task'     => '<p>Show all columns of the <code>Region</code> table.</p>',
            'hint'     => 'Same as the previous exercise, another table. The table name is singular: <code>Region</code>.',
            'solution' => "SELECT *\nFROM Region;",
        ],
        [
            'title'    => 'Category names',
            'task'     => '<p>Show the <code>CategoryName</code> and <code>Description</code> of every category.</p>',
            'hint'     => 'Name the two columns after <code>SELECT</code>, separated by a comma. The table is <code>Categories</code>.',
            'solution' => "SELECT CategoryName, Description\nFROM Categories;",
        ],
        [
            'title'    => 'Product prices',
            'task'     => '<p>Show the name and the unit price of every product, in that order (two columns: <code>ProductName</code>, <code>UnitPrice</code>).</p>',
            'hint'     => 'Both columns come from <code>Products</code>.',
            'solution' => "SELECT ProductName, UnitPrice\nFROM Products;",
        ],
        [
            'title'    => 'Customer contacts',
            'task'     => '<p>Show <code>CompanyName</code>, <code>ContactName</code> and <code>Phone</code> of every customer, in that order.</p>',
            'hint'     => 'Three columns from <code>Customers</code>.',
            'solution' => "SELECT CompanyName, ContactName, Phone\nFROM Customers;",
        ],
        [
            'title'    => 'Employee titles',
            'task'     => '<p>Show <code>FirstName</code>, <code>LastName</code> and <code>Title</code> of every employee, in that order.</p>',
            'hint'     => 'Three columns from <code>Employees</code>. <code>Title</code> is the job title, not <code>TitleOfCourtesy</code>.',
            'solution' => "SELECT FirstName, LastName, Title\nFROM Employees;",
        ],
        [
            'title'    => 'Rename columns',
            'task'     => '<p>Show every supplier\'s <code>CompanyName</code> as <code>Supplier</code> and <code>Country</code> as <code>Land</code>. The column names in your result are checked.</p>',
            'hint'     => '<code>column AS NewName</code>, for both columns.',
            'solution' => "SELECT CompanyName AS Supplier, Country AS Land\nFROM Suppliers;",
            'columns'  => true,
        ],
        [
            'title'    => 'Territories renamed',
            'task'     => '<p>Show every territory\'s <code>TerritoryDescription</code> as <code>Territory</code> and <code>RegionID</code> as <code>Region</code>, in that order. Column names are checked.</p>',
            'hint'     => 'Two columns from <code>Territories</code>, each with its own <code>AS</code>.',
            'solution' => "SELECT TerritoryDescription AS Territory, RegionID AS Region\nFROM Territories;",
            'columns'  => true,
        ],
        [
            'title'    => 'Shipping destinations',
            'task'     => '<p>For every order show <code>OrderID</code> (keep that name), <code>ShipName</code> as <code>Recipient</code> and <code>ShipCountry</code> as <code>Destination</code>, in that order. Column names are checked.</p>',
            'hint'     => 'A column without <code>AS</code> keeps its own name. The table is <code>Orders</code>.',
            'solution' => "SELECT OrderID, ShipName AS Recipient, ShipCountry AS Destination\nFROM Orders;",
            'columns'  => true,
        ],
        [
            'title'    => 'Freight with handling fee',
            'task'     => '<p>For every order show <code>OrderID</code>, <code>Freight</code> and the freight plus a fixed handling fee of 5 in a column called <code>FreightWithHandling</code>. Column names are checked.</p>',
            'hint'     => 'Add a constant to a column: <code>Freight + 5</code>, then give it a name with <code>AS</code>.',
            'solution' => "SELECT OrderID, Freight, Freight + 5 AS FreightWithHandling\nFROM Orders;",
            'columns'  => true,
        ],
        [
            'title'    => 'Stock value',
            'task'     => '<p>For every product show <code>ProductName</code> and the value of its stock (<code>UnitPrice</code> times <code>UnitsInStock</code>) in a column called <code>StockValue</code>. Column names are checked.</p>',
            'hint'     => 'Multiply the two columns with <code>*</code> and give the result a name with <code>AS StockValue</code>.',
            'solution' => "SELECT ProductName, UnitPrice * UnitsInStock AS StockValue\nFROM Products;",
            'columns'  => true,
        ],
        [
            'title'    => 'Full names',
            'task'     => '<p>Show one column <code>FullName</code> with the first name, a space and the last name of every employee, for example <code>Nancy Davolio</code>. Column names are checked.</p>',
            'hint'     => "<code>CONCAT(FirstName, ' ', LastName)</code>. Text constants go between single quotes.",
            'solution' => "SELECT CONCAT(FirstName, ' ', LastName) AS FullName\nFROM Employees;",
            'columns'  => true,
        ],
        [
            'title'    => 'Total units',
            'task'     => '<p>For every product show <code>ProductName</code>, <code>UnitsInStock</code>, <code>UnitsOnOrder</code> and the sum of those two numbers in a column called <code>TotalUnits</code>, in that order. Column names are checked.</p>',
            'hint'     => 'Four columns in the select list; the last one is <code>UnitsInStock + UnitsOnOrder AS TotalUnits</code>.',
            'solution' => "SELECT ProductName, UnitsInStock, UnitsOnOrder, UnitsInStock + UnitsOnOrder AS TotalUnits\nFROM Products;",
            'columns'  => true,
        ],
        [
            'title'    => 'Supplier location',
            'task'     => '<p>For every supplier show <code>CompanyName</code> and one column <code>Location</code> containing the city, a comma, a space and the country, for example <code>London, UK</code>. Column names are checked.</p>',
            'hint'     => "The separator is a text constant: <code>CONCAT(City, ', ', Country)</code>.",
            'solution' => "SELECT CompanyName, CONCAT(City, ', ', Country) AS Location\nFROM Suppliers;",
            'columns'  => true,
        ],
        [
            'title'    => 'Monthly salary',
            'task'     => '<p>For every employee show <code>FirstName</code>, <code>LastName</code>, <code>Salary</code> (the yearly salary) and the salary divided by 12 in a column called <code>MonthlySalary</code>, in that order. Column names are checked.</p>',
            'hint'     => 'Division is <code>/</code>: <code>Salary / 12 AS MonthlySalary</code>.',
            'solution' => "SELECT FirstName, LastName, Salary, Salary / 12 AS MonthlySalary\nFROM Employees;",
            'columns'  => true,
        ],
        [
            'title'    => 'Stock shortage',
            'task'     => '<p>For every product show <code>ProductName</code>, <code>UnitsInStock</code>, <code>ReorderLevel</code> and how many units are missing to reach the reorder level (<code>ReorderLevel</code> minus <code>UnitsInStock</code>) in a column called <code>Shortage</code>, in that order. A negative number means there is more than enough stock. Column names are checked.</p>',
            'hint'     => 'Subtraction is <code>-</code>. Mind the order: reorder level first, stock second.',
            'solution' => "SELECT ProductName, UnitsInStock, ReorderLevel, ReorderLevel - UnitsInStock AS Shortage\nFROM Products;",
            'columns'  => true,
        ],
        [
            'title'    => 'Employee address line',
            'task'     => '<p>For every employee show <code>LastName</code> and one column <code>AddressLine</code> with the address, a comma and a space, the postal code, a space and the city, for example <code>507 - 20th Ave. E. Apt. 2A, 98122 Seattle</code>. Column names are checked.</p>',
            'hint'     => "<code>CONCAT()</code> takes as many arguments as you like: columns and text constants alternate: <code>CONCAT(Address, ', ', PostalCode, ' ', City)</code>.",
            'solution' => "SELECT LastName, CONCAT(Address, ', ', PostalCode, ' ', City) AS AddressLine\nFROM Employees;",
            'columns'  => true,
        ],
        [
            'title'    => 'Discounted price with VAT',
            'task'     => '<p>Northwind gives 10% off every product and then adds 21% VAT. For every product show <code>ProductName</code>, <code>UnitPrice</code>, the price after the 10% discount in a column <code>DiscountedPrice</code> (<code>UnitPrice</code> times 0.9) and the discounted price including VAT in a column <code>DiscountedPriceWithVat</code> (the discounted price times 1.21), in that order. Column names are checked.</p>',
            'hint'     => 'Use parentheses to make the order of operations clear: <code>(UnitPrice * 0.9) * 1.21</code>.',
            'solution' => "SELECT ProductName, UnitPrice, UnitPrice * 0.9 AS DiscountedPrice, (UnitPrice * 0.9) * 1.21 AS DiscountedPriceWithVat\nFROM Products;",
            'columns'  => true,
        ],
        [
            'title'    => 'Employee card',
            'task'     => '<p>For every employee show <code>EmployeeID</code> and one column <code>EmployeeCard</code> made of the title of courtesy, a space, the first name, a space, the last name, a space and the job title between parentheses, for example <code>Ms. Nancy Davolio (Sales Representative)</code>. Column names are checked.</p>',
            'hint'     => "Five columns and four text constants in one <code>CONCAT()</code>: <code>' '</code>, <code>' '</code>, <code>' ('</code> and <code>')'</code>. The columns are <code>TitleOfCourtesy</code>, <code>FirstName</code>, <code>LastName</code> and <code>Title</code>.",
            'solution' => "SELECT EmployeeID, CONCAT(TitleOfCourtesy, ' ', FirstName, ' ', LastName, ' (', Title, ')') AS EmployeeCard\nFROM Employees;",
            'columns'  => true,
        ],
        [
            'title'    => 'Shipping invoice',
            'task'     => '<p>For every order show, in this order: <code>OrderID</code>; a column <code>Destination</code> with the ship name, a space, a hyphen, a space, the ship city, a space and the ship country between parentheses, for example <code>Vins et alcools Chevalier - Reims (France)</code>; <code>Freight</code>; the freight plus 21% VAT in a column <code>FreightWithVat</code> (<code>Freight</code> times 1.21); and the freight with VAT plus a fixed handling fee of 10 in a column <code>TotalShipping</code>. Column names are checked.</p>',
            'hint'     => "Columns are <code>ShipName</code>, <code>ShipCity</code> and <code>ShipCountry</code>. For the last column: <code>(Freight * 1.21) + 10</code>.",
            'solution' => "SELECT OrderID, CONCAT(ShipName, ' - ', ShipCity, ' (', ShipCountry, ')') AS Destination, Freight, Freight * 1.21 AS FreightWithVat, (Freight * 1.21) + 10 AS TotalShipping\nFROM Orders;",
            'columns'  => true,
        ],
    ],
];
