# ADR 0001: Modular Monolith

Status: Accepted

## Context
The platform shares Laravel runtime, transactions, tenant data and operational controls across many bounded contexts.

## Decision
Retain one deployable modular monolith with explicit module ownership and dependency manifests.

## Consequences
Cross-module contracts require governance; no microservice independence is claimed. Integration, Notification and Audit may be extraction candidates only after transaction/data coupling is removed.
