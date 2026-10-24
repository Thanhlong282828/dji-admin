# Production read-only smoke test

Automated anonymous smoke checks HTTPS login, `/health/live` and `/health/ready` through `scripts/operations/postdeploy-smoke.sh`. Authenticated manual smoke uses an externally supplied account/session and checks Dashboard, Employee, Attendance, Payroll, IAM, Audit, Analytics, Forecasting, Planning, Planning Execution, Reports, Go-Live Readiness and protected Swagger.

The default smoke performs no writes. A controlled write smoke is staging-only, must use disposable fixtures and must clean them. Never embed credentials in the script or repository.
