# LaravelDji Permanent ERP Development Standards

## 1. Scope and precedence

This standard applies to every future ERP module, feature, administrative workspace, route, database workflow, API, background job and UI/UX change.

It supplements rather than replaces the repository's specialized governance. Before work begins, follow `AGENTS.md` and read every referenced architecture, security, database, environment, testing and release document that applies to the approved scope. If two rules conflict, stop, identify the conflict and obtain an explicit decision before editing.

## 2. Future Feature Completeness Audit

Before every development batch, record the answers to all six questions below:

1. **Sidebar applicability:** Is this a user-facing administrative workspace that requires discovery through the canonical sidebar?
2. **Route and authorization:** Do the named route, server-side permission, middleware, Process Help entry and tenant/company/branch scope exist and agree?
3. **Real business source:** Does every displayed record, balance, metric and status come from canonical persisted business data through an authorized read path?
4. **Workflow integration:** Are validation, persistence, state transition, downstream effects and owning-module integrations implemented rather than implied?
5. **Design-system fit:** Does the UI reuse the established ERP Admin components, Dark/Gold tokens, interaction patterns and responsive/accessibility behavior?
6. **Verification:** Do tests prove the data flow, authorization, isolation and relevant state transitions?

Missing items are explicit gaps. Propose the smallest corrective scope and stop for approval when it exceeds the current batch. Never silently broaden scope, invent a dependency or describe an incomplete workflow as complete.

## 3. Canonical sidebar integration

- Every new user-facing administrative workspace must be registered through `App\Support\Navigation\AdminNavigation` using its established definition and filtering mechanism.
- Classify it under the correct ERP domain and owning bounded context. Follow `docs/architecture/ERP-SIDEBAR-STANDARD.md` and generate `docs/architecture/ERP-NAVIGATION-MAP.md` through the canonical architecture-map workflow rather than hand-maintaining authoritative rows.
- Use a real named route, an existing or deliberately approved permission, correct route patterns, company/branch parameters and Process Help metadata. Preserve active states, breadcrumbs and source traceability.
- Preserve server-side permission filtering and tenant/company/branch context. A hidden menu item is not a substitute for route, Policy or Gate authorization.
- Never invent a route, module, permission, page or empty navigation group merely to populate the sidebar. Never link to an unimplemented page.
- A workflow intentionally reached through a parent workspace remains there unless an approved UX requirement creates a separate canonical workspace.
- Backend-only services, internal APIs, jobs, events, commands and infrastructure tooling do not need sidebar entries unless they expose a genuine user-facing administrative workspace.
- New or changed workspaces require navigation contract tests covering route existence, permission visibility, active state and applicable company/branch isolation.

## 4. Real business data and honest empty states

- Never fabricate business records, balances, transactions, counts, charts, revenue, inventory, payroll, tax or operational activity to make a page appear populated.
- Never add production-facing hardcoded statistics, fake seeders or demo rows to operational environments. Synthetic fixtures are allowed only in isolated tests and must never be represented as real activity. Do not import production data into tests.
- Read through the owning module's canonical database tables, query handlers, read models or services with applicable authorization and tenant scope.
- Existing menus with no data must be audited and classified as one of:
  1. no real records exist yet;
  2. the read path is not connected to its canonical source;
  3. the legitimate data-creation workflow is missing; or
  4. the capability is incomplete or configuration-blocked.
- If no real records exist, render a truthful empty state that explains the source workflow and offers an authorized CTA to the legitimate creation or processing step when one exists.
- Do not create finance, tax, inventory, HR, payroll or other transactions merely to fill a UI. If the source workflow is missing, implement or approve that workflow before presenting derived operational data.
- Preserve lineage from displayed aggregates and references back to their canonical source. A legitimate zero or empty result is not a defect.

## 5. End-to-end business workflows

- Every business capability must identify its source, validation, persistence owner, allowed state transitions and downstream effects.
- Respect bounded-context ownership and existing CQRS, Command/Query handlers, application services, domain services, events, observers and queues. Do not duplicate ownership across modules.
- Controllers and Blade templates orchestrate requests and render view models; they do not become alternate business or persistence layers.
- Use database transactions where multiple writes form one atomic business action. Use durable idempotency keys and deterministic duplicate handling where retries or repeated requests are possible.
- Preserve maker-checker approval, immutable snapshots, accounting evidence, audit logs and historical records wherever the domain requires them.
- Cross-module integration must use established contracts and retain tenant/company/branch scope and source references.
- Do not build disconnected CRUD pages that imply a complete operational workflow. Clearly label capabilities that remain incomplete, configuration-blocked or intentionally read-only.

## 6. ERP Admin UI/UX and branding

- Preserve the established **Dark/Gold ERP Admin** identity. White remains appropriate for text, content and existing light components; it is not authorization to redesign admin surfaces into a new light theme.
- Reuse existing CSS variables, design tokens, Blade components, typography, spacing, borders, panels, tables, status treatments and interaction patterns.
- Inspect the actual logo and current motion implementation before adding animation. Reuse the established gold animation, gradient, glow and timing tokens; do not create a visually unrelated effect.
- Use `#EAB308` only where it is the verified existing token or component contract. Do not introduce a parallel palette.
- Do not change public storefront colors or components when they use a separate established design system.
- Motion must be restrained and performant, and every new motion effect must provide behavior compatible with `prefers-reduced-motion`.
- Maintain accessible contrast, readable text, semantic structure, keyboard navigation, visible focus and usable targets.
- Design and verify responsive behavior for desktop, tablet and mobile without hiding required actions, covering content or breaking scroll behavior.
- Handle applicable loading, truthful empty, success, validation-error, authorization-denied, disabled/pending and failure states.

## 7. Security and data integrity

- Enforce authentication and authorization server-side through the existing middleware, Gate, Policy and Spatie Permission architecture.
- Preserve tenant/company/branch isolation in every query, command, route binding, cache key, background job and exported artifact.
- Prevent IDOR, mass assignment, SQL injection, XSS, unsafe file access and unbounded queries through validated inputs, explicit allowlists, safe bindings and pagination.
- Preserve database/environment safety guards. Never weaken them to make a test or workflow pass.
- Do not expose secrets, signed URLs, internal authorization decisions or privileged configuration through HTML, JavaScript, logs, fixtures or documentation.
- Financial, inventory, HR, payroll and tax writes must preserve transactions, approval controls, idempotency and audit evidence required by their owning domain.

## 8. Testing and acceptance evidence

For every user-facing feature, evaluate and verify all applicable items:

- Canonical sidebar discovery and correct ERP domain.
- Named route existence and server-side authorization.
- Permission filtering and tenant/company/branch isolation.
- Real database-backed data flow and source traceability.
- Honest behavior when no records exist.
- Positive, denial, invalid-state and rollback/failure paths.
- End-to-end business state transition and downstream effects.
- No fabricated dashboard statistics or operational records.
- Dark/Gold UI consistency, responsive layouts, keyboard access, focus visibility and reduced-motion behavior.
- Focused unit/feature/contract/concurrency tests as appropriate.
- PHPStan Level 10 across both `app` and `Modules`, Pint and relevant frontend/Blade/build checks.

Never claim PASS for a command or scenario that was not actually executed. Historical evidence may inform scope but does not certify the current source, environment or release.

## 9. Bounded development and preservation

- Audit first, declare the exact file manifest, implement second and validate third.
- Preserve all existing uncommitted user work. Do not reset, restore, clean, stash, overwrite or otherwise absorb unrelated changes.
- Do not silently broaden scope. Report missing dependencies and request approval before touching additional files or changing a business contract.
- Do not run staging/production migrations, restore, deploy, publish or release without explicit approval and the applicable governance gates.
- Do not change an existing phase classification or release closeout merely because a local implementation or focused test passes.

## 10. Definition of Done

A user-facing business feature is complete only when:

1. Its real business workflow and owning-module integration are implemented.
2. Persistence, state transitions and required downstream effects are verified.
3. Authorization and tenant/company/branch isolation are tested.
4. The UI reads canonical data and handles truthful empty and failure states.
5. Applicable canonical sidebar, breadcrumb, Process Help and navigation-map integration is complete.
6. The existing design system, accessibility and responsive conventions are respected.
7. Appropriate automated and static gates pass with current evidence.
8. No unrelated regression or unapproved scope expansion is introduced.

A backend-only capability may be complete without a sidebar entry, but it must still have explicit integration, security, observability and verification criteria. A CRUD screen or UI shell without its real workflow is not complete.
