# Phase 3F-12 Runtime Configuration Audit

## Existing state

The repository keeps `env()` access inside `config/*`; application and module code consumes `config()`. Phase 3F-8–11 already supplied environment guards, reliability, observability and release evidence, but the only named feature toggle was the release dashboard flag. There was no typed catalog, explicit failure policy, drift command, stale-flag metadata or release snapshot checksum.

Integration HTTP delivery already refused real external IO except explicit mock mode. Notification preferences controlled recipient channels, but there was no global operational kill switch above those preferences. Business policy for Payroll, tax, insurance, Employee authorization and Workflow remains domain-owned and is deliberately excluded.

## Decision

No database persistence is justified for this foundation. Current operations require environment-scoped, deploy-controlled flags and read-only visibility, not live UI mutation. Therefore Phase 3F-12 adds no migration, table, maker-checker workflow or mutation race suite. A persistent override store becomes justified only when independent runtime changes, multiple operators and durable approval/history are required.

Secret configuration remains in environment/credential boundaries and never enters the flag catalog, UI, command output, logs or release evidence.

Final audit found no `env()` usage outside Laravel config files. The typed catalog contains operational/optional capability and external-side-effect keys only; Payroll formulas, salary, tax, insurance and Employee authorization are absent. Final quality and production-safety evidence is recorded in `docs/enterprise-runtime-configuration.md`.
