# ADR 0004: Environment Isolation

Status: Accepted

## Context
Production, local demo, automated tests and browser fixtures have different data purposes.

## Decision
Use `schema_admin`, `_development`, `_testing` and `_browser_testing` contracts enforced by EnvironmentSafetyGuard.

## Consequences
Production is read-only during governance/testing phases; demo and fixture data are never copied to production.
