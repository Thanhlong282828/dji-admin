# Full Database Restore

Symptoms: durable database loss or verified corruption. Impact: Tier 0 reads/writes unavailable. Verify health, storage and approved recovery point; freeze deployments/consumers. Restore only into a fresh isolated target, verify artifact checksum, migrations, tables, indexes, FKs, row/checksum baseline and application readiness. Never auto-run down migrations. Roll back the application only when schema compatibility passes; otherwise forward-fix. Escalate to incident commander, database owner, security and business owners before any production cutover.
