# Test Fixture Governance

Fixtures are synthetic, deterministic, company-scoped and owned by their test/command. They contain no production PII, credentials, financial identifiers or copied hashes. Browser fixtures accept only a browser-testing database and clean all owned rows.

Factories suit simple entities; domain commands/services suit aggregates with lifecycle, checksum, idempotency, history or outbox invariants. Process fixtures must commit before workers start. `DatabaseTransactions` is unsuitable across PHP processes, so concurrency suites own explicit setup/cleanup. Filesystem tests use fake storage or scoped temporary directories.
