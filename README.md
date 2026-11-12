# Northwind SQL course

A small learning platform for MySQL on the Northwind database. Students open a
URL and get three chapters, each a series of lessons: **SELECT** (SELECT, WHERE,
ORDER BY, functions, GROUP BY, JOINs, subqueries; 20 exercises per lesson),
**ERD** (one lesson with theory, a dozen worked example diagrams and 12 drawing
exercises, then six projects: hotel, hospital, food delivery, music festival,
car rental, sports club, each a case designed in four steps) and **CRUD** (CREATE TABLE,
INSERT, UPDATE/DELETE, ALTER/DROP; theory only, the database is read-only).
A lesson has theory, examples and exercises (easy to hard) that are checked
automatically. Plus a free playground. No accounts, nothing is stored.
No MySQL Workbench, no local MySQL.

Files:

- `index.php` – course home (per chapter one card per lesson)
- `chapter.php` – theory, examples and exercise list of one lesson
- `exercise.php` – one exercise: editor, **Check**, **Run only**, hint, solution
- `playground.php` – free SQL editor (any reading query, several statements)
- `exam.php` – exam mode: the playground without navigation (see below)
- `exam-groupby.php`, `exam-innerjoin.php` – graded exams (GROUP BY, INNER JOIN): login, 6 assignments with Check, autosave, submit (see below). Each is a few lines that name an exam set
- `exampage.php` – the exam page itself, shared by those two (not a URL of its own: answers 404)
- `exam-admin.php` – teacher page of the exams (`?set=` picks one): who solved what, queries and results
- `examlib.php` – shared code of the two exam pages (login, storing answers, points)
- `assets/exam.js`, `assets/exam-admin.js` – autosave/submit, and the hover cards of the teacher page
- `exam-sets/*.php` – the exam assignments, same format as a chapter
- `exam-data/` – the submissions, one JSON file per student (not for git)
- `erd.php` – database diagram (ERD): every table with its PK/FK columns and
  the relationships between them, drawn as SVG from `information_schema`
  (the drawing code is in `lib.php`; the exam page shows it as a popup)
- `assets/erd.js` – hover highlighting, fit/save buttons and the popup of the diagram
- `assets/favourites.js` – star exercises (exercise page, lesson page); the home page lists them. Stored in the browser (localStorage), per device
- `lib.php` – shared code: configuration, database, query runner, checker, layout
- `chapters/<n>-<slug>/` – the course content: one folder per chapter, one file per lesson (see below)
- `assets/app.css`, `assets/app.js` – styling and the editor/submit script
- `assets/sqlformat.js` – the SQL formatter behind the **Format** button
- `tools/check-solutions.php` – command line: runs every reference solution
- `tools/load-test.php` – command line: simulates a classroom pressing Check at once
- `Northwind.sql` – the MySQL dump (tables, data, views, procedures)
- `.env` – database connection (host, name, user, password). Not for git.
- `.env.example` – same without the password
- `.htaccess` – stops Apache from serving `.env`, `*.sql` and this README;
  `chapters/.htaccess` and `tools/.htaccess` block those folders completely
- `setup.sql` – creates the read-only user `northwind_ro` (only for your own server)
- `.ddev/config.yaml` – ready-made ddev project (PHP 8.3 + MySQL 8.0)

Three layers keep the data safe with 150 students at once:

1. Only `SELECT`, `WITH`, `SHOW`, `DESCRIBE`, `EXPLAIN` statements are run.
   Anything else is refused before it reaches MySQL.
2. Every connection runs with `SET SESSION TRANSACTION READ ONLY`, so MySQL
   itself refuses writes even for a user with full rights.
3. On your own server you can also use the SELECT-only user from `setup.sql`.

Every connection also enables `ONLY_FULL_GROUP_BY`, so a `SELECT` with
`GROUP BY` may only list grouped columns or aggregates, as standard SQL requires.

Queries that run longer than 3 seconds are killed. The playground shows at most
500 rows; the checker compares at most 2000. See "Load: 150 students at once"
for how the platform keeps a whole class from overloading one shared MySQL.

## How an exercise is checked

**Check** runs the student's query and the reference solution and compares the
two result sets:

1. same number of columns (column *order* matters: the task says which order),
2. same column names, only when the exercise has `'columns' => true`
   (exercises about aliases),
3. same number of rows,
4. same values: row order matters only when the exercise has `'ordered' => true`
   (exercises about `ORDER BY`), otherwise both results are sorted first.
   Numbers are compared with 4 decimals, `NULL` only equals `NULL`.

The first difference becomes the message ("Expected 2 columns, you have 1",
"The rows are right but the order is not", …). On a wrong answer the student
sees their own result and the expected result. Any query that produces the same
result is accepted, not only the reference solution.

**Run only** runs the query without comparing, like the playground.

## Adding or editing exercises

Each chapter is a folder in `chapters/` (`1-select/`, `2-erd/`, `3-crud/`),
loaded in name order. Its `chapter.php` returns the chapter's `title` and
`summary`; every other file that starts with a number is one lesson, again in
name order: `chapters/2-erd/04-something.php` becomes lesson 2.4.

Lessons are also numbered through the whole course (1.1 … 1.7 are 1 … 7, 2.1 is
8, …). That number is the `c=` in the URLs and in the stored favourites, so
adding a lesson in the middle of the course shifts the links of every lesson
after it; adding one at the end of the last chapter shifts nothing.

A lesson file returns an array:

```php
<?php
return [
    'title'   => 'WHERE: filtering rows',
    'summary' => 'One line shown on the course home.',
    'intro'   => <<<'HTML'
        <h3>Theory</h3><p>HTML, shown on the chapter page.</p>
        HTML,
    'examples' => [
        ['title' => 'Customers in Germany',
         'sql'   => "SELECT CompanyName FROM Customers WHERE Country = 'Germany';",
         'note'  => 'Optional HTML under the example.'],
    ],
    'runnable' => true,    // false: the examples are not SELECTs (CRUD lessons): no playground link, not run by the checker
    'exercises' => [       // optional: a lesson without exercises is theory only
        [
            'title'    => 'Products that are out of stock',
            'task'     => '<p>HTML with the assignment.</p>',
            'hint'     => 'HTML, optional.',
            'solution' => "SELECT ProductName FROM Products WHERE UnitsInStock = 0;",
            'ordered'  => false,   // true: row order must match (give a tie-breaker in the task)
            'columns'  => false,   // true: column names must match too
        ],
    ],
];
```

### Design exercises (the student draws an ERD)

An exercise with `'type' => 'erd'` asks for a diagram instead of a query. The
student gets a canvas (`assets/erd-editor.js`). It is a drawing only; no
database is involved.

- **Entity:** drag the Entity block onto the canvas (or click it), type the name.
- **Attribute:** "+ attribute", a name and a **data type** from a list (INT,
  DECIMAL, VARCHAR, TEXT, DATE, DATETIME, BOOLEAN). **PK** marks the key.
- **Relationship:** drag a round connector on the side of the "one" entity onto
  the "many" entity. The foreign key attribute is added (named and typed after
  the key it points to); the student renames it when the task asks.
- **One or many:** a click on an end of the line switches it. Bar + crow's foot
  is one-to-many, two bars one-to-one, two crow's feet many-to-many (no foreign
  key; the check then asks for a link table). The × in the middle removes it.
- **Colour:** the dot in an entity's bar. Not checked.

**Check** compares the drawing with the model design in `solution`.

```php
[
    'type'     => 'erd',
    'title'    => 'One-to-many',
    'task'     => '<p>Draw <code>Teams</code> (...) and <code>Players</code> (...). The foreign key is called <code>TeamID</code>.</p>',
    'hint'     => 'HTML, optional.',
    'starter'  => "Teams: TeamID int PK, Name varchar",      // optional: entities already on the canvas
    'solution' => "Teams: TeamID int PK, Name varchar, City varchar\nPlayers: PlayerID int PK, Name varchar, TeamID int > Teams",
],
```

You write `solution` (and `starter`) as text, in the notation of "Diagrams in a
lesson" below. The editor stores the student's drawing in the same notation,
with place and colour of each entity added (`Teams @24,24 #green: ...`); the
**Text** button of the editor shows it.

Compared: the tables, the columns of each table, the primary keys, the foreign
keys (which column points to which table), the ends of each relationship
(one-to-many or one-to-one) and the **kind of data type** of every column that
has a type in the model design. Kinds: whole number (`int`, `smallint`, ...),
decimal (`decimal`, `float`, ...), text (`varchar` and `text` count as the
same), `date`, `datetime`, `boolean`. Leave the type out in `solution` and that
column's type is free.

Free: where the entities stand, their colours, the order of tables and columns,
capitals, underscores (`first_name` = `FirstName`) and singular or plural table
names (`Team` = `Teams`). The names themselves must match, so **name every
table and column in the task**, and describe values so that the type follows
("a whole number", "day and time"). The verdict names the first difference
("Players and Teams are not linked yet", "the foreign key is on the wrong
side", "the data type of Price is not right", ...) without showing the model
design; that is under "Show solution". Lesson 2.1 has twelve of these.

**Projects.** A project is a lesson of chapter 2 whose `intro` tells one case
and whose exercises are steps: each step's `starter` is the `solution` of the
step before it, so the diagram grows and a mistake in one step does not follow
the student to the next (`chapters/2-erd/02-project-hotel.php` is a good one to
copy). Add a project as the next numbered file in `chapters/2-erd/`. Mind that
this shifts the `c=` of the CRUD lessons after it (see above).

The same type works in an exam set (`exam-sets/*.php`): the drawing is
autosaved like a query, and the teacher page shows the student's diagram next
to the model design.

### Diagrams in a lesson

Write an `<erd>` tag anywhere in a lesson's `intro`, an example's `note` or an
exercise's `task`; the page draws it in the style of the database diagram
(PK/FK tags, crow's foot lines, hover highlighting).

Real tables of the course database, always up to date with the database:

```html
<erd tables="Orders, OrderDetails, Products"></erd>
<erd tables="Orders, Customers" columns="keys"></erd>   <!-- only the PK / FK columns -->
```

A design of your own, one table per line:

```html
<erd>
Members: MemberID PK, FirstName, LastName, Email varchar(100)
Loans: LoanID PK, MemberID > Members, BookID > Books, LoanDate date
Books: BookID PK, Title
+ Authors: AuthorID PK, Name
</erd>
```

- `Table: column, column, ...`
- A column is a name, optionally followed by a data type and `PK`.
- `> Table` makes it a foreign key to that table's primary key
  (`> Table.Column` for another column). A table may point to itself.
- `UNIQUE` after a foreign key draws a one-to-one relationship (a bar at both ends).
- `Table #green: ...` colours the table: blue (default), green, orange, purple,
  red, teal, grey.
- A line `Students <> Courses` draws a many-to-many relationship without a
  link table (a crow's foot at both ends), for a first sketch.
- Tables are placed left to right in the order written. A line that starts
  with `+` goes under the previous table. Lines look best between neighbours,
  so write linked tables next to each other.
- A wide diagram is scaled down to the width of the text; four tables side by
  side still read well.

A mistake (unknown table, unreadable line) shows as a red box on the page, and
`tools/check-solutions.php` reports it as a failure.

Rules of thumb: a solution must return at least 1 and at most 2000 rows; an
`ordered` exercise must have a deterministic order (add a second sort key and
say so in the task); avoid the `Discount` column (the dump stores it with 0
decimals, so every value is 0 or 1) and the blob columns `Picture` / `Photo`.

After editing, run the checker (it needs a database, see the sections below):

```bash
php tools/check-solutions.php
# or, with ddev running and no .env:
DB_HOST=127.0.0.1 DB_PORT=$(docker port ddev-northwind-db 3306 | sed 's/.*://') \
DB_USER=root DB_PASS=root php tools/check-solutions.php
```

It runs every example and solution, and submits every solution to the checker
as if a student typed it. Exit code 0 means all good.

## Exam mode

`exam.php` is the playground with everything else removed: the SQL editor
(Run, Undo, Redo, Format, autocomplete), the results and the table list in the
left panel. The header has no links, so there is no way to reach the course,
the hints or the solutions from that page. The editor starts empty. The
"Diagram (ERD)" button in the left panel opens the database diagram as a popup
on the same page, so `exam.php` is the only URL the students need (whitelist
it alone; `erd.php` is not used during the exam). Hand out
`https://<your-site>/exam.php` during the exam; the course itself stays online
at the other URLs, so hide or rename it if students must not open it.

## Graded exams: GROUP BY and INNER JOIN

`exam-groupby.php` is a graded exam with six GROUP BY assignments
(`exam-sets/group-by.php`, easy to hard, 10 points each; give an exercise
`'points' => n` for another weight). Like `exam.php` the header has no
links, so it is the only URL students need.

1. The student logs in with first name, last name and school e-mail. Only
   `r` + 7 digits + `@student.thomasmore.be` is accepted (checked in the
   browser and on the server); the student number names their file.
2. Per assignment they write one query and press **Check** (or **Run only**).
   Check says *Correct* / *Not yet* with the reason ("Expected 6 rows, you
   have 9") and shows their own result, but **never the expected result**.
   Checked-correct assignments get a ✓ in the left panel.
3. The editor text is **autosaved** every 15 seconds when it changed, and when
   the student goes to another assignment or closes the tab; the bar shows
   "Saved 14:32:05". Check and Run save it too.
4. **Submit exam** (left panel) asks for confirmation, checks every
   assignment whose text was not checked yet, and locks the exam: the student
   then only sees what they handed in. What is in the editor is the answer,
   not the last correct Check.

**One browser per student.** The login is a cookie (12 hours) tied to the
student's file, so closing the browser is harmless: open the page again and
continue. Logging in with the same e-mail from another browser or computer is
refused until the teacher presses **Allow a new login** on that student's page
(or the student pressed "Log out" first). Nobody can take over someone
else's exam by typing their address.

Submissions are JSON files in `exam-data/`, one per student per day
(`group-by-2026-10-28-r1234567.json`). **Every day is a separate session:**
a student who takes the exam again on another day starts with an empty exam,
the earlier day stays as it was. An exam that runs past midnight stays on the
day it started. Files from before this (`group-by-r1234567.json`) are moved
to the day they were started automatically. `exam-data/`
must be writable by PHP; `exam-data/.htaccess` (Apache) and
`.ddev/nginx/deny-private.conf` (ddev, nginx ignores `.htaccess`) keep them
from being downloaded.

**Answers cannot get lost.** A save writes a new file and then swaps it in,
so a refresh, a crash or a full disk never leaves a half or empty file.
A file that cannot be read is never replaced: the save fails with a message
to tell the teacher and a copy goes to `exam-data/broken/`. Every editor
text is also appended to `exam-data/log/group-by-<id>.jsonl`; on the
student page under **Saved versions** you can **Restore** any of them. An
autosave never overwrites an answer with an empty editor, and the browser
keeps its own copy: an editor that comes up empty gets the text back.

**Teacher page:** set `EXAM_ADMIN_PASS` in `.env` (for ddev without `.env`:
`web_environment: [EXAM_ADMIN_PASS=...]` in `.ddev/config.yaml`), then open
`exam-admin.php`. Without the setting the page answers 403.

- **Exam password** (top of the overview): every login needs it, so only
  students in the room can start. Without a password the exam is closed:
  nobody can log in. Announce it (on the board) at the start of the exam;
  emptying it after everyone is in closes the door again. Students who are
  already logged in are not affected when you change or empty it. The
  default is `'password'` in `exam-sets/group-by.php`; what you save on the
  overview is stored in `exam-data/settings-group-by.json` and wins.
- **Day:** the buttons at the top pick the exam day (with the number of
  students); without a choice you see today, or the last day with
  submissions. The overview, averages, CSV and "Submit all open exams" are
  for that day only. The exam password is the same for every day.
- Overview: every student × Q1–Q6 with the last Check (✓ correct, ✗ not yet,
  ! MySQL error, – not checked, ✎ changed after the last Check) and the
  points, total, submit time and last activity. **Hover a cell** for the
  assignment, the verdict and the student's answer. "Auto-refresh" reloads
  the page every 15 seconds.
- Click a cell: the student's answer is run again and shown with its actual
  result next to the expected result, plus every Check with time and verdict.
- **Points:** automatic = all points when the last Check is correct,
  otherwise 0. On the detail page you overrule them (steps of 0.5, with an
  optional note); overruled points get a `*`. "Back to automatic" undoes it.
- **Reopen exam** lets a student who submitted continue; **Allow a new login**
  frees the login for another browser.
- **Remove submission** (student page, asks first) takes the student out of
  the results; they can log in again and start over. The file moves to
  `exam-data/deleted/` (time in the name): move it back to undo.
- **Submit all open exams** at the end hands in every exam that is still open,
  as if each student pressed Submit.
- **Download CSV**: student number, e-mail, names, total, and per assignment
  points, status, Checks, answer and note (`;`-separated, opens in Excel).

Reset before a new group: delete `exam-data/group-by-*.json` (or
`inner-join-*.json`).

**Two exams exist:** `exam-groupby.php` (`exam-sets/group-by.php`) and
`exam-innerjoin.php` (`exam-sets/inner-join.php`: six assignments that need
only inner joins, from two tables to four, one self join, the last one
sorted). Everything in this section holds for both; in file names and URLs
read `inner-join` for `group-by`. The teacher page shows one exam at a time:
`exam-admin.php?set=inner-join`, or the "Exam:" links at the top. Each exam
has its own password, students, days and results.

**A new exam** is two files: the assignments in `exam-sets/<name>.php` (copy
one of the two) and a page `exam-<name>.php` of three lines:

```php
<?php
$set = '<name>';
require __DIR__ . '/exampage.php';
```

The teacher page finds it by itself. `php tools/check-solutions.php` checks
exam solutions too.

## Connection settings

`lib.php` reads, in this order: `.env`, then environment variables, then
built-in defaults. Keys: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
A `.env` that points to the hosting database therefore also wins inside ddev.
To work locally with ddev, rename it (`mv .env .env.hosting`) or comment out
its lines; rename it back before uploading.

## A. Shared hosting (webhosting.be)

1. Import `Northwind.sql` once through phpMyAdmin or MySQL Workbench into the
   database the host gave you (`ID291816_northwind`). The dump starts with
   `CREATE DATABASE northwind; USE northwind;` – delete those two lines first,
   or in phpMyAdmin select the database before importing.
2. Fill in `DB_PASS` in `.env`.
3. Upload the whole folder except `Northwind.sql`, `setup.sql` and `.ddev/`:
   all `*.php` files, `assets/`, `chapters/`, `tools/`, `exam-sets/`, `exam-data/`
   (only its `.htaccess`; make it writable), `.htaccess` and `.env`,
   to the web root (or a sub folder such as `/sql/`).
4. Open the URL. The home page must show 3 chapters with 18 lessons; the playground must list
   13 tables.

Check once that `https://<your-site>/.env` and
`https://<your-site>/chapters/1-select/01-select.php` give a 403.

## B. Run it with ddev (own laptop / classroom LAN)

Needs [ddev](https://ddev.com) + Docker (OrbStack / Docker Desktop).

```bash
cd exams
ddev start
```

The first start imports `Northwind.sql` and runs `setup.sql` automatically
(see `hooks` in `.ddev/config.yaml`). Open <https://northwind.ddev.site>.

To let the whole class reach it on the classroom network:

```bash
ddev config --bind-all-interfaces --host-webserver-port=8080
ddev restart
```

Students then open `http://<your-ip>:8080`. Find your IP with
`ipconfig getifaddr en0` (Mac). Alternatively, `ddev share` gives a public
ngrok URL.

Useful commands:

```bash
ddev mysql                 # MySQL shell as root
ddev delete -O && ddev start   # wipe the database and re-import from scratch
```

## C. Any other server with PHP 8 + MySQL

1. Import the dump (must go through the `mysql` CLI or Workbench, because of
   the `DELIMITER` blocks for stored procedures):

   ```bash
   mysql -u root -p < Northwind.sql
   mysql -u root -p < setup.sql
   ```

2. Put the folder (see A.3) with a filled-in `.env` in the web root.

3. Quick test without a web server:

   ```bash
   DB_HOST=127.0.0.1 DB_PASS=northwind php -S 0.0.0.0:8000
   ```

## Load: 150 students at once

The Northwind database is tiny (the dump is 800 KB) and every course query
runs in milliseconds, so MySQL itself is not the problem. What hurts a shared
MySQL is many *connections* at the same moment and runaway queries (an
accidental cross join) that keep a connection and a CPU busy. A queue server
would bound that, but shared hosting cannot run one. The platform does the same
job in plain PHP:

- **Admission gate.** At most `DB_SLOTS` requests (default 10) talk to MySQL
  at the same time. Slots are `flock()`ed files in the temp dir, shared by every
  PHP process and freed automatically when a process ends. A request that
  finds no free slot within 2 seconds answers `503` with `Retry-After: 2`;
  the page retries by itself (up to 4 times) and shows "Server busy" only after
  that. Set `DB_SLOTS` in `.env` when the host's `max_user_connections` is
  lower than 10 (plus one spare connection for killing truncated queries).
  Without a writable temp dir the gate is simply off.
- **Reference solutions are cached.** A **Check** used to run two queries, the
  student's and the solution's. The solution's result is now cached for a day
  (APCu or temp dir; the key contains the solution text, so editing a solution
  invalidates it). One MySQL query per Check.
- **Page loads never touch MySQL** once the table list is cached (10 minutes).
- **Runaway queries** are killed after 3 seconds (`QUERY_TIMEOUT_MS` in
  `lib.php`), so a slot is never held longer than that.

Prove it before the exam instead of hoping:

```bash
php tools/load-test.php https://<your-site>/ --students=150 --rounds=3
# ddev (self-signed certificate):
php tools/load-test.php https://northwind.ddev.site/ --students=150 --rounds=3 --insecure
```

It posts 150 Checks at once (70 % correct answers, 20 % wrong, 10 % cross
joins) and prints the HTTP statuses, verdicts and latency per round. `200`
everywhere is the goal; a few `503` mean the gate did its job; `429` comes from
the host's per-IP limit (next section). Exit code 1 on MySQL connection errors.

## 429 Too Many Requests

A whole classroom sits behind one public IP (the school's NAT). Shared hosts
such as webhosting.be rate-limit **per IP**, so 150 students look like one
visitor sending far too many requests, and the host answers `429` (or `503`).
This limit lives at the host, not in the code.

What the platform does about it:

- The table list is cached for 10 minutes (APCu or a file in the temp dir),
  so a page load no longer runs the slow `information_schema` query, and a
  plain page load with a warm cache does not open a MySQL connection at all.
- **Check** and **Run** send the query with `fetch()` and only the results
  block comes back: no full page reload, no re-download of the editor assets.
  Hints and solutions are in the page already, opening them costs no request.
- The buttons are disabled while a query runs, so Ctrl+Enter twice does not
  send two requests.
- On `429`/`503` the page waits for the host's `Retry-After` (default 3 s),
  retries twice, and otherwise asks the student to try again.

If it still happens:

1. Find the classroom's public IP (`curl ifconfig.me`) and ask webhosting.be
   support to whitelist it or raise the per-IP request limit. Look in the
   control panel for "rate limiting", "DDoS protection" or "requests per IP".
2. Send support the response headers of a 429 (`curl -I -X POST <url>`):
   `Server:` and `Retry-After:` tell them which layer fires.
3. For an exam, prefer section B (ddev on the classroom LAN): no per-IP
   limit, no shared-host PHP process cap, and LAN latency.

## For students

- Start on the home page, pick a lesson, read the theory, try the examples
  ("Open in playground"), then go through the exercises one by one.
- On an exercise: type one query, press **Check** or **Ctrl+Enter**
  (⌘+Enter on Mac). **Run only** shows the result without checking.
- Suggestions for keywords, tables, columns and functions pop up while you
  type (column names show the tables they belong to; a dot after a table name
  lists only its columns; a function such as `ROUND()` is inserted with its
  opening parenthesis). **Enter** or **Tab** accepts, **Esc** closes,
  **Ctrl+Space** opens them by hand. The left panel lists every table with its
  columns; `PK` and `FK` mark the primary and foreign keys.
- **Diagram** (top menu, or "Diagram (ERD)" in the left panel) shows all tables
  and the lines between them: crow's foot on the *many* side, bar on the *one*
  side. Those are the columns to `JOIN` on. Hover a table to highlight its
  relationships; **Save .svg** downloads the diagram.
- Table names are case-sensitive on the server: `Products` works,
  `products` gives "Table doesn't exist". Column names and keywords are not.
- **Save .sql** downloads what is in the editor as a `.sql` file
  (`exercise-3-2.sql`, `playground.sql`, `exam.sql`), for example to hand in
  your answers or keep them.
- **Format** (or ⇧Ctrl+F / ⇧⌘+F, also outside the editor) tidies the query:
  every clause keyword (`SELECT`, `FROM`, `WHERE`, …) on its own line, its
  contents on the next line indented, keywords in capitals. One undo step.
- **Undo / Redo** buttons sit next to Run; Ctrl+Z and ⌘+Z both undo,
  Ctrl+Y or ⇧⌘+Z redo.
- Pasting from Word, PDF or Teams is fine: curly quotes, non-breaking spaces
  and long dashes are turned into plain SQL characters automatically.
- Stuck? Open **Hint**. Still stuck? Open **Show solution**, read it, close it
  and write it yourself.
- Playground: several statements separated by `;` run one after another, each
  gets its own result tab. Execution stops at the first error, like in
  Workbench, and the tab with the error opens.
  Only the first 500 rows are shown. Use `LIMIT` to narrow down.
- Views with spaces in their name need backticks: `` SELECT * FROM `Order Subtotals`; ``
- Stored procedures cannot be called (the user has no `EXECUTE` right).
# database-portal
# database-portal
