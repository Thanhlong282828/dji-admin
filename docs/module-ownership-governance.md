# Module Ownership Governance

| Concept | Owner | Permitted consumers |
|---|---|---|
| Company/tenant identity | Company | Organization and company-scoped modules read/reference it |
| Organization unit, position, tenant access | Organization | Employee, Payroll, Performance, Integration policies/read boundaries |
| Employee aggregate/lifecycle | Employee | Attendance, Leave, Recruitment, Payroll, Performance, IAM event consumers |
| Payroll calculation/result | Payroll | Integration/Audit consume safe intents/evidence; no external owner writes results |
| Workflow definition/instance/task | Workflow | Domain modules start/consume workflows; Workflow does not own their state |
| Notification message/channel delivery | Notification | Producers submit safe intent only |
| Entitlement/governance decision | IAM | Spatie adapter applies approved assignments; Employee does not grant IAM state |
| External delivery/receipt | Integration | ERP/Government prepare business payloads; Hub transports them |
| Audit event/snapshot/export | Audit | Source modules emit events and never write Audit tables directly |

Cross-module reads require company scope and a documented contract. Cross-module writes require a command/service contract or outbox consumer; direct mutation of another owner's aggregate is forbidden.
