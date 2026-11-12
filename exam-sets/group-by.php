<?php
/**
 * Exam: GROUP BY. Six assignments, easy to hard, shown on exam-groupby.php.
 * Same exercise format as the chapters (see README "Adding or editing
 * exercises"); kept out of chapters/ so the course never shows them.
 */

return [
    'title' => 'Exam: GROUP BY',
    // Needed to log in; the teacher can change it or close the exam on exam-admin.php.
    'password' => 'ilovecollin',
    'intro' => '<p>Six assignments. Write one query per assignment and press <strong>Check</strong>. '
        . 'You may check as often as you like; your last checked query counts. '
        . 'Give the columns in the order the assignment asks; column names (aliases) are free.</p>',
    'exercises' => [
        [
            'title'    => 'Orders per country',
            'task'     => '<p>From <code>Orders</code>, show every <code>ShipCountry</code> and the number of orders shipped to that country, one row per country.</p>'
                . '<p>Columns: country, number of orders.</p>',
            'solution' => "SELECT ShipCountry, COUNT(*) AS Orders\nFROM Orders\nGROUP BY ShipCountry;",
        ],
        [
            'title'    => 'Active products per category',
            'task'     => '<p>Only look at products that are <em>not</em> discontinued (<code>Discontinued = 0</code>). '
                . 'For each <code>CategoryID</code>, show the number of those products and the highest <code>UnitPrice</code> among them.</p>'
                . '<p>Columns: category id, number of products, highest price.</p>',
            'solution' => "SELECT CategoryID, COUNT(*) AS Products, MAX(UnitPrice) AS MaxPrice\nFROM Products\nWHERE Discontinued = 0\nGROUP BY CategoryID;",
        ],
        [
            'title'    => 'Orders per month in 1997',
            'task'     => '<p>For every month of 1997 (based on <code>OrderDate</code>), show the month number (1 to 12) and the number of orders placed in that month.</p>'
                . '<p>Columns: month number, number of orders.</p>',
            'solution' => "SELECT MONTH(OrderDate) AS Month, COUNT(*) AS Orders\nFROM Orders\nWHERE YEAR(OrderDate) = 1997\nGROUP BY MONTH(OrderDate);",
        ],
        [
            'title'    => 'Suppliers with many products',
            'task'     => '<p>Show the <code>SupplierID</code> and the number of products of every supplier that delivers <strong>at least 4</strong> products.</p>'
                . '<p>Columns: supplier id, number of products.</p>',
            'solution' => "SELECT SupplierID, COUNT(*) AS Products\nFROM Products\nGROUP BY SupplierID\nHAVING COUNT(*) >= 4;",
        ],
        [
            'title'    => 'Best-selling products',
            'task'     => '<p>The revenue of an order line in <code>OrderDetails</code> is <code>UnitPrice * Quantity</code> (ignore the discount). '
                . 'Show the <code>ProductID</code> and the total revenue of every product whose total revenue is <strong>more than 20000</strong>. '
                . 'Round the revenue to 2 decimals.</p>'
                . '<p>Columns: product id, total revenue.</p>',
            'solution' => "SELECT ProductID, ROUND(SUM(UnitPrice * Quantity), 2) AS Revenue\nFROM OrderDetails\nGROUP BY ProductID\nHAVING SUM(UnitPrice * Quantity) > 20000;",
        ],
        [
            'title'    => 'Busy employees in 1998',
            'task'     => '<p>Only look at orders placed in 1998. For each <code>EmployeeID</code>, show the number of orders and the number of <em>different</em> customers they served. '
                . 'Only show employees with <strong>at least 20</strong> orders in 1998. '
                . 'Sort by number of orders, highest first; on a tie by <code>EmployeeID</code> ascending.</p>'
                . '<p>Columns: employee id, number of orders, number of different customers.</p>',
            'solution' => "SELECT EmployeeID, COUNT(*) AS Orders, COUNT(DISTINCT CustomerID) AS Customers\nFROM Orders\nWHERE YEAR(OrderDate) = 1998\nGROUP BY EmployeeID\nHAVING COUNT(*) >= 20\nORDER BY Orders DESC, EmployeeID;",
            'ordered'  => true,
        ],
    ],
];
