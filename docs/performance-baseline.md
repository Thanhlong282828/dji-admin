# Phase 3F-18 Local Performance Baseline

Environment: Apple local host, PHP 8.4.23, Laravel 13.21.1, MySQL 9.6 client/server-compatible local service, Redis 8.8.0, Docker Engine 29.5.3. HTTP used the single-process PHP development server; results are not production SLA or Nginx/PHP-FPM capacity.

Dataset: one synthetic testing company and 5,000 employees (4,500 active, 500 probation). No production or development row was copied.

## Measurements

| Workload | Evidence |
|---|---|
| Employee export, 1,000 rows | 9 SELECTs, 81.62 ms, 8 MiB memory delta |
| Employee export, 5,000 rows | 41 SELECTs, 460.54 ms, 8 MiB memory delta, 57.15 MB peak |
| 100 DB operations | point 18.39 ms; filtered list 16.51 ms; aggregate 84.64 ms |
| 500 DB operations | point 57.97 ms; filtered list 59.83 ms; aggregate 380.49 ms |
| 1,000 DB operations | point 99.38 ms; filtered list 134.60 ms; aggregate 828.27 ms |
| Queue dispatch foundation | 100/500/1,000 fake-safe dispatch measurements: 19.21/16.65/25.49 ms; this is dispatch overhead, not worker processing capacity |

Sequential HTTP, 60 requests/route: Dashboard p50/p95/p99 268.97/300.27/462.29 ms; Employee list 453.42/542.75/671.65 ms; Performance dashboard 326.29/407.47/510.86 ms. All 180 responses succeeded. Page timing includes browser navigation and the single-process server.

Performance fixture cleanup removed the deterministic testing company and all `PERF-*` employees. Browser fixture cleanup returned users/companies/audit events to zero.
