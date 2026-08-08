# Enterprise Canonical Status Matrix

Updated: 2026-08-09  
Authority: this file is the canonical current-state summary. Older phase documents remain historical evidence and may contain an earlier `PARTIAL` status followed by later completion evidence.

| Enterprise capability | Canonical status | Scope statement |
|---|---|---|
| Employee Lifecycle | **COMPLETED** | Append-only lifecycle, transitions, position/manager/document history, CQRS, tenant security and concurrency foundation. This does not mean every Employee Core convenience feature is complete. |
| Talent | **COMPLETED** | Performance cycle, goals, reviews, ratings and talent snapshot foundation. |
| Career | **COMPLETED** | Career plan, succession, readiness and immutable history foundation. |
| Analytics | **COMPLETED** | Versioned workforce/talent/organization snapshots, deterministic metrics and dashboard foundation. |
| Governance | **COMPLETED** | HR data quality, consent/document governance and append-only governance audit foundation. |
| Workflow | **COMPLETED** | Versioned workflow definitions, tasks, decisions, histories, outbox and concurrency foundation. |
| Notification | **COMPLETED** | Template versions, messages, recipients, delivery attempts, preferences and outbox foundation. |
| IAM | **COMPLETED** | Access lifecycle, SoD, privileged access, review/certification, Spatie adapter and integrations. |
| Integration | **COMPLETED** | Connection, outbound/inbound, webhook, retry, dead-letter and domain bridge foundation. No real production provider is implied. |
| Audit | **COMPLETED** | Immutable enterprise timeline, six source consumers, checkpoints, streaming export, secure download and archive foundation. |

## Product-level qualifications

- Full Employee product remains broader than Employee Lifecycle and still has explicitly deferred Employee Core convenience flows.
- Full Payroll product remains `PARTIAL`; completed payroll foundations do not imply live statutory/legal compliance or external bank/government/ERP execution.
- Recruitment/Onboarding and workforce operations retain explicitly documented external integrations and portal gaps.
- Disabled standalone modules are not promoted to `COMPLETED` by similarly named capabilities implemented inside enabled bounded contexts.

## Governance rule

Future phase completion must update this matrix in the same change as its final evidence. Historical documents must not be rewritten to imply a capability was complete before its actual completion gate.
