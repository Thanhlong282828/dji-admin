# ADR 0005: Operations Governance

Status: Accepted

## Context
Reliability, release, security, performance and architecture evidence must be observable without owning business state.

## Decision
Expose typed read-only snapshots through protected Operations commands/pages and immutable release evidence.

## Consequences
Operations surfaces contain aggregate safe metadata only, require `operations.view`, and introduce no generic business database.
