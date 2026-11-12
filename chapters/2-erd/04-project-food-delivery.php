<?php
return [
    'title'   => 'Project: Food delivery',
    'summary' => 'Restaurants and dishes, customers and orders, order items, couriers and reviews. Four steps.',
    'intro'   => <<<'HTML'
<h3>The case</h3>
<p>A delivery app works with <strong>restaurants</strong>: a name, a city, and whether the restaurant is open right now.
A restaurant offers <strong>dishes</strong>; a dish has a name, a price and a mark that says whether it is vegetarian.
A dish belongs to one restaurant.</p>
<p><strong>Customers</strong> have a name, an e-mail address and a delivery address. A customer places <strong>orders</strong>;
an order is placed at one restaurant, at a certain moment.</p>
<p>An order contains one or more dishes, each with a quantity. The same dish is of course ordered by many customers.</p>
<p>A <strong>courier</strong> (name, phone number) delivers the order; a courier delivers many orders. After delivery the customer
can write one <strong>review</strong> for the order: a number of stars and a comment.</p>

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
            'title'    => 'Step 1: Restaurants and dishes',
            'task'     => '<p>A restaurant has a name, a city, and is open or not. A dish has a name, a price, is vegetarian or not, and belongs to one restaurant.</p><p>Draw <code>Restaurants</code> (<code>RestaurantID</code>, <code>Name</code>, <code>City</code>, <code>IsOpen</code>) and <code>Dishes</code> (<code>DishID</code>, <code>Name</code>, <code>Price</code>, <code>IsVegetarian</code>) and their relationship. The foreign key is called <code>RestaurantID</code>.</p>',
            'hint'     => 'Open or not, vegetarian or not: yes/no values are <code>BOOLEAN</code>. One restaurant has many dishes.',
            'solution' => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 2: Customers and orders',
            'task'     => '<p>Add the customers (name, e-mail address, delivery address) and their orders. An order is placed by one customer at one restaurant, at a certain moment (day and time).</p><p>Add <code>Customers</code> (<code>CustomerID</code>, <code>Name</code>, <code>Email</code>, <code>Address</code>) and <code>Orders</code> (<code>OrderID</code>, <code>OrderedAt</code>) with two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Orders</code> gets <code>CustomerID</code> and <code>RestaurantID</code>: one customer has many orders, one restaurant receives many orders.',
            'starter'  => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants',
            'solution' => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
+ Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants
Orders: OrderID int PK, OrderedAt datetime, CustomerID int > Customers, RestaurantID int > Restaurants
Customers: CustomerID int PK, Name varchar, Email varchar, Address varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 3: What is in an order?',
            'task'     => '<p>An order contains one or more dishes, each with a quantity. A dish is on many orders. A dish appears on an order only once (the quantity says how many).</p><p>Add the link table <code>OrderItems</code> between <code>Orders</code> and <code>Dishes</code>, with a composite key and a <code>Quantity</code>.</p>',
            'hint'     => 'Orders and dishes are many-to-many. <code>OrderItems</code> gets <code>OrderID</code> and <code>DishID</code>, both PK and both foreign key.',
            'starter'  => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
+ Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants
Orders: OrderID int PK, OrderedAt datetime, CustomerID int > Customers, RestaurantID int > Restaurants
Customers: CustomerID int PK, Name varchar, Email varchar, Address varchar',
            'solution' => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
+ Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants
Orders: OrderID int PK, OrderedAt datetime, CustomerID int > Customers, RestaurantID int > Restaurants
+ OrderItems: OrderID int PK > Orders, DishID int PK > Dishes, Quantity int
Customers: CustomerID int PK, Name varchar, Email varchar, Address varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 4: Couriers and reviews',
            'task'     => '<p>A courier (name, phone number) delivers an order; one courier delivers many orders. After delivery the customer can write one review for the order: a number of stars (1 to 5) and a comment (a longer text). An order has at most one review.</p><p>Add <code>Couriers</code> (<code>CourierID</code>, <code>Name</code>, <code>Phone</code>) with a foreign key <code>CourierID</code> in <code>Orders</code>, and <code>Reviews</code> (<code>ReviewID</code>, <code>Stars</code>, <code>Comment</code>) with a foreign key <code>OrderID</code>, one-to-one.</p>',
            'hint'     => 'Couriers to Orders is one-to-many. Orders to Reviews is one-to-one: drag from <code>Orders</code> to <code>Reviews</code> and click the crow\'s foot at <code>Reviews</code>.',
            'starter'  => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
+ Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants
Orders: OrderID int PK, OrderedAt datetime, CustomerID int > Customers, RestaurantID int > Restaurants
+ OrderItems: OrderID int PK > Orders, DishID int PK > Dishes, Quantity int
Customers: CustomerID int PK, Name varchar, Email varchar, Address varchar',
            'solution' => 'Restaurants: RestaurantID int PK, Name varchar, City varchar, IsOpen boolean
+ Dishes: DishID int PK, Name varchar, Price decimal, IsVegetarian boolean, RestaurantID int > Restaurants
Orders: OrderID int PK, OrderedAt datetime, CustomerID int > Customers, RestaurantID int > Restaurants, CourierID int > Couriers
+ OrderItems: OrderID int PK > Orders, DishID int PK > Dishes, Quantity int
Customers: CustomerID int PK, Name varchar, Email varchar, Address varchar
+ Couriers: CourierID int PK, Name varchar, Phone varchar
Reviews: ReviewID int PK, Stars int, Comment text, OrderID int > Orders UNIQUE',
        ],
    ],
];
