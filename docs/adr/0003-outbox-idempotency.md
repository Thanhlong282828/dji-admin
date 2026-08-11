# ADR 0003: Outbox and Idempotency

Status: Accepted

## Context
Workflow, Notification, IAM, Integration and Audit require reliable asynchronous delivery without duplicate business mutation.

## Decision
Persist intent/history transactionally and use deterministic event identity, database unique guards/checkpoints, bounded retry and process-race evidence.

## Consequences
Outbox schemas remain context-owned; common metadata vocabulary may evolve without replacing proven guards.
