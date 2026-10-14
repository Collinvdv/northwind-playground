# Northwind SQL course

A small learning platform for MySQL on the Northwind database. Students open a
URL and get seven chapters (SELECT, WHERE, ORDER BY, functions, GROUP BY, JOINs,
subqueries), each with theory, runnable examples and 20 exercises (easy to hard)
that are checked automatically, plus a free playground. No accounts, nothing is stored.
No MySQL Workbench, no local MySQL.

Files:

- `index.php` – course home (chapter cards)
- `chapter.php` – theory, examples and exercise list of one chapter
- `exercise.php` – one exercise: editor, **Check**, **Run only**, hint, solution
- `playground.php` – free SQL editor (any reading query, several statements)
- `lib.php` – shared code: configuration, database, query runner, checker, layout
- `chapters/*.php` – the course content, one file per chapter (see below)
- `assets/app.css`, `assets/app.js` – styling and the editor/submit script
- `assets/sqlformat.js` – the SQL formatter behind the **Format** button
- `tools/check-solutions.php` – command line: runs every reference solution
- `Northwind.sql` – the MySQL dump (tables, data, views, procedures)
- `.env` – database connection (host, name, user, password). Not for git.
- `.env.example` – same without the password
- `.htaccess` – stops Apache from serving `.env`, `*.sql` and this README;
  `chapters/.htaccess` and `tools/.htaccess` block those folders completely
- `setup.sql` – creates the read-only user `northwind_ro` (only for your own server)
- `.ddev/config.yaml` – ready-made ddev project (PHP 8.3 + MySQL 8.0)

Three layers keep the data safe with 30 students at once:

1. Only `SELECT`, `WITH`, `SHOW`, `DESCRIBE`, `EXPLAIN` statements are run.
   Anything else is refused before it reaches MySQL.
2. Every connection runs with `SET SESSION TRANSACTION READ ONLY`, so MySQL
   itself refuses writes even for a user with full rights.
3. On your own server you can also use the SELECT-only user from `setup.sql`.

Queries that run longer than 5 seconds are killed. The playground shows at most
500 rows; the checker compares at most 2000.

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

Each chapter is a PHP file in `chapters/` that returns an array. Files are
loaded in name order, so `08-something.php` becomes chapter 8.

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
    'exercises' => [
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
   all `*.php` files, `assets/`, `chapters/`, `tools/`, `.htaccess` and `.env`,
   to the web root (or a sub folder such as `/sql/`).
4. Open the URL. The home page must show 7 chapters; the playground must list
   13 tables.

Check once that `https://<your-site>/.env` and
`https://<your-site>/chapters/01-select.php` give a 403.

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

## 429 Too Many Requests

A whole classroom sits behind one public IP (the school's NAT). Shared hosts
such as webhosting.be rate-limit **per IP**, so 30 students look like one
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

- Start on the home page, pick a chapter, read the theory, try the examples
  ("Open in playground"), then go through the exercises one by one.
- On an exercise: type one query, press **Check** or **Ctrl+Enter**
  (⌘+Enter on Mac). **Run only** shows the result without checking.
- Suggestions for keywords, tables and columns pop up while you type (column
  names show the tables they belong to; a dot after a table name lists only
  its columns). **Enter** or **Tab** accepts, **Esc** closes, **Ctrl+Space**
  opens them by hand. The left panel lists every table with its columns.
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
