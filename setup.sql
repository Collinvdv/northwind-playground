-- Read-only user for the SQL playground (index.php).
-- Run once, as root, AFTER importing Northwind.sql:
--   mysql -u root -p < setup.sql
CREATE USER IF NOT EXISTS 'northwind_ro'@'%' IDENTIFIED BY 'northwind';
GRANT SELECT, SHOW VIEW ON northwind.* TO 'northwind_ro'@'%';
FLUSH PRIVILEGES;
