# Enterprise Test Engineering Audit

## Baseline

The 2026-08-11 pre-change inventory found 146 PHPUnit files, 468 listed tests and seven Playwright scripts. `phpunit.xml` exposed Unit, Feature and Concurrency suites. Architecture, security, integration, performance and operations classifications existed through paths/behavior rather than separate PHPUnit suites. Xdebug coverage exists in CI, but no arbitrary global percentage is proof of business correctness.

The MySQL race runner already used two real processes, an atomic filesystem barrier, bounded timeouts and `finally` termination. The missing control was an independent preflight/postflight check for the known `workforce_concurrency_worker.php` path. CI used one testing schema in one job, avoiding inter-job collision but not visibly expressing serial concurrency governance.

Fixture review found `RefreshDatabase`, manually committed process fixtures, factories and guarded browser commands. Process tests correctly cannot depend on parent transactions because child processes require committed rows. Browser scripts repeat some launch/login/console logic; consolidation remains debt because changing all scripts would risk prior viewport evidence.

## Findings

- No orphan project worker existed at baseline.
- `schema_admin_testing` was reachable with zero pending migrations; Redis, Chrome, Playwright and Docker were available.
- Race-runner sleeps are bounded polling around real synchronization, not arbitrary correctness delays.
- Full-suite parallelization is unsafe for concurrency controllers and shared browser fixtures.
- The documented single skip remains visible and cannot become a silent pass.

The final skip is `AdminRouteSecurityGovernanceTest::test_fixture_login_routes_are_not_registered_in_production`; it is intentionally production-environment-specific and retains an explicit rationale. No test was skipped to make the suite green.
