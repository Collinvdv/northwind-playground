<?php
return [
    'title'   => 'Entity-relationship diagrams',
    'summary' => 'Entities, attributes, data types, keys and relationships, with a dozen worked examples. Then draw your own diagrams.',
    'intro'   => <<<'HTML'
<h3>What is an ERD?</h3>
<p>An <strong>entity-relationship diagram</strong> (ERD) is the map of a database: which things it stores and how they are linked.
You draw it <em>before</em> any table exists. Open the <a href="erd.php" data-erd-open>diagram of Northwind</a> to see a complete one:
you have been querying those tables all along.</p>

<h3>Entities and attributes</h3>
<p>An <strong>entity</strong> is a kind of thing you keep data about: a customer, a product, an order. It becomes a <strong>table</strong>;
every row is one customer, one product, one order. An <strong>attribute</strong> is one fact about it: a name, a price.
It becomes a <strong>column</strong>.</p>
<erd>
Products: ProductID PK int, ProductName varchar, UnitPrice decimal, UnitsInStock int, Discontinued boolean
</erd>
<p>An attribute holds <strong>one value</strong>. A column <code>PhoneNumbers</code> with three numbers in it, or columns
<code>Phone1</code>, <code>Phone2</code>, <code>Phone3</code>, mean that an entity is missing from the design.
Do not store what you can calculate: an age follows from a birth date, an order total from its lines.</p>

<h3>Every attribute has a data type</h3>
<p>The data type says what kind of value the attribute holds. Choose it by what you <em>do</em> with the value:</p>
<table>
<tr><th>Type</th><th>Use it for</th><th>Examples</th></tr>
<tr><td><code>INT</code></td><td>Whole numbers, and keys that are just a number</td><td>quantity, number of seats, a year, <code>ProductID</code></td></tr>
<tr><td><code>DECIMAL</code></td><td>Numbers with decimals, always for money</td><td>price, weight, salary</td></tr>
<tr><td><code>VARCHAR</code></td><td>Short text</td><td>name, city, e-mail address, phone number, postal code</td></tr>
<tr><td><code>TEXT</code></td><td>Long text</td><td>description, review, notes</td></tr>
<tr><td><code>DATE</code></td><td>A day</td><td>birth date, order date</td></tr>
<tr><td><code>DATETIME</code></td><td>A day and a time</td><td>start of a film, moment of a payment</td></tr>
<tr><td><code>BOOLEAN</code></td><td>Yes or no</td><td>discontinued, paid, active</td></tr>
</table>
<p>A phone number or postal code is text, not a number: you never calculate with it and it can start with a zero.</p>

<h3>Primary key</h3>
<p>Every table needs a way to point at exactly one row: the <strong>primary key</strong> (<strong>PK</strong>). Its value is
<strong>unique</strong>, <strong>never empty</strong> and <strong>never changes</strong>. Names, phone numbers and e-mail addresses are
poor keys: they change, and two people can share a name. That is why most tables get a number that means nothing:
<code>ProductID</code>, <code>OrderID</code>.</p>
<p>Sometimes no single attribute is unique but a pair is. Then both together are the key, a <strong>composite key</strong>
(you will meet it in link tables below).</p>

<h3>Foreign key</h3>
<p>A <strong>foreign key</strong> (<strong>FK</strong>) is an attribute that holds the primary key of a row in another table.
<code>Products.CategoryID</code> does not store the category name; it stores the key of one row of <code>Categories</code>.</p>
<erd tables="Categories, Products" columns="keys"></erd>
<ul>
<li>The value must <strong>exist</strong> in the other table (the database checks this: <strong>referential integrity</strong>).</li>
<li>The value <strong>may repeat</strong>: many products have <code>CategoryID</code> 1.</li>
<li>It has the <strong>same data type</strong> as the key it points to.</li>
<li>It may be empty (<code>NULL</code>) when the link is optional.</li>
</ul>

<h3>Relationships: one or many?</h3>
<p>A <strong>relationship</strong> is a line between two entities; in the database it is a foreign key. Its <strong>cardinality</strong>
says how many rows of one side belong to one row of the other. Ask the question in both directions:</p>
<ul>
<li>One category has how many products? <strong>Many.</strong></li>
<li>One product has how many categories? <strong>One.</strong></li>
</ul>
<p>The line ends in a <strong>bar</strong> on the "one" side and a <strong>crow's foot</strong> on the "many" side.</p>
<table>
<tr><th>Relationship</th><th>Both questions</th><th>How it is stored</th></tr>
<tr><td><strong>One-to-many</strong> (1:N)</td><td>one / many</td><td>A foreign key in the table on the <strong>many</strong> side.</td></tr>
<tr><td><strong>Many-to-many</strong> (N:M)</td><td>many / many</td><td>Cannot be stored directly: a <strong>link table</strong> in between, with a foreign key to both sides.</td></tr>
<tr><td><strong>One-to-one</strong> (1:1)</td><td>one / one</td><td>A foreign key that is also unique. Rare: the two tables could often be one.</td></tr>
</table>

<h3>Designing in five steps</h3>
<ol>
<li><strong>Entities:</strong> the nouns you keep several facts about. A noun with one fact (a title, an e-mail address) is an attribute.</li>
<li><strong>Attributes, types and a key</strong> for every entity. Nothing unique and stable? Add a number: <code>MemberID</code>.</li>
<li><strong>Relationships:</strong> the verbs (a member <em>borrows</em> books). Ask "how many?" in both directions.</li>
<li><strong>Link tables:</strong> replace every many-to-many relationship by a table in between.</li>
<li><strong>Foreign keys:</strong> for every one-to-many relationship, on the many side.</li>
</ol>
<p>Then check that <strong>every fact is stored once</strong>. Repeated data goes wrong sooner or later: one copy gets changed, the other
does not. No lists in a cell, no repeating columns (<code>Author1</code>, <code>Author2</code>), and no attribute that really
describes another entity (a customer's city in <code>Orders</code>).</p>

<h3>Example 1: one-to-many</h3>
<p><em>A football league has teams. Every player plays for exactly one team.</em></p>
<erd>
Teams #green: TeamID PK int, Name varchar, City varchar
Players: PlayerID PK int, FirstName varchar, LastName varchar, ShirtNumber int, TeamID int > Teams
</erd>
<p>One team has many players, a player has one team: the foreign key <code>TeamID</code> is in <code>Players</code>, the many side.
The other way round is impossible: a team row cannot hold a list of players in one cell.</p>

<h3>Example 2: a chain of one-to-many</h3>
<p><em>A vet has owners, their animals, and the visits of each animal.</em></p>
<erd>
Owners #green: OwnerID PK int, Name varchar, Phone varchar
Animals: AnimalID PK int, Name varchar, Species varchar, BirthDate date, OwnerID int > Owners
Visits #orange: VisitID PK int, VisitDate date, Reason text, Price decimal, AnimalID int > Animals
</erd>
<p><code>Visits</code> has no <code>OwnerID</code>: the owner is found through the animal. Storing it twice would allow a visit
whose owner is not the owner of the animal.</p>

<h3>Example 3: many-to-many, first sketch</h3>
<p><em>Students follow courses. A student follows several courses and a course has many students.</em></p>
<erd>
Students: StudentID PK int, FirstName varchar, LastName varchar
Courses #orange: CourseID PK int, Name varchar, Credits int
Students <> Courses
</erd>
<p>Many on both sides. This is fine as a sketch, but neither table can hold the foreign key: a student row has room for one
<code>CourseID</code>, not for six.</p>

<h3>Example 4: the link table</h3>
<p>The same school, ready to become tables. The relationship itself becomes a table:</p>
<erd>
Students: StudentID PK int, FirstName varchar, LastName varchar
Enrolments #purple: StudentID PK int > Students, CourseID PK int > Courses, Grade int
Courses #orange: CourseID PK int, Name varchar, Credits int
</erd>
<p>One many-to-many relationship is now <strong>two one-to-many</strong> relationships. The key of <code>Enrolments</code> is the
<strong>pair</strong> of foreign keys: a student is enrolled in a course once. The grade belongs to neither the student nor the
course but to "this student in this course", so it lives in the link table.</p>

<h3>Example 5: a link table with its own key</h3>
<p><em>A library lends books to members. The same member can borrow the same book again later.</em></p>
<erd>
Members: MemberID PK int, FirstName varchar, LastName varchar, Email varchar
Loans #purple: LoanID PK int, LoanDate date, ReturnDate date, MemberID int > Members, BookID int > Books
Books #orange: BookID PK int, Title varchar, PublicationYear int
</erd>
<p>Here the pair member + book is <em>not</em> unique, so <code>Loans</code> gets its own key <code>LoanID</code> and two ordinary
foreign keys. <code>ReturnDate</code> stays empty while the book is out.</p>

<h3>Example 6: an entity linked to itself</h3>
<p><em>Every employee has at most one manager, who is also an employee.</em></p>
<erd>
Employees: EmployeeID PK int, FirstName varchar, LastName varchar, Title varchar, ManagerID int > Employees
</erd>
<p><code>ManagerID</code> holds the <code>EmployeeID</code> of another row of the same table. The line leaves the entity and comes back.
The top manager has an empty <code>ManagerID</code>. Northwind does the same with <code>Employees.ReportsTo</code>.</p>

<h3>Example 7: one-to-one</h3>
<p><em>Some employees get a company car. A car belongs to one employee, and an employee has at most one car.</em></p>
<erd>
Employees: EmployeeID PK int, FirstName varchar, LastName varchar
Cars #teal: CarID PK int, LicensePlate varchar, Brand varchar, EmployeeID int > Employees UNIQUE
</erd>
<p>A bar at both ends. The foreign key is <strong>unique</strong>: no two cars have the same <code>EmployeeID</code>. It sits in
<code>Cars</code> because not every employee has a car; in <code>Employees</code> it would be empty for most rows.</p>

<h3>Example 8: two relationships between the same entities</h3>
<p><em>A flight departs from one airport and arrives at another.</em></p>
<erd>
Airports #green: AirportID PK int, Code varchar, Name varchar, City varchar
Flights: FlightID PK int, FlightNumber varchar, DepartureTime datetime, DepartureAirportID int > Airports, ArrivalAirportID int > Airports
</erd>
<p>Two foreign keys to the same table, each with a name that says what it means. One column <code>AirportID</code> could not tell
departure from arrival.</p>

<h3>Example 9: a web shop</h3>
<p><em>Customers place orders. An order contains several products, each with a quantity. Products belong to a category.</em></p>
<erd>
Customers #green: CustomerID PK int, Name varchar, Email varchar, City varchar
Orders: OrderID PK int, OrderDate date, Paid boolean, CustomerID int > Customers
OrderLines #purple: OrderID PK int > Orders, ProductID PK int > Products, Quantity int, UnitPrice decimal
Products #orange: ProductID PK int, Name varchar, Price decimal, CategoryID int > Categories
+ Categories #teal: CategoryID PK int, Name varchar
</erd>
<p><code>OrderLines</code> is the link table between orders and products. It keeps its own <code>UnitPrice</code>: the price
<em>at the moment of the order</em>, which must not change when the product's price changes later. That is not repeated data,
it is a different fact. Northwind's <code>OrderDetails</code> works the same way.</p>

<h3>Example 10: a cinema</h3>
<p><em>Films are shown in rooms at a certain time. Visitors buy tickets for a showing.</em></p>
<erd>
Films #orange: FilmID PK int, Title varchar, Minutes int
Showings #purple: ShowingID PK int, StartTime datetime, FilmID int > Films, RoomID int > Rooms
+ Tickets: TicketID PK int, SeatNumber int, Price decimal, ShowingID int > Showings
Rooms #green: RoomID PK int, Name varchar, Seats int
</erd>
<p><code>Showings</code> links films and rooms and has its own key: the same film runs in the same room at different times.
A ticket belongs to a showing, not directly to a film or a room.</p>

<h3>Example 11: a blog</h3>
<p><em>Users write posts. Other users comment on posts. A post can have several tags, and a tag is used on many posts.</em></p>
<erd>
Users #green: UserID PK int, UserName varchar, Email varchar
Posts: PostID PK int, Title varchar, Body text, PublishedAt datetime, UserID int > Users
+ Comments #orange: CommentID PK int, Body text, WrittenAt datetime, PostID int > Posts, UserID int > Users
PostTags #purple: PostID PK int > Posts, TagID PK int > Tags
Tags #teal: TagID PK int, Name varchar
</erd>
<p><code>Comments</code> has two foreign keys with different meanings: the post it is about and the user who wrote it.
<code>PostTags</code> is a pure link table: nothing but the two keys.</p>

<h3>Example 12: repairing a bad design</h3>
<p>Everything in one table:</p>
<erd>
Orders #red: OrderID PK int, OrderDate date, CustomerName varchar, CustomerCity varchar, Product1 varchar, Product2 varchar, Product3 varchar
</erd>
<p>An order with four products does not fit, and the customer's name and city are repeated on every order. Split it: each entity its
own table, a link table for the many-to-many relationship.</p>
<erd>
Customers #green: CustomerID PK int, Name varchar, City varchar
Orders: OrderID PK int, OrderDate date, CustomerID int > Customers
OrderLines #purple: OrderID PK int > Orders, ProductID PK int > Products, Quantity int
Products #orange: ProductID PK int, Name varchar
</erd>

<h3>Draw it yourself</h3>
<p>In the exercises below you draw the diagram on a canvas. It is only a drawing: no database is created.</p>
<ul>
<li>Drag the <strong>Entity</strong> block onto the canvas and type the name of the table.</li>
<li>Add attributes with <strong>+ attribute</strong>, choose a <strong>data type</strong> for each, and click <strong>PK</strong> in front of the key.</li>
<li>For a relationship, drag one of the round <strong>connectors</strong> on the sides of the "one" entity onto the "many" entity.
The foreign key is added for you; rename it if the task asks for another name.</li>
<li>Click an <strong>end of the line</strong> to switch it between one (bar) and many (crow's foot).</li>
<li>The coloured dot in the bar of an entity changes its colour. Colours are yours to use: they are not checked.</li>
</ul>
<p><strong>Check</strong> compares your entities, attributes, data types, keys and relationships with a model design.</p>
HTML,
    'exercises' => [
        [
            'type'     => 'erd',
            'title'    => 'One entity',
            'task'     => '<p>A school keeps a list of its students: first name, last name and e-mail address. Two students can have the same name.</p>
<p>Draw the entity <code>Students</code> with the attributes <code>FirstName</code>, <code>LastName</code> and <code>Email</code>, and add a primary key <code>StudentID</code> (a number). Give every attribute a data type.</p>',
            'hint'     => 'One entity with four attributes. The key is an <code>INT</code>, the others are short text: <code>VARCHAR</code>. Click <strong>PK</strong> in front of <code>StudentID</code>.',
            'solution' => "Students: StudentID int PK, FirstName varchar, LastName varchar, Email varchar",
        ],
        [
            'type'     => 'erd',
            'title'    => 'One-to-many',
            'task'     => '<p>A football league has teams. A team has a name and a city. A player has a first name, a last name and a shirt number, and plays for exactly one team.</p>
<p>Draw <code>Teams</code> (<code>TeamID</code>, <code>Name</code>, <code>City</code>) and <code>Players</code> (<code>PlayerID</code>, <code>FirstName</code>, <code>LastName</code>, <code>ShirtNumber</code>) and the relationship between them. The foreign key is called <code>TeamID</code>.</p>',
            'hint'     => 'One team has many players: drag a connector of <code>Teams</code> onto <code>Players</code>. The foreign key <code>TeamID</code> appears in <code>Players</code>, the many side.',
            'solution' => "Teams: TeamID int PK, Name varchar, City varchar\nPlayers: PlayerID int PK, FirstName varchar, LastName varchar, ShirtNumber int, TeamID int > Teams",
        ],
        [
            'type'     => 'erd',
            'title'    => 'Which side is many?',
            'task'     => '<p>A company has departments (a name and the floor number they are on). Every employee (first name, last name, the day they were hired) works in one department; a department has many employees.</p>
<p>Draw <code>Departments</code> (<code>DepartmentID</code>, <code>Name</code>, <code>Floor</code>) and <code>Employees</code> (<code>EmployeeID</code>, <code>FirstName</code>, <code>LastName</code>, <code>HireDate</code>) and link them. The foreign key is called <code>DepartmentID</code>.</p>',
            'hint'     => 'Ask the question in both directions. Start the line at the "one" entity. Wrong way round? Click the ends of the line until the crow\'s foot is at the many side.',
            'solution' => "Departments: DepartmentID int PK, Name varchar, Floor int\nEmployees: EmployeeID int PK, FirstName varchar, LastName varchar, HireDate date, DepartmentID int > Departments",
        ],
        [
            'type'     => 'erd',
            'title'    => 'A chain',
            'task'     => '<p>A vet treats animals. An owner (name, phone number) can have several animals. An animal (name, species) has one owner. Every visit (the day of the visit, the reason as a longer text) is for one animal; an animal can have many visits.</p>
<p>Draw <code>Owners</code> (<code>OwnerID</code>, <code>Name</code>, <code>Phone</code>), <code>Animals</code> (<code>AnimalID</code>, <code>Name</code>, <code>Species</code>) and <code>Visits</code> (<code>VisitID</code>, <code>VisitDate</code>, <code>Reason</code>) with their two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => 'Two one-to-many relationships: Owners → Animals → Visits. A visit does not need an <code>OwnerID</code>: you reach the owner through the animal. A phone number is text.',
            'solution' => "Owners: OwnerID int PK, Name varchar, Phone varchar\nAnimals: AnimalID int PK, Name varchar, Species varchar, OwnerID int > Owners\nVisits: VisitID int PK, VisitDate date, Reason text, AnimalID int > Animals",
        ],
        [
            'type'     => 'erd',
            'title'    => 'Many-to-many',
            'task'     => '<p>Students follow courses. A student (first name, last name) follows several courses, and a course (name, number of credits) has many students. For every student in a course we keep the grade, a whole number.</p>
<p>Draw <code>Students</code> (<code>StudentID</code>, <code>FirstName</code>, <code>LastName</code>), <code>Courses</code> (<code>CourseID</code>, <code>Name</code>, <code>Credits</code>) and the link table <code>Enrolments</code> with the <code>Grade</code>. A student is enrolled in a course only once, so the link table needs no number of its own: use a composite key.</p>',
            'hint'     => 'Drag a connector from <code>Students</code> to <code>Enrolments</code> and one from <code>Courses</code> to <code>Enrolments</code>. Then click <strong>PK</strong> on both foreign keys: together they are the key.',
            'solution' => "Students: StudentID int PK, FirstName varchar, LastName varchar\nEnrolments: StudentID int PK > Students, CourseID int PK > Courses, Grade int\nCourses: CourseID int PK, Name varchar, Credits int",
        ],
        [
            'type'     => 'erd',
            'title'    => 'An entity linked to itself',
            'task'     => '<p>A company keeps its employees: first name, last name and job title. Every employee has at most one manager, who is also an employee.</p>
<p>Draw the single entity <code>Employees</code> (<code>EmployeeID</code>, <code>FirstName</code>, <code>LastName</code>, <code>Title</code>) with a relationship to itself. Call the foreign key <code>ManagerID</code>.</p>',
            'hint'     => 'Click a connector of <code>Employees</code>, then click <code>Employees</code> itself. Rename the new attribute to <code>ManagerID</code>.',
            'solution' => "Employees: EmployeeID int PK, FirstName varchar, LastName varchar, Title varchar, ManagerID int > Employees",
        ],
        [
            'type'     => 'erd',
            'title'    => 'One-to-one',
            'task'     => '<p>Some employees (first name, last name) get a company car. A car has a license plate and a brand. A car belongs to exactly one employee, and an employee has at most one car.</p>
<p>Draw <code>Employees</code> (<code>EmployeeID</code>, <code>FirstName</code>, <code>LastName</code>) and <code>Cars</code> (<code>CarID</code>, <code>LicensePlate</code>, <code>Brand</code>). Put the foreign key <code>EmployeeID</code> in <code>Cars</code> and make the relationship one-to-one.</p>',
            'hint'     => 'Drag from <code>Employees</code> to <code>Cars</code>, then click the crow\'s foot at <code>Cars</code>: it becomes a bar. One at both ends.',
            'solution' => "Employees: EmployeeID int PK, FirstName varchar, LastName varchar\nCars: CarID int PK, LicensePlate varchar, Brand varchar, EmployeeID int > Employees UNIQUE",
        ],
        [
            'type'     => 'erd',
            'title'    => 'Repair a design',
            'task'     => '<p>The entity on the canvas stores everything about an order in one table. An order with four products does not fit, and the name and city of a customer are repeated on every order.</p>
<p>Split it into <code>Customers</code> (<code>CustomerID</code>, <code>Name</code>, <code>City</code>), <code>Orders</code> (<code>OrderID</code>, <code>OrderDate</code>, and a foreign key to the customer), <code>Products</code> (<code>ProductID</code>, <code>Name</code>) and the link table <code>OrderLines</code> with a composite key and a <code>Quantity</code>.</p>',
            'hint'     => 'Customers to Orders is one-to-many. Orders to Products is many-to-many: <code>OrderLines</code> gets <code>OrderID</code> and <code>ProductID</code>, both PK and both a foreign key, plus <code>Quantity</code>. Remove the customer and product attributes from <code>Orders</code>.',
            'starter'  => "Orders: OrderID int PK, OrderDate date, CustomerName varchar, CustomerCity varchar, Product1 varchar, Product2 varchar, Product3 varchar",
            'solution' => "Customers: CustomerID int PK, Name varchar, City varchar\nOrders: OrderID int PK, OrderDate date, CustomerID int > Customers\nOrderLines: OrderID int PK > Orders, ProductID int PK > Products, Quantity int\nProducts: ProductID int PK, Name varchar",
        ],
        [
            'type'     => 'erd',
            'title'    => 'A link table with its own key',
            'task'     => '<p>A library lends books to members. A member has a first name, a last name and an e-mail address. A book has a title and a publication year (a number). For every loan we keep the day it was lent and the day it came back. The same member can borrow the same book again later.</p>
<p>Draw <code>Members</code> (<code>MemberID</code>, <code>FirstName</code>, <code>LastName</code>, <code>Email</code>), <code>Books</code> (<code>BookID</code>, <code>Title</code>, <code>PublicationYear</code>) and <code>Loans</code> (<code>LoanDate</code>, <code>ReturnDate</code>). Decide which primary key <code>Loans</code> needs; if it needs a new attribute, call it <code>LoanID</code>.</p>',
            'hint'     => 'The pair member + book is not unique here, so <code>Loans</code> gets its own key <code>LoanID</code>, plus two ordinary foreign keys.',
            'solution' => "Members: MemberID int PK, FirstName varchar, LastName varchar, Email varchar\nLoans: LoanID int PK, LoanDate date, ReturnDate date, MemberID int > Members, BookID int > Books\nBooks: BookID int PK, Title varchar, PublicationYear int",
        ],
        [
            'type'     => 'erd',
            'title'    => 'Two relationships, same entities',
            'task'     => '<p>An airline keeps airports (a three-letter code, a name, a city) and flights. A flight has a flight number such as <code>SN2093</code>, a departure moment (day and time), the airport it departs from and the airport it arrives at.</p>
<p>Draw <code>Airports</code> (<code>AirportID</code>, <code>Code</code>, <code>Name</code>, <code>City</code>) and <code>Flights</code> (<code>FlightID</code>, <code>FlightNumber</code>, <code>DepartureTime</code>) with two relationships. Call the foreign keys <code>DepartureAirportID</code> and <code>ArrivalAirportID</code>.</p>',
            'hint'     => 'Drag from <code>Airports</code> to <code>Flights</code> twice and rename the two new attributes. A flight number contains letters, so it is text.',
            'solution' => "Airports: AirportID int PK, Code varchar, Name varchar, City varchar\nFlights: FlightID int PK, FlightNumber varchar, DepartureTime datetime, DepartureAirportID int > Airports, ArrivalAirportID int > Airports",
        ],
        [
            'type'     => 'erd',
            'title'    => 'Find the relationships yourself',
            'task'     => '<p>A cinema has rooms; a room has a name and a number of seats. Films have a title and a length in minutes. A film is shown many times, in different rooms and at different moments; every showing has a start (day and time). Visitors buy tickets for a showing; a ticket has a seat number and a price.</p>
<p>Draw four entities: <code>Rooms</code> (<code>Name</code>, <code>Seats</code>), <code>Films</code> (<code>Title</code>, <code>Minutes</code>), <code>Showings</code> (<code>StartTime</code>) and <code>Tickets</code> (<code>SeatNumber</code>, <code>Price</code>). Every entity gets its own key (<code>RoomID</code>, <code>FilmID</code>, <code>ShowingID</code>, <code>TicketID</code>); foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Showings</code> links films and rooms and has its own key. A ticket belongs to one showing, not directly to a film or a room. A price is money: <code>DECIMAL</code>.',
            'solution' => "Films: FilmID int PK, Title varchar, Minutes int\nShowings: ShowingID int PK, StartTime datetime, FilmID int > Films, RoomID int > Rooms\n+ Tickets: TicketID int PK, SeatNumber int, Price decimal, ShowingID int > Showings\nRooms: RoomID int PK, Name varchar, Seats int",
        ],
        [
            'type'     => 'erd',
            'title'    => 'A complete design',
            'task'     => '<p>A music service has artists (name, country). An artist releases albums; an album has a title and a release year (a number) and belongs to one artist. An album contains tracks; a track has a title and a length in seconds and is on one album. Users (user name, e-mail address) make playlists; a playlist has a name, belongs to one user and can be public or not. A playlist contains many tracks and a track can be on many playlists.</p>
<p>Draw six entities: <code>Artists</code> (<code>Name</code>, <code>Country</code>), <code>Albums</code> (<code>Title</code>, <code>ReleaseYear</code>), <code>Tracks</code> (<code>Title</code>, <code>Seconds</code>), <code>Users</code> (<code>UserName</code>, <code>Email</code>), <code>Playlists</code> (<code>Name</code>, <code>IsPublic</code>) and the link table <code>PlaylistTracks</code> (composite key, no other attributes). Keys are <code>ArtistID</code>, <code>AlbumID</code>, <code>TrackID</code>, <code>UserID</code>, <code>PlaylistID</code>; foreign keys are named after the key they point to.</p>',
            'hint'     => 'Three one-to-many relationships (Artists → Albums → Tracks, Users → Playlists) and one many-to-many (Playlists and Tracks) through <code>PlaylistTracks</code>. Public or not is yes/no: <code>BOOLEAN</code>.',
            'solution' => "Artists: ArtistID int PK, Name varchar, Country varchar\nAlbums: AlbumID int PK, Title varchar, ReleaseYear int, ArtistID int > Artists\nTracks: TrackID int PK, Title varchar, Seconds int, AlbumID int > Albums\nPlaylistTracks: PlaylistID int PK > Playlists, TrackID int PK > Tracks\nPlaylists: PlaylistID int PK, Name varchar, IsPublic boolean, UserID int > Users\n+ Users: UserID int PK, UserName varchar, Email varchar",
        ],
    ],
];
