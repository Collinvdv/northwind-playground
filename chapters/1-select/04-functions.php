<?php
return [
    'title'   => 'Functions and expressions',
    'summary' => 'Round numbers, change text, take dates apart, handle NULL, choose with CASE.',
    'intro'   => <<<'HTML'
<h3>Functions work on one row at a time</h3>
<p>A function takes values from the current row and returns a new value. You can use it in the select list, in
<code>WHERE</code> and in <code>ORDER BY</code>. The functions below are the ones you will need most in MySQL.</p>

<h3>Numbers</h3>
<table>
<tr><th>Function</th><th>Result</th></tr>
<tr><td><code>ROUND(x, 2)</code></td><td>x rounded to 2 decimals (<code>ROUND(x)</code>: to a whole number)</td></tr>
<tr><td><code>FLOOR(x)</code>, <code>CEILING(x)</code></td><td>round down / up</td></tr>
<tr><td><code>ABS(x)</code>, <code>MOD(a, b)</code></td><td>absolute value, remainder</td></tr>
</table>

<h3>Text</h3>
<table>
<tr><th>Function</th><th>Result</th></tr>
<tr><td><code>UPPER(s)</code>, <code>LOWER(s)</code></td><td>capitals / small letters</td></tr>
<tr><td><code>LENGTH(s)</code></td><td>number of characters (bytes, for plain text the same)</td></tr>
<tr><td><code>CONCAT(a, b, ...)</code></td><td>glue together; <code>CONCAT_WS(' ', a, b)</code> puts a separator between</td></tr>
<tr><td><code>LEFT(s, n)</code>, <code>RIGHT(s, n)</code>, <code>SUBSTRING(s, start, n)</code></td><td>parts of a text</td></tr>
<tr><td><code>TRIM(s)</code>, <code>REPLACE(s, 'a', 'b')</code></td><td>remove spaces, replace text</td></tr>
</table>

<h3>Dates</h3>
<table>
<tr><th>Function</th><th>Result</th></tr>
<tr><td><code>YEAR(d)</code>, <code>MONTH(d)</code>, <code>DAY(d)</code></td><td>parts of a date as numbers</td></tr>
<tr><td><code>DATE_FORMAT(d, '%d/%m/%Y')</code></td><td>the date as text in a chosen layout</td></tr>
<tr><td><code>DATEDIFF(later, earlier)</code></td><td>number of days between two dates</td></tr>
<tr><td><code>DATE_ADD(d, INTERVAL 7 DAY)</code></td><td>a date shifted</td></tr>
<tr><td><code>CURDATE()</code>, <code>NOW()</code></td><td>today / now</td></tr>
</table>

<h3>NULL</h3>
<p>Any calculation with <code>NULL</code> gives <code>NULL</code> (<code>NULL + 1</code> is <code>NULL</code>, <code>CONCAT('a', NULL)</code> is <code>NULL</code>).
<code>IFNULL(x, replacement)</code> gives the replacement when x is NULL; <code>COALESCE(a, b, c)</code> gives the first value that is not NULL.</p>

<h3>Choosing a value: CASE</h3>
<pre><code>SELECT ProductName,
       CASE
         WHEN UnitPrice &lt; 20 THEN 'cheap'
         WHEN UnitPrice &lt; 50 THEN 'medium'
         ELSE 'expensive'
       END AS PriceClass
FROM Products;</code></pre>
<p>The first <code>WHEN</code> that is true wins. Without <code>ELSE</code> the result is <code>NULL</code> when nothing matches.</p>
HTML,
    'examples' => [
        [
            'title' => 'Text functions',
            'sql'   => "SELECT CompanyName, UPPER(CompanyName) AS Upper, LENGTH(CompanyName) AS Len, LEFT(CompanyName, 3) AS Abbrev\nFROM Shippers;",
        ],
        [
            'title' => 'Date functions',
            'sql'   => "SELECT OrderID, OrderDate, YEAR(OrderDate) AS Yr, DATE_FORMAT(OrderDate, '%d/%m/%Y') AS Formatted,\n       DATEDIFF(ShippedDate, OrderDate) AS DaysToShip\nFROM Orders\nWHERE CustomerID = 'ALFKI';",
            'note'  => '<code>DaysToShip</code> is NULL for an order that has not been shipped.',
        ],
        [
            'title' => 'IFNULL and CASE',
            'sql'   => "SELECT CompanyName, IFNULL(Fax, 'no fax') AS Fax,\n       CASE WHEN Country IN ('Germany', 'Austria', 'Switzerland') THEN 'DACH' ELSE 'other' END AS Zone\nFROM Customers\nWHERE Country IN ('Germany', 'Austria', 'Switzerland', 'Belgium');",
        ],
    ],
    'exercises' => [
        [
            'title'    => 'Rounded prices',
            'task'     => '<p>Show <code>ProductName</code> and the <code>UnitPrice</code> rounded to a whole number, for every product.</p>',
            'hint'     => '<code>ROUND(UnitPrice)</code>; one argument rounds to 0 decimals.',
            'solution' => "SELECT ProductName, ROUND(UnitPrice) AS RoundedPrice\nFROM Products;",
        ],
        [
            'title'    => 'Round down and up',
            'task'     => '<p>Show <code>ProductName</code>, the <code>UnitPrice</code> rounded down to a whole number and the <code>UnitPrice</code> rounded up to a whole number (three columns), for every product.</p>',
            'hint'     => '<code>FLOOR(UnitPrice)</code> and <code>CEILING(UnitPrice)</code>.',
            'solution' => "SELECT ProductName, FLOOR(UnitPrice) AS PriceDown, CEILING(UnitPrice) AS PriceUp\nFROM Products;",
        ],
        [
            'title'    => 'Shouting shippers',
            'task'     => '<p>Show the company name of every shipper in capitals, and the number of characters in it (two columns).</p>',
            'hint'     => '<code>UPPER(CompanyName)</code> and <code>LENGTH(CompanyName)</code>.',
            'solution' => "SELECT UPPER(CompanyName) AS Name, LENGTH(CompanyName) AS Len\nFROM Shippers;",
        ],
        [
            'title'    => 'First three letters',
            'task'     => '<p>For the customers in <code>France</code>, show <code>CompanyName</code> and the first three characters of the company name (two columns).</p>',
            'hint'     => '<code>LEFT(CompanyName, 3)</code>.',
            'solution' => "SELECT CompanyName, LEFT(CompanyName, 3) AS Abbrev\nFROM Customers\nWHERE Country = 'France';",
        ],
        [
            'title'    => 'Formal names',
            'task'     => '<p>Show one column <code>FullName</code> for every employee with the title of courtesy, first name and last name separated by spaces, for example <code>Ms. Nancy Davolio</code>. Column names are checked.</p>',
            'hint'     => "<code>CONCAT_WS(' ', TitleOfCourtesy, FirstName, LastName)</code> or <code>CONCAT</code> with explicit spaces.",
            'solution' => "SELECT CONCAT_WS(' ', TitleOfCourtesy, FirstName, LastName) AS FullName\nFROM Employees;",
            'columns'  => true,
        ],
        [
            'title'    => 'Year and month',
            'task'     => '<p>For the orders of customer <code>ALFKI</code>, show <code>OrderID</code>, the year of the order date and the month of the order date (three columns, numbers).</p>',
            'hint'     => '<code>YEAR(OrderDate)</code> and <code>MONTH(OrderDate)</code>, with a <code>WHERE</code> on <code>CustomerID</code>.',
            'solution' => "SELECT OrderID, YEAR(OrderDate) AS Yr, MONTH(OrderDate) AS Mon\nFROM Orders\nWHERE CustomerID = 'ALFKI';",
        ],
        [
            'title'    => 'No fax',
            'task'     => '<p>Show <code>CompanyName</code> and the fax number of every customer in the UK. When the fax is missing, show the text <code>none</code> instead.</p>',
            'hint'     => "<code>IFNULL(Fax, 'none')</code>.",
            'solution' => "SELECT CompanyName, IFNULL(Fax, 'none') AS Fax\nFROM Customers\nWHERE Country = 'UK';",
        ],
        [
            'title'    => 'Underscores',
            'task'     => '<p>For the products with <code>CategoryID</code> 1, show <code>ProductName</code> and the product name with every space replaced by an underscore (<code>_</code>), for example <code>Sasquatch_Ale</code> (two columns).</p>',
            'hint'     => "<code>REPLACE(ProductName, ' ', '_')</code>.",
            'solution' => "SELECT ProductName, REPLACE(ProductName, ' ', '_') AS Slug\nFROM Products\nWHERE CategoryID = 1;",
        ],
        [
            'title'    => 'Days to ship',
            'task'     => '<p>For the orders of customer <code>QUICK</code> that have been shipped, show <code>OrderID</code> and the number of days between the order date and the shipping date.</p>',
            'hint'     => '<code>DATEDIFF(ShippedDate, OrderDate)</code>; shipped means <code>ShippedDate IS NOT NULL</code>.',
            'solution' => "SELECT OrderID, DATEDIFF(ShippedDate, OrderDate) AS DaysToShip\nFROM Orders\nWHERE CustomerID = 'QUICK' AND ShippedDate IS NOT NULL;",
        ],
        [
            'title'    => 'Day and month names',
            'task'     => '<p>For the orders of customer <code>ALFKI</code>, show <code>OrderID</code>, the name of the weekday of the order date (for example <code>Monday</code>) and the name of the month of the order date (for example <code>August</code>), three columns.</p>',
            'hint'     => '<code>DAYNAME(OrderDate)</code> and <code>MONTHNAME(OrderDate)</code>.',
            'solution' => "SELECT OrderID, DAYNAME(OrderDate) AS Weekday, MONTHNAME(OrderDate) AS MonthName\nFROM Orders\nWHERE CustomerID = 'ALFKI';",
        ],
        [
            'title'    => 'Thirty days later',
            'task'     => '<p>For the orders of customer <code>BONAP</code>, show <code>OrderID</code>, <code>OrderDate</code> and the date exactly 30 days after the order date (three columns).</p>',
            'hint'     => '<code>DATE_ADD(OrderDate, INTERVAL 30 DAY)</code>.',
            'solution' => "SELECT OrderID, OrderDate, DATE_ADD(OrderDate, INTERVAL 30 DAY) AS PayBy\nFROM Orders\nWHERE CustomerID = 'BONAP';",
        ],
        [
            'title'    => 'Formatted order dates',
            'task'     => '<p>For the orders of customer <code>HANAR</code>, show <code>OrderID</code> and the order date as text in the form day/month/year with two digits for day and month and four for the year, for example <code>14/10/1996</code>. Name the second column <code>OrderDay</code>. Column names are checked.</p>',
            'hint'     => "<code>DATE_FORMAT(OrderDate, '%d/%m/%Y')</code>.",
            'solution' => "SELECT OrderID, DATE_FORMAT(OrderDate, '%d/%m/%Y') AS OrderDay\nFROM Orders\nWHERE CustomerID = 'HANAR';",
            'columns'  => true,
        ],
        [
            'title'    => 'Price with VAT',
            'task'     => '<p>For the products with <code>CategoryID</code> 2, show <code>ProductName</code>, <code>UnitPrice</code> and the price including 21% VAT (<code>UnitPrice * 1.21</code>) rounded to 2 decimals, three columns.</p>',
            'hint'     => '<code>ROUND(UnitPrice * 1.21, 2)</code>.',
            'solution' => "SELECT ProductName, UnitPrice, ROUND(UnitPrice * 1.21, 2) AS PriceWithVat\nFROM Products\nWHERE CategoryID = 2;",
        ],
        [
            'title'    => 'Area codes',
            'task'     => '<p>The phone numbers of the customers in the <code>USA</code> look like <code>(503) 555-7555</code>. Show <code>CompanyName</code>, <code>Phone</code> and the three-digit area code between the brackets, for example <code>503</code> (three columns).</p>',
            'hint'     => '<code>SUBSTRING(Phone, 2, 3)</code>: start at position 2, take 3 characters.',
            'solution' => "SELECT CompanyName, Phone, SUBSTRING(Phone, 2, 3) AS AreaCode\nFROM Customers\nWHERE Country = 'USA';",
        ],
        [
            'title'    => 'Stock status',
            'task'     => '<p>Show <code>ProductName</code>, <code>UnitsInStock</code> and a third column that says <code>out of stock</code> when the stock is 0, <code>low</code> when it is below 20, and <code>ok</code> otherwise.</p>',
            'hint'     => '<code>CASE WHEN UnitsInStock = 0 THEN ... WHEN UnitsInStock &lt; 20 THEN ... ELSE ... END</code>. The order of the WHENs matters.',
            'solution' => "SELECT ProductName, UnitsInStock,\n       CASE\n         WHEN UnitsInStock = 0 THEN 'out of stock'\n         WHEN UnitsInStock < 20 THEN 'low'\n         ELSE 'ok'\n       END AS Status\nFROM Products;",
        ],
        [
            'title'    => 'Email addresses',
            'task'     => '<p>Show <code>LastName</code> and an email address <code>Email</code> for every employee: the first letter of the first name, the full last name and <code>@northwind.com</code>, all in small letters, for example <code>ndavolio@northwind.com</code>. Column names are checked.</p>',
            'hint'     => "<code>LOWER(CONCAT(LEFT(FirstName, 1), LastName, '@northwind.com'))</code>: functions can be nested.",
            'solution' => "SELECT LastName, LOWER(CONCAT(LEFT(FirstName, 1), LastName, '@northwind.com')) AS Email\nFROM Employees;",
            'columns'  => true,
        ],
        [
            'title'    => 'Long company names',
            'task'     => '<p>Show <code>CompanyName</code> and its number of characters for the customers whose company name is longer than 25 characters. Sort from longest to shortest; equal lengths alphabetically on <code>CompanyName</code>.</p>',
            'hint'     => '<code>LENGTH(CompanyName)</code> works in <code>WHERE</code> and <code>ORDER BY</code> too.',
            'solution' => "SELECT CompanyName, LENGTH(CompanyName) AS Len\nFROM Customers\nWHERE LENGTH(CompanyName) > 25\nORDER BY Len DESC, CompanyName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Late shipments',
            'task'     => '<p>Show <code>OrderID</code>, <code>RequiredDate</code>, <code>ShippedDate</code> and the number of days the shipment was late, for the orders that were shipped after the required date (four columns). Orders that were never shipped do not count.</p>',
            'hint'     => '<code>DATEDIFF(ShippedDate, RequiredDate)</code>; use it in the <code>WHERE</code> with <code>&gt; 0</code> (a NULL ShippedDate gives NULL and is not greater than 0).',
            'solution' => "SELECT OrderID, RequiredDate, ShippedDate, DATEDIFF(ShippedDate, RequiredDate) AS DaysLate\nFROM Orders\nWHERE DATEDIFF(ShippedDate, RequiredDate) > 0;",
        ],
        [
            'title'    => 'Shipping status',
            'task'     => '<p>For the orders of employee 4, show <code>OrderID</code>, <code>RequiredDate</code>, <code>ShippedDate</code> and a column <code>Status</code> with the text <code>not shipped</code> when there is no shipping date, <code>late</code> when the shipping date is after the required date, and <code>on time</code> otherwise. Column names are checked.</p>',
            'hint'     => '<code>CASE WHEN ShippedDate IS NULL THEN ... WHEN ShippedDate &gt; RequiredDate THEN ... ELSE ... END</code>. Test for NULL first.',
            'solution' => "SELECT OrderID, RequiredDate, ShippedDate,\n       CASE\n         WHEN ShippedDate IS NULL THEN 'not shipped'\n         WHEN ShippedDate > RequiredDate THEN 'late'\n         ELSE 'on time'\n       END AS Status\nFROM Orders\nWHERE EmployeeID = 4;",
            'columns'  => true,
        ],
        [
            'title'    => 'Hired in words',
            'task'     => '<p>For every employee show <code>LastName</code>, the hire date as text <code>HiredOn</code> in the form weekday name, day (two digits), full month name, year, for example <code>Friday 01 May 1992</code>, and the age in whole years at the hire date as <code>AgeAtHire</code>, computed as the number of days between birth date and hire date divided by 365.25 and rounded down. Column names are checked.</p>',
            'hint'     => "<code>DATE_FORMAT(HireDate, '%W %d %M %Y')</code> and <code>FLOOR(DATEDIFF(HireDate, BirthDate) / 365.25)</code>.",
            'solution' => "SELECT LastName, DATE_FORMAT(HireDate, '%W %d %M %Y') AS HiredOn,\n       FLOOR(DATEDIFF(HireDate, BirthDate) / 365.25) AS AgeAtHire\nFROM Employees;",
            'columns'  => true,
        ],
    ],
];
