# Security & Best-Practice Review Checklist

This document lists file/line references where additional security hardening or best-practice validation is recommended. Line numbers reflect the current repository state.

## Database Query Builders

- `src/Infrastructure/Database/Query/InsertBuilder.php`
  - Line 19-30: `addColumn()` — Validate `$name` against an allowlist; identifiers are concatenated.
  - Line 37-41: `setSql()` — Validate `$this->dbTable` (table name) against an allowlist.

- `src/Infrastructure/Database/Query/UpdateBuilder.php`
  - Line 22-30: `addColumn()` — Validate `$name` (column identifier) before concatenation.
  - Line 39-45: `setSql()` — Validate `$this->dbTable` and `$this->updateOnColumnName` (identifiers).

- `src/Infrastructure/Database/Query/SelectBuilder.php`
  - Line 30-34: `addWhereExtraClause()` — `$clause` is raw SQL; only accept trusted server-generated fragments.
  - Line 52-68: `addWhereColumn()` — Validate `$columnNameSql` against known column names.

- `src/Infrastructure/Database/Query/QueryBuilder.php`
  - Line 63-67: `alterBooleanArgs()` — Use `PostgresService::convertBoolToPostgresBool`; ensure correct class reference.
  - Line 77-98: `execute()` — Ensure `PG_CONN` is a securely managed connection resource; not overrideable by user input.

## PostgreSQL Service

- `src/Infrastructure/Database/Postgres.php`
  - Line 40-48: `getSchemaTables()` — `$skipTables` values are interpolated; if user-provided, risk of SQL injection. Prefer validation/allowlist or construct safe parameterized filters.
  - Line 54-61: `doesTableExist()` — Parameters are safe; still validate requested table/schema names at call sites.
  - Line 66-73: `getTableMetaData()` — Parameterized; ensure callers do not expose metadata for unauthorized tables.

## Utilities & Core

- `src/Pageflow.php` (not line-referenced here due to partial context): Review `.env` handling for required variables, secret management, and production defaults. Ensure error display is disabled on live servers and sensitive values are not logged.
- `src/Infrastructure/Utilities/PHPMailerService.php` (partial context): Confirm SMTP credentials are loaded only from `.env`, outputs do not leak secrets, and dev-mode email routing is enforced.
- `src/Infrastructure/Utilities/ThrowableHandler.php` (partial context): Verify log file path permissions, rotation, and that sensitive data is not included in emailed/logged messages.

## General Recommendations

- Identifier allowlists: Maintain per-table allowlists of permissible table and column names where builders accept identifiers.
- Input validation: Normalize/validate all incoming user inputs before they reach query builders.
- Least privilege: Ensure the DB user used by `POSTGRES_CONNECTION_STRING` has minimal privileges required.
- Error handling: Avoid logging full SQL with sensitive args on production; consider masking.
- Configuration security: Keep `.env` outside web root, restrict access, and never check it into version control.
