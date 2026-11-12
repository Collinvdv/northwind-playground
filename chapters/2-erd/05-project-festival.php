<?php
return [
    'title'   => 'Project: Music festival',
    'summary' => 'Stages and performances, artists, visitors and tickets, favourite artists. Four steps.',
    'intro'   => <<<'HTML'
<h3>The case</h3>
<p>A music festival has several <strong>stages</strong>, each with a name and a capacity (number of people).
On a stage there are <strong>performances</strong>: a performance starts and ends at a certain moment and takes place on one stage.</p>
<p>A performance is given by one <strong>artist</strong>. An artist has a name, a country and a genre, and can perform several
times during the festival.</p>
<p><strong>Visitors</strong> (first name, last name, e-mail address) buy <strong>tickets</strong>. There are several
<strong>ticket types</strong> (day ticket, weekend, VIP), each with a name and a price. A ticket is of one type, belongs to one
visitor, and we keep the moment it was bought. A visitor can buy several tickets.</p>
<p>In the festival app a visitor can mark artists as <strong>favourite</strong>. A visitor has many favourites and an artist is
the favourite of many visitors.</p>

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
            'title'    => 'Step 1: Stages and performances',
            'task'     => '<p>A stage has a name and a capacity. A performance has a start and an end (day and time) and takes place on one stage.</p><p>Draw <code>Stages</code> (<code>StageID</code>, <code>Name</code>, <code>Capacity</code>) and <code>Performances</code> (<code>PerformanceID</code>, <code>StartsAt</code>, <code>EndsAt</code>) and their relationship. The foreign key is called <code>StageID</code>.</p>',
            'hint'     => 'One stage has many performances. A capacity is a whole number, a start is a <code>DATETIME</code>.',
            'solution' => 'Stages: StageID int PK, Name varchar, Capacity int
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 2: Artists',
            'task'     => '<p>A performance is given by one artist; an artist can perform several times. An artist has a name, a country and a genre.</p><p>Add <code>Artists</code> (<code>ArtistID</code>, <code>Name</code>, <code>Country</code>, <code>Genre</code>) and link it. The foreign key is called <code>ArtistID</code>.</p>',
            'hint'     => 'Which entity is on the many side: the artist or the performance? That one gets the foreign key.',
            'starter'  => 'Stages: StageID int PK, Name varchar, Capacity int
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages',
            'solution' => 'Stages: StageID int PK, Name varchar, Capacity int
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages, ArtistID int > Artists
Artists: ArtistID int PK, Name varchar, Country varchar, Genre varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 3: Visitors and tickets',
            'task'     => '<p>Visitors (first name, last name, e-mail address) buy tickets. A ticket type has a name and a price. A ticket is of one type, belongs to one visitor, and we keep the moment it was bought.</p><p>Add <code>Visitors</code> (<code>VisitorID</code>, <code>FirstName</code>, <code>LastName</code>, <code>Email</code>), <code>TicketTypes</code> (<code>TicketTypeID</code>, <code>Name</code>, <code>Price</code>) and <code>Tickets</code> (<code>TicketID</code>, <code>BoughtAt</code>) with two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Tickets</code> is on the many side twice: it gets <code>VisitorID</code> and <code>TicketTypeID</code>. These three entities are not linked to the stages and artists.',
            'starter'  => 'Stages: StageID int PK, Name varchar, Capacity int
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages, ArtistID int > Artists
Artists: ArtistID int PK, Name varchar, Country varchar, Genre varchar',
            'solution' => 'Stages: StageID int PK, Name varchar, Capacity int
+ Visitors: VisitorID int PK, FirstName varchar, LastName varchar, Email varchar
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages, ArtistID int > Artists
+ Tickets: TicketID int PK, BoughtAt datetime, VisitorID int > Visitors, TicketTypeID int > TicketTypes
Artists: ArtistID int PK, Name varchar, Country varchar, Genre varchar
+ TicketTypes: TicketTypeID int PK, Name varchar, Price decimal',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 4: Favourite artists',
            'task'     => '<p>In the app a visitor can mark artists as favourite. A visitor has many favourites and an artist is the favourite of many visitors.</p><p>Add the link table <code>Favourites</code> between <code>Visitors</code> and <code>Artists</code>: a composite key and no other attributes.</p>',
            'hint'     => 'A pure link table: <code>VisitorID</code> and <code>ArtistID</code>, both PK and both foreign key. This is what finally connects the visitors to the artists.',
            'starter'  => 'Stages: StageID int PK, Name varchar, Capacity int
+ Visitors: VisitorID int PK, FirstName varchar, LastName varchar, Email varchar
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages, ArtistID int > Artists
+ Tickets: TicketID int PK, BoughtAt datetime, VisitorID int > Visitors, TicketTypeID int > TicketTypes
Artists: ArtistID int PK, Name varchar, Country varchar, Genre varchar
+ TicketTypes: TicketTypeID int PK, Name varchar, Price decimal',
            'solution' => 'Stages: StageID int PK, Name varchar, Capacity int
+ Visitors: VisitorID int PK, FirstName varchar, LastName varchar, Email varchar
Performances: PerformanceID int PK, StartsAt datetime, EndsAt datetime, StageID int > Stages, ArtistID int > Artists
+ Tickets: TicketID int PK, BoughtAt datetime, VisitorID int > Visitors, TicketTypeID int > TicketTypes
Artists: ArtistID int PK, Name varchar, Country varchar, Genre varchar
+ TicketTypes: TicketTypeID int PK, Name varchar, Price decimal
Favourites: VisitorID int PK > Visitors, ArtistID int PK > Artists',
        ],
    ],
];
