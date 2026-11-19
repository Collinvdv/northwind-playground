<?php
/**
 * Exam: INNER JOIN. Nine assignments, shown on exam-innerjoin.php: six plain
 * joins (easy to hard), then three joins combined with GROUP BY / HAVING.
 * Same exercise format as the lessons (see README "Adding or editing
 * exercises"); kept out of chapters/ so the course never shows them.
 * Only inner joins: no LEFT JOIN.
 */

return [
    'title' => 'Exam: INNER JOIN',
    // Needed to log in; the teacher can change it or close the exam on exam-admin.php?set=inner-join.
    'password' => 'innerjoin',
    'intro' => '<p>Nine assignments. The last three also need <code>GROUP BY</code>. Write one query per assignment and press <strong>Check</strong>. '
        . 'You may check as often as you like; your last checked query counts. '
        . 'Every assignment needs one or more <code>INNER JOIN</code>s. '
        . 'Give the columns in the order the assignment asks; column names (aliases) are free.</p>',
    'exercises' => [
        [
            'title'    => 'Products and their category',
            'task'     => '<p>Show the <code>ProductName</code> of every product together with the <code>CategoryName</code> of its category.</p>'
                . '<p>Columns: product name, category name.</p>',
            'solution' => "SELECT p.ProductName, c.CategoryName\nFROM Products AS p\nINNER JOIN Categories AS c ON c.CategoryID = p.CategoryID;",
        ],
        [
            'title'    => 'German orders of 1998',
            'task'     => '<p>Show the orders placed in 1998 (based on <code>OrderDate</code>) by customers whose <code>Country</code> is <code>Germany</code>: '
                . 'the <code>OrderID</code>, the <code>OrderDate</code> and the <code>CompanyName</code> of the customer.</p>'
                . '<p>Columns: order id, order date, company name.</p>',
            'solution' => "SELECT o.OrderID, o.OrderDate, c.CompanyName\nFROM Orders AS o\nINNER JOIN Customers AS c ON c.CustomerID = o.CustomerID\nWHERE c.Country = 'Germany' AND YEAR(o.OrderDate) = 1998;",
        ],
        [
            'title'    => 'Products from Japan',
            'task'     => '<p>Show every product that is delivered by a supplier from <code>Japan</code>: the <code>ProductName</code>, '
                . 'the <code>CompanyName</code> of the supplier and the <code>CategoryName</code> of the product\'s category.</p>'
                . '<p>Columns: product name, supplier name, category name.</p>',
            'solution' => "SELECT p.ProductName, s.CompanyName, c.CategoryName\nFROM Products AS p\nINNER JOIN Suppliers AS s ON s.SupplierID = p.SupplierID\nINNER JOIN Categories AS c ON c.CategoryID = p.CategoryID\nWHERE s.Country = 'Japan';",
        ],
        [
            'title'    => 'Expensive shipments',
            'task'     => '<p>Show every order with a <code>Freight</code> of <strong>more than 500</strong>: the <code>OrderID</code>, the <code>Freight</code>, '
                . 'the <code>LastName</code> of the employee who took the order and the <code>CompanyName</code> of the shipper that delivered it. '
                . 'Look in the diagram which column of <code>Orders</code> points to <code>Shippers</code>.</p>'
                . '<p>Columns: order id, freight, employee last name, shipper name.</p>',
            'solution' => "SELECT o.OrderID, o.Freight, e.LastName, s.CompanyName\nFROM Orders AS o\nINNER JOIN Employees AS e ON e.EmployeeID = o.EmployeeID\nINNER JOIN Shippers AS s ON s.ShipperID = o.ShipVia\nWHERE o.Freight > 500;",
        ],
        [
            'title'    => 'Employees and their manager',
            'task'     => '<p><code>Employees.ReportsTo</code> holds the <code>EmployeeID</code> of an employee\'s manager. '
                . 'Show the <code>LastName</code> of every employee who has a manager, together with the <code>LastName</code> of that manager.</p>'
                . '<p>Columns: employee last name, manager last name.</p>',
            'solution' => "SELECT e.LastName, m.LastName\nFROM Employees AS e\nINNER JOIN Employees AS m ON m.EmployeeID = e.ReportsTo;",
        ],
        [
            'title'    => 'Big order lines from London',
            'task'     => '<p>Only look at customers whose <code>City</code> is <code>London</code>. Show every order line of their orders with a <code>Quantity</code> of '
                . '<strong>at least 30</strong>: the <code>CompanyName</code> of the customer, the <code>OrderID</code>, the <code>ProductName</code> and the <code>Quantity</code>. '
                . 'Sort by <code>OrderID</code> ascending; within an order by <code>ProductName</code> ascending.</p>'
                . '<p>Columns: company name, order id, product name, quantity.</p>',
            'solution' => "SELECT c.CompanyName, o.OrderID, p.ProductName, od.Quantity\nFROM Customers AS c\nINNER JOIN Orders AS o ON o.CustomerID = c.CustomerID\nINNER JOIN OrderDetails AS od ON od.OrderID = o.OrderID\nINNER JOIN Products AS p ON p.ProductID = od.ProductID\nWHERE c.City = 'London' AND od.Quantity >= 30\nORDER BY o.OrderID, p.ProductName;",
            'ordered'  => true,
        ],
        [
            'title'    => 'Products per category',
            'task'     => '<p>Show for every category the <code>CategoryName</code> and the number of products in that category.</p>'
                . '<p>Columns: category name, number of products.</p>',
            'solution' => "SELECT c.CategoryName, COUNT(*) AS Products\nFROM Products AS p\nINNER JOIN Categories AS c ON c.CategoryID = p.CategoryID\nGROUP BY c.CategoryID, c.CategoryName;",
        ],
        [
            'title'    => 'Suppliers with many products',
            'task'     => '<p>Show the suppliers that deliver <strong>at least 4</strong> products: the <code>CompanyName</code> of the supplier and the number of products.</p>'
                . '<p>Columns: supplier name, number of products.</p>',
            'solution' => "SELECT s.CompanyName, COUNT(*) AS Products\nFROM Suppliers AS s\nINNER JOIN Products AS p ON p.SupplierID = s.SupplierID\nGROUP BY s.SupplierID, s.CompanyName\nHAVING COUNT(*) >= 4;",
        ],
        [
            'title'    => 'Revenue per employee in 1997',
            'task'     => '<p>Only look at orders placed in 1997 (based on <code>OrderDate</code>). Show for every employee the <code>LastName</code> and the total revenue of their orders: '
                . 'the sum of <code>UnitPrice * Quantity</code> over all order lines (ignore <code>Discount</code>), rounded to 2 decimals. '
                . 'Sort by revenue, highest first.</p>'
                . '<p>Columns: employee last name, revenue.</p>',
            'solution' => "SELECT e.LastName, ROUND(SUM(od.UnitPrice * od.Quantity), 2) AS Revenue\nFROM Employees AS e\nINNER JOIN Orders AS o ON o.EmployeeID = e.EmployeeID\nINNER JOIN OrderDetails AS od ON od.OrderID = o.OrderID\nWHERE YEAR(o.OrderDate) = 1997\nGROUP BY e.EmployeeID, e.LastName\nORDER BY Revenue DESC;",
            'ordered'  => true,
        ],
    ],
];
