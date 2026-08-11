# ADR 0002: CQRS Enterprise Boundaries

Status: Accepted

## Context
Governed workflows require traceable writes and read-only projections while legacy/simple modules remain pragmatic.

## Decision
Enterprise writes enter Commands/Handlers and reads enter Queries/read services. Domain services remain valid and own invariants.

## Consequences
Controllers stay thin. CQRS is not imposed on disabled skeletons solely for uniformity.
