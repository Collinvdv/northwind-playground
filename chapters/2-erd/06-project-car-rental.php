<?php
return [
    'title'   => 'Project: Car rental',
    'summary' => 'Branches and cars, customers and rentals, pick-up and return branch, extras and staff. Four steps.',
    'intro'   => <<<'HTML'
<h3>The case</h3>
<p>A car rental company has <strong>branches</strong> in several cities; a branch has a city and an address.
Every <strong>car</strong> has a license plate, a brand, a model and a daily rate, and belongs to one branch.</p>
<p><strong>Customers</strong> are registered with first name, last name and the number of their driver's license (letters and digits).
A customer makes <strong>rentals</strong>: one rental is for one car, from a start day to an end day.</p>
<p>A car is picked up at one branch and may be returned at another one. Both branches are kept for every rental.</p>
<p>With a rental the customer can take <strong>extras</strong> (child seat, GPS, extra insurance); an extra has a name and a price per day.
A rental can have several extras and an extra is taken with many rentals.</p>
<p>The company's <strong>employees</strong> (first name, last name) work at one branch. Every employee has at most one manager,
who is also an employee.</p>

<h3>How a project works</h3>
<p>A project is one case that you design in <strong>four steps</strong>. Every step adds a part of the story to the diagram.
A step starts from the model design of the step before it, so a mistake in step 1 does not follow you to step 4.
Read the whole case first: a good designer knows where the story is going.</p>
<p>The names to use are given in every step. Give every attribute a data type, mark the primary keys, and draw the relationships
from the "one" side to the "many" side.</p>
HTML,
    'exercises' => [
        [
            'type'     => 'erd',
            'title'    => 'Step 1: Branches and cars',
            'task'     => '<p>A branch has a city and an address. A car has a license plate, a brand, a model and a daily rate, and belongs to one branch.</p><p>Draw <code>Branches</code> (<code>BranchID</code>, <code>City</code>, <code>Address</code>) and <code>Cars</code> (<code>CarID</code>, <code>LicensePlate</code>, <code>Brand</code>, <code>Model</code>, <code>DailyRate</code>) and their relationship. The foreign key is called <code>BranchID</code>.</p>',
            'hint'     => 'One branch has many cars. A license plate is text, a daily rate is money.',
            'solution' => 'Branches: BranchID int PK, City varchar, Address varchar
Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 2: Customers and rentals',
            'task'     => '<p>Add the customers (first name, last name, number of the driver\'s license) and their rentals. A rental is made by one customer, for one car, from a start day to an end day.</p><p>Add <code>Customers</code> (<code>CustomerID</code>, <code>FirstName</code>, <code>LastName</code>, <code>DriverLicense</code>) and <code>Rentals</code> (<code>RentalID</code>, <code>StartDate</code>, <code>EndDate</code>) with two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Rentals</code> gets <code>CustomerID</code> and <code>CarID</code>. The license number contains letters, so it is text.',
            'starter'  => 'Branches: BranchID int PK, City varchar, Address varchar
Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches',
            'solution' => 'Branches: BranchID int PK, City varchar, Address varchar
+ Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches
Rentals: RentalID int PK, StartDate date, EndDate date, CustomerID int > Customers, CarID int > Cars
Customers: CustomerID int PK, FirstName varchar, LastName varchar, DriverLicense varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 3: Pick-up and return',
            'task'     => '<p>A car is picked up at one branch and may be returned at another. Both are kept for every rental.</p><p>Add two relationships between <code>Branches</code> and <code>Rentals</code>. Call the foreign keys <code>PickupBranchID</code> and <code>ReturnBranchID</code>.</p>',
            'hint'     => 'Drag from <code>Branches</code> to <code>Rentals</code> twice and rename the two new attributes: one column could not tell pick-up from return.',
            'starter'  => 'Branches: BranchID int PK, City varchar, Address varchar
+ Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches
Rentals: RentalID int PK, StartDate date, EndDate date, CustomerID int > Customers, CarID int > Cars
Customers: CustomerID int PK, FirstName varchar, LastName varchar, DriverLicense varchar',
            'solution' => 'Branches: BranchID int PK, City varchar, Address varchar
+ Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches
Rentals: RentalID int PK, StartDate date, EndDate date, CustomerID int > Customers, CarID int > Cars, PickupBranchID int > Branches, ReturnBranchID int > Branches
Customers: CustomerID int PK, FirstName varchar, LastName varchar, DriverLicense varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 4: Extras and employees',
            'task'     => '<p>With a rental the customer can take extras; an extra has a name and a price per day. A rental can have several extras and an extra is taken with many rentals. The employees (first name, last name) work at one branch, and every employee has at most one manager, who is also an employee.</p><p>Add <code>Extras</code> (<code>ExtraID</code>, <code>Name</code>, <code>DailyPrice</code>) and the link table <code>RentalExtras</code> (composite key, no other attributes). Add <code>Employees</code> (<code>EmployeeID</code>, <code>FirstName</code>, <code>LastName</code>) with a foreign key <code>BranchID</code> and a foreign key <code>ManagerID</code> to itself.</p>',
            'hint'     => '<code>RentalExtras</code> gets <code>RentalID</code> and <code>ExtraID</code>, both PK. For the manager: click a connector of <code>Employees</code>, then <code>Employees</code> itself, and rename the new attribute.',
            'starter'  => 'Branches: BranchID int PK, City varchar, Address varchar
+ Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches
Rentals: RentalID int PK, StartDate date, EndDate date, CustomerID int > Customers, CarID int > Cars, PickupBranchID int > Branches, ReturnBranchID int > Branches
Customers: CustomerID int PK, FirstName varchar, LastName varchar, DriverLicense varchar',
            'solution' => 'Branches: BranchID int PK, City varchar, Address varchar
+ Cars: CarID int PK, LicensePlate varchar, Brand varchar, Model varchar, DailyRate decimal, BranchID int > Branches
Rentals: RentalID int PK, StartDate date, EndDate date, CustomerID int > Customers, CarID int > Cars, PickupBranchID int > Branches, ReturnBranchID int > Branches
+ RentalExtras: RentalID int PK > Rentals, ExtraID int PK > Extras
Customers: CustomerID int PK, FirstName varchar, LastName varchar, DriverLicense varchar
+ Extras: ExtraID int PK, Name varchar, DailyPrice decimal
Employees: EmployeeID int PK, FirstName varchar, LastName varchar, BranchID int > Branches, ManagerID int > Employees',
        ],
    ],
];
