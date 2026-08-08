# Payroll Inputs

Operational URLs:

- `GET /admin/payroll/inputs` — filtered snapshot list.
- `GET /admin/payroll/inputs/create` and `POST /admin/payroll/inputs` — immutable capture.
- `GET /admin/payroll/inputs/{snapshot}` — summary, readiness, employee rows, issues, history and actions.
- `POST .../validate` — validate persisted rows only.
- `POST .../versions` — recapture approved live sources into the next version.
- `POST .../create-run` — create a draft Payroll Run from a ready exact snapshot.

Permissions: `payroll-period.view`, `payroll-input.generate`, `payroll-input.validate`, `payroll-input.create-version`, `payroll.run.create`. The old `/admin/payroll-input` URL remains a compatibility redirect and contains no Article UI.

Period options are tenant-scoped open periods (`collecting`/`review`) with code, name and date range. Draft, locked, processed and foreign periods are not selectable.
