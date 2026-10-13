# Northwind SQL playground

A one-page SQL editor for the Northwind database. Students open a URL, type
MySQL, press Run, and see the result. No MySQL Workbench, no local MySQL.

Files:

- `index.php` – the playground (PHP, no dependencies, talks to MySQL via PDO)
- `Northwind.sql` – the MySQL dump (tables, data, views, procedures)
- `.env` – database connection (host, name, user, password). Not for git.
- `.env.example` – same without the password
- `.htaccess` – stops Apache from serving `.env`, `*.sql` and this README
- `setup.sql` – creates the read-only user `northwind_ro` (only for your own server)
- `.ddev/config.yaml` – ready-made ddev project (PHP 8.3 + MySQL 8.0)

Three layers keep the data safe with 30 students at once:

1. The playground only runs `SELECT`, `WITH`, `SHOW`, `DESCRIBE`, `EXPLAIN`.
   Anything else is refused before it reaches MySQL.
2. Every connection runs with `SET SESSION TRANSACTION READ ONLY`, so MySQL
   itself refuses writes even for a user with full rights.
3. On your own server you can also use the SELECT-only user from `setup.sql`.

Queries that run longer than 5 seconds are killed. Only 500 rows are shown.

## Connection settings

`index.php` reads, in this order: `.env`, then environment variables, then
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
3. Upload `index.php`, `.env` and `.htaccess` to the web root (or a sub folder
   such as `/sql/`).
4. Open the URL. The left panel must list 13 tables.

Check once that `https://<your-site>/.env` gives a 403, not the password.

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

2. Put `index.php`, `.htaccess` and a filled-in `.env` in the web root.

3. Quick test without a web server:

   ```bash
   DB_HOST=127.0.0.1 DB_PASS=northwind php -S 0.0.0.0:8000
   ```

## 429 Too Many Requests

A whole classroom sits behind one public IP (the school's NAT). Shared hosts
such as webhosting.be rate-limit **per IP**, so 30 students look like one
visitor sending far too many requests, and the host answers `429` (or `503`).
This limit lives at the host, not in `index.php`.

What the playground does about it:

- The table list is cached for 10 minutes (APCu or a file in the temp dir),
  so a page load no longer runs the slow `information_schema` query, and a
  plain page load with a warm cache does not open a MySQL connection at all.
- **Run** sends the query with `fetch()` and only the results block comes
  back: no full page reload, no re-download of the editor assets.
- The Run button is disabled while a query runs, so Ctrl+Enter twice does
  not send two requests.
- On `429`/`503` the page waits for the host's `Retry-After` (default 3 s),
  retries twice, and otherwise asks the student to press Run again.

If it still happens:

1. Find the classroom's public IP (`curl ifconfig.me`) and ask webhosting.be
   support to whitelist it or raise the per-IP request limit. Look in the
   control panel for "rate limiting", "DDoS protection" or "requests per IP".
2. Send support the response headers of a 429 (`curl -I -X POST <url>`):
   `Server:` and `Retry-After:` tell them which layer fires.
3. For an exam, prefer section B (ddev on the classroom LAN): no per-IP
   limit, no shared-host PHP process cap, and LAN latency.

## For students

- Type a query, press **Run** or **Ctrl+Enter** (⌘+Enter on Mac).
- **Ctrl+Space** autocompletes table and column names.
- The left panel lists every table and view with its columns.
- Several statements separated by `;` run one after another, each gets its
  own result. Execution stops at the first error, like in Workbench.
- Only the first 500 rows are shown. Use `LIMIT` to narrow down.
- Views with spaces in their name need backticks: `` SELECT * FROM `Order Subtotals`; ``
- Stored procedures cannot be called (the user has no `EXECUTE` right).
