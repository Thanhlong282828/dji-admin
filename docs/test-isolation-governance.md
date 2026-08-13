# Test Isolation Governance

`schema_admin` is a forbidden automated-test target. PHPUnit/integration/concurrency use `schema_admin_testing` or a narrowly named `schema_admin_testing_*`; browser automation uses `schema_admin_browser_testing`. The historical `schema_admin_development` database is **REMOVED / NO LONGER EXISTS** and is never a valid target.

Pure unit and proven architecture tests may run in parallel. Process concurrency, shared browser fixtures and queue/process suites run serially. A race intentionally shares one committed MySQL fixture between two workers; separate worker databases would invalidate it.

The runner owns children, uses an atomic barrier, bounded waits and `finally` termination. CI checks the absolute known worker path before and after tests and never kills a process. Cache/queue cleanup targets isolated namespaces; shared Redis `FLUSHALL` is prohibited.
