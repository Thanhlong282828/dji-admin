# Overtime workflow

The internal workflow is **FUNCTIONAL**: policy and effective assignment management, tenant-scoped request draft/submit/approve/reject/cancel, attendance reconciliation, integer payable minutes, audit history, broadcast event and CQRS UI are connected. Approval never fabricates actual minutes; reconciliation requires approved attendance. Payroll snapshots consume payable minutes. No overtime amount or rate engine is claimed. See `docs/overtime-management-completion-after.md`.
