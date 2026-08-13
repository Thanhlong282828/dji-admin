# ADR 0004: Environment Isolation

Status: Accepted

## Context
Production, local demo, automated tests and browser fixtures have different data purposes.

## Decision
Use `schema_admin` for Mac local manual/demo work, `_testing` namespaces for PHPUnit/integration, and `_browser_testing` for browser fixtures. VPS production supplies a separate database through its environment. The former development-only database is removed.

## Consequences
Automated tests never target the writable Mac local-main database. Demo and fixture data are never copied to VPS production.
