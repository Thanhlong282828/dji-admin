# Phase 3F-15 DR Exercise Report

Environment: isolated Docker project `phase3f15`; database `schema_admin_testing_dr`; restored database `schema_admin_testing_dr_restore_testing`. Source HEAD: `2cacdbf`.

| Gate | Evidence | Result |
|---|---:|---|
| Backup | 42,213 bytes; 0.57s; mode 0600; SHA-256 verified | PASS |
| Restore | 3.77s; 61 migrations; fixture checksum equal | PASS |
| Restored structure | 230 tables; 1,778 index entries; 720 FKs | PASS |
| Redis loss | live 200, ready 503; recovery ready 200 in 6s | PASS |
| MySQL loss | live 200, ready 503; recovery ready 200 in 6s | PASS |
| Queue | depth 1 while stopped, 0 after restart, failed jobs 0 | PASS |
| Scheduler | stale while stopped, fresh after 31s | PASS |
| Reverb | core live/ready 200 while stopped | PASS |
| Rollback | previous immutable image ready; migration count 61 | PASS |
| Outbox/adapters | focused 54 tests / 353 assertions | PASS |

Observed testing RTO is 31 seconds (slowest required recovery). Deterministic fixture RPO is 0 seconds. PITR and private-storage outage are documented design-only gaps.
