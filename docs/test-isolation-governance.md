# Test Isolation Governance

`schema_admin` and `schema_admin_development` are forbidden test targets. PHPUnit/integration/concurrency use `schema_admin_testing` or a narrowly named `schema_admin_testing_*`; browser automation uses `schema_admin_browser_testing`.

Pure unit and proven architecture tests may run in parallel. Process concurrency, shared browser fixtures and queue/process suites run serially. A race intentionally shares one committed MySQL fixture between two workers; separate worker databases would invalidate it.

The runner owns children, uses an atomic barrier, bounded waits and `finally` termination. CI checks the absolute known worker path before and after tests and never kills a process. Cache/queue cleanup targets isolated namespaces; shared Redis `FLUSHALL` is prohibited.
