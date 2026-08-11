# Full Service Recovery

Recover in order: network/storage, MySQL, Redis, core HTTP, one queue/Horizon authority, one scheduler, Reverb, then external adapters. At every step verify safe health payloads and immutable evidence. Keep integrations/email disabled until core and idempotency checkpoints are healthy. Roll back only compatible application images; never destructive database rollback by default. Escalate failed integrity, security exposure or unmet recovery objective.
