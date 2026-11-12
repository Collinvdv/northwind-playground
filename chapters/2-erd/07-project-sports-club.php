<?php
return [
    'title'   => 'Project: Sports club',
    'summary' => 'Teams and members, coaches, fields and trainings, attendance and membership fees. Four steps.',
    'intro'   => <<<'HTML'
<h3>The case</h3>
<p>A sports club has <strong>teams</strong>; a team has a name and an age group such as <code>U12</code>.
<strong>Members</strong> are registered with first name, last name and birth date. A member can play in several teams
(their own age group and the one above), and a team has many members. In each team a member has a shirt number.</p>
<p>Every team has one <strong>coach</strong>, who is a member of the club. A member can coach several teams.</p>
<p>The club has several <strong>fields</strong>, each with a name; some have lights. A <strong>training</strong> is for one team,
on one field, starts at a certain moment and lasts a number of minutes.</p>
<p>For every training the coach notes which members were there (<strong>attendance</strong>). Members also pay
<strong>membership fees</strong>: an amount, paid on a certain day; a member pays several times over the years.</p>

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
            'title'    => 'Step 1: Teams and members',
            'task'     => '<p>A team has a name and an age group such as <code>U12</code>. A member has a first name, a last name and a birth date. A member can play in several teams and a team has many members; in each team the member has a shirt number.</p><p>Draw <code>Teams</code> (<code>TeamID</code>, <code>Name</code>, <code>AgeGroup</code>), <code>Members</code> (<code>MemberID</code>, <code>FirstName</code>, <code>LastName</code>, <code>BirthDate</code>) and the link table <code>TeamMembers</code> with a composite key and a <code>ShirtNumber</code>.</p>',
            'hint'     => 'Many-to-many from the first step: <code>TeamMembers</code> gets <code>TeamID</code> and <code>MemberID</code>, both PK and both foreign key. <code>U12</code> is text.',
            'solution' => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 2: The coach',
            'task'     => '<p>Every team has one coach, who is a member of the club. A member can coach several teams.</p><p>Add this second relationship between <code>Members</code> and <code>Teams</code>: a foreign key <code>CoachID</code>.</p>',
            'hint'     => 'One member coaches many teams, a team has one coach: the foreign key goes in <code>Teams</code>. Drag from <code>Members</code> to <code>Teams</code> and rename the new attribute.',
            'starter'  => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date',
            'solution' => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar, CoachID int > Members
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 3: Fields and trainings',
            'task'     => '<p>The club has fields, each with a name; a field has lights or not. A training is for one team, on one field, starts at a day and time and lasts a number of minutes.</p><p>Add <code>Fields</code> (<code>FieldID</code>, <code>Name</code>, <code>HasLights</code>) and <code>Trainings</code> (<code>TrainingID</code>, <code>StartsAt</code>, <code>Minutes</code>) with two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Trainings</code> gets <code>TeamID</code> and <code>FieldID</code>. Lights or not is a <code>BOOLEAN</code>.',
            'starter'  => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar, CoachID int > Members
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date',
            'solution' => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar, CoachID int > Members
+ Trainings: TrainingID int PK, StartsAt datetime, Minutes int, TeamID int > Teams, FieldID int > Fields
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
+ Fields: FieldID int PK, Name varchar, HasLights boolean
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 4: Attendance and fees',
            'task'     => '<p>For every training the coach notes for each member whether they were present. Members pay membership fees: an amount, paid on a certain day; a member pays several times.</p><p>Add the link table <code>Attendance</code> between <code>Trainings</code> and <code>Members</code> (composite key, plus <code>Present</code>), and <code>Payments</code> (<code>PaymentID</code>, <code>Amount</code>, <code>PaidOn</code>) with a foreign key <code>MemberID</code>.</p>',
            'hint'     => '<code>Attendance</code> gets <code>TrainingID</code> and <code>MemberID</code>, both PK, and a yes/no attribute. One member has many payments.',
            'starter'  => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar, CoachID int > Members
+ Trainings: TrainingID int PK, StartsAt datetime, Minutes int, TeamID int > Teams, FieldID int > Fields
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
+ Fields: FieldID int PK, Name varchar, HasLights boolean
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date',
            'solution' => 'Teams: TeamID int PK, Name varchar, AgeGroup varchar, CoachID int > Members
+ Trainings: TrainingID int PK, StartsAt datetime, Minutes int, TeamID int > Teams, FieldID int > Fields
TeamMembers: TeamID int PK > Teams, MemberID int PK > Members, ShirtNumber int
+ Attendance: TrainingID int PK > Trainings, MemberID int PK > Members, Present boolean
Members: MemberID int PK, FirstName varchar, LastName varchar, BirthDate date
+ Fields: FieldID int PK, Name varchar, HasLights boolean
Payments: PaymentID int PK, Amount decimal, PaidOn date, MemberID int > Members',
        ],
    ],
];
