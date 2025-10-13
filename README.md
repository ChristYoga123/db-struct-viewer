# DB Structure Viewer (Laravel)

A lightweight Laravel console tool to inspect your database schema from the terminal. It shows columns and indexes for one or all tables, and can help you find related migration files.

## Requirements
- PHP >= 8.1
- Laravel 10, 11, or 12
- MySQL/MariaDB (uses `information_schema`, `SHOW COLUMNS`, and `SHOW INDEXES`)

## Installation
```bash
composer require christyoga123/db-structure-viewer --dev
```

The service provider is auto-discovered; no manual registration is required.

## Command Overview
```
php artisan db:structure {table?} {--all} {--list} {--migrations}
```
- `table?` (optional): Show structure for the given table
- `--all`: Show structure for every table
- `--list`: List all tables in the current database
- `--migrations`: Try to locate related migration files for the selected table(s)
- `-v|--verbose`: Also print full file paths for related migrations

If no arguments/options are provided, an interactive prompt will guide you.

## Usage

### List all tables
```bash
php artisan db:structure --list
```

### Show a specific table
```bash
php artisan db:structure users
```

### Show a specific table and related migrations
```bash
php artisan db:structure users --migrations
```

### Show all tables
```bash
php artisan db:structure --all
```

### Show all tables and related migrations
```bash
php artisan db:structure --all --migrations
```

### Interactive mode
Simply run without args and choose from the menu:
```bash
php artisan db:structure
```

## What you’ll see
For each table:
- Columns: name, type, nullability, key, default, extra
- Indexes: name, column, uniqueness, type
- (Optional) Related migration files, with an inferred type such as "Create Table", "Add Column(s)", etc. Use `-v` to include full file paths and optionally print file contents on confirmation.

## How migrations are detected
The command scans `database/migrations` and matches common filename patterns that include the table name (plural and singular variations), such as:
- `create_{table}_table`
- `*_to_{table}_table`
- `*_in_{table}_table`
- `*_{table}_*`

## Notes and limitations
- Currently tailored for MySQL/MariaDB dialects via `information_schema`, `SHOW COLUMNS`, and `SHOW INDEXES`.
- Migration detection is filename- and simple content-based and may not catch every edge case.

## Troubleshooting
- Ensure your `.env` database connection points to the intended schema.
- Make sure tables exist and your DB user has access to `information_schema`.
- For large schemas, consider using `table` or `--list` first to scope output.

## License
This package is open source software. If a `LICENSE` file is not present, please open an issue to clarify licensing terms.
