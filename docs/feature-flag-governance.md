# Feature Flag Governance

Every flag has a typed key, owner, risk, deterministic default, failure mode and removal condition. Flags must not encode salary, tax, insurance, Payroll calculations, Workflow rules or authorization semantics.

Risk policy:

- low: deploy-reviewed environment change;
- medium: documented reason and operational verification;
- high: maker-checker policy for production once a multi-operator persistent store exists.

Kill switches prevent new side effects, never fake success, delete pending intent or rewrite history. Restoration allows the owning idempotent retry flow to proceed once. Stale flags are reviewed through `operations:config-audit`; removal requires proof that all evaluation sites and environment declarations are gone.

Final matrix: low-risk operations UI is fail-open to its safe environment default; external delivery, email, broadcast, realtime, export and privileged-access controls are fail-closed. Business policy never enters the generic evaluator.
