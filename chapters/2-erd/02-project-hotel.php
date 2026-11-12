<?php
return [
    'title'   => 'Project: Hotel',
    'summary' => 'Rooms and room types, guests and bookings, extra services, payments. Four steps.',
    'intro'   => <<<'HTML'
<h3>The case</h3>
<p>A hotel has <strong>room types</strong> (single, double, suite, ...). A type has a name, a price per night and a maximum number of guests.
Every <strong>room</strong> has a room number such as <code>12B</code>, is on a floor and is of exactly one type.</p>
<p><strong>Guests</strong> are registered with their first name, last name, e-mail address and phone number. A guest makes
<strong>bookings</strong>: one booking is for one room, from a check-in day to a check-out day. A guest can come back many times,
and a room is booked again and again.</p>
<p>During a stay a guest can order <strong>services</strong> (breakfast, parking, spa): a service has a name and a price.
The same service can be on a booking several times, and a booking can have several services.</p>
<p>A booking is paid in one or more <strong>payments</strong>: an amount, the moment of payment and the method (card, cash, ...).</p>

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
            'title'    => 'Step 1: Rooms and room types',
            'task'     => '<p>Start with the rooms. A room type has a name, a price per night and a maximum number of guests. A room has a room number such as <code>12B</code>, a floor number, and is of one type.</p><p>Draw <code>RoomTypes</code> (<code>RoomTypeID</code>, <code>Name</code>, <code>PricePerNight</code>, <code>MaxGuests</code>) and <code>Rooms</code> (<code>RoomID</code>, <code>RoomNumber</code>, <code>Floor</code>) and their relationship. The foreign key is called <code>RoomTypeID</code>.</p>',
            'hint'     => 'One type has many rooms: drag from <code>RoomTypes</code> to <code>Rooms</code>. A room number with a letter in it is text; a price is <code>DECIMAL</code>.',
            'solution' => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 2: Guests and bookings',
            'task'     => '<p>Add the guests (first name, last name, e-mail address, phone number) and their bookings. A booking is made by one guest, for one room, from a check-in day to a check-out day.</p><p>Add <code>Guests</code> (<code>GuestID</code>, <code>FirstName</code>, <code>LastName</code>, <code>Email</code>, <code>Phone</code>) and <code>Bookings</code> (<code>BookingID</code>, <code>CheckIn</code>, <code>CheckOut</code>) with two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Bookings</code> is on the many side twice: one guest has many bookings, one room has many bookings. It gets <code>GuestID</code> and <code>RoomID</code>.',
            'starter'  => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes',
            'solution' => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
+ Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes
Bookings: BookingID int PK, CheckIn date, CheckOut date, GuestID int > Guests, RoomID int > Rooms
Guests: GuestID int PK, FirstName varchar, LastName varchar, Email varchar, Phone varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 3: Extra services',
            'task'     => '<p>A guest can order services during a stay. A service has a name and a price. A booking can have several services and a service is ordered on many bookings; for every service on a booking we keep how many times it was ordered.</p><p>Add <code>Services</code> (<code>ServiceID</code>, <code>Name</code>, <code>Price</code>) and the link table <code>BookingServices</code> with a composite key and a <code>Quantity</code>.</p>',
            'hint'     => 'Many-to-many: drag from <code>Bookings</code> to <code>BookingServices</code> and from <code>Services</code> to <code>BookingServices</code>, then click PK on both foreign keys.',
            'starter'  => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
+ Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes
Bookings: BookingID int PK, CheckIn date, CheckOut date, GuestID int > Guests, RoomID int > Rooms
Guests: GuestID int PK, FirstName varchar, LastName varchar, Email varchar, Phone varchar',
            'solution' => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
+ Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes
Bookings: BookingID int PK, CheckIn date, CheckOut date, GuestID int > Guests, RoomID int > Rooms
+ BookingServices: BookingID int PK > Bookings, ServiceID int PK > Services, Quantity int
Guests: GuestID int PK, FirstName varchar, LastName varchar, Email varchar, Phone varchar
+ Services: ServiceID int PK, Name varchar, Price decimal',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 4: Payments',
            'task'     => '<p>A booking is paid in one or more payments. Of a payment we keep the amount, the moment it was paid (day and time) and the method, such as <code>card</code> or <code>cash</code>.</p><p>Add <code>Payments</code> (<code>PaymentID</code>, <code>Amount</code>, <code>PaidAt</code>, <code>Method</code>) and link it. The foreign key is called <code>BookingID</code>.</p>',
            'hint'     => 'One booking has many payments, so the foreign key goes in <code>Payments</code>. An amount is money; a moment is <code>DATETIME</code>.',
            'starter'  => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
+ Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes
Bookings: BookingID int PK, CheckIn date, CheckOut date, GuestID int > Guests, RoomID int > Rooms
+ BookingServices: BookingID int PK > Bookings, ServiceID int PK > Services, Quantity int
Guests: GuestID int PK, FirstName varchar, LastName varchar, Email varchar, Phone varchar
+ Services: ServiceID int PK, Name varchar, Price decimal',
            'solution' => 'RoomTypes: RoomTypeID int PK, Name varchar, PricePerNight decimal, MaxGuests int
+ Rooms: RoomID int PK, RoomNumber varchar, Floor int, RoomTypeID int > RoomTypes
Bookings: BookingID int PK, CheckIn date, CheckOut date, GuestID int > Guests, RoomID int > Rooms
+ BookingServices: BookingID int PK > Bookings, ServiceID int PK > Services, Quantity int
Guests: GuestID int PK, FirstName varchar, LastName varchar, Email varchar, Phone varchar
+ Services: ServiceID int PK, Name varchar, Price decimal
Payments: PaymentID int PK, Amount decimal, PaidAt datetime, Method varchar, BookingID int > Bookings',
        ],
    ],
];
