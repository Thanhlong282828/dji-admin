# Enterprise Data Quality Audit

Phase 3F-24 audits existing Company, Organization, Employee, Attendance, Payroll and IAM relationships without introducing a second master-data store. The canonical sources remain `employees`, current `employee_assignments`, `users`, `company_user_access`, `attendance_records` and Payroll-owned input/item tables.

## Evidence-backed gaps

- Employee schema already supports user linkage, DOB, gender, controlled avatar metadata, manager hierarchy and soft deletion. The list/form did not expose those fields professionally and used a raw manager ID.
- Manager self-reference and cycles were already rejected by the Employee write repository and assignment service.
- Promotion, transfer and immutable career/lifecycle history already existed, but position rank and a distinct demotion event were missing. Phase 3F-24 adds an explicit numeric career level and validates promotion/demotion direction without inferring it from titles.
- Attendance already owns capture, approval, shift/calendar management and realtime events. Phase 3F-24 composes those boundaries into a server-filtered operational table, real day/week chart and tenant-scoped streaming XLSX export; adjustment remains the canonical edit path and records are not hard-deleted.
- Cross-domain quality evidence was fragmented; no aggregate read-only CLI/dashboard existed.

Root cause of the nine duplicate current primary assignments was an unstable `today()->subYears(3)` key in the synthetic Employee bootstrap. Annual reruns changed `effective_from` and inserted another current primary row. The correction uses a fixed synthetic join-date anchor and expires only redundant synthetic current assignments, retaining history. The final 13-rule audit reports zero duplicate, orphan, tenant, position/unit, manager, Attendance and Payroll findings.

No VPS action, destructive correction or automatic repair is part of this audit.

## Final Employee runtime hardening

The Employee form rendered Organization Unit and Job Position, but the original write path excluded both values from `CreateEmployeeCommand` without creating the canonical assignment. The form also required those values while legacy/API-compatible creates did not supply them. The corrected controller keeps Employee profile persistence separate, then creates or repairs the current primary assignment through `EmployeeLifecycleService` inside the same transaction. Unit/position, company, manager and position-unit validation now returns field-specific errors. The Employee list, Employee 360 and lifecycle list all read the same effective primary assignment; assignment events invalidate the scoped Employee and Organization read versions.

Manual Employee creation now appends the hired/onboarding/active lifecycle, initial primary assignment and initial position/manager history where applicable. Promotion and demotion use all database positions above or below the current `career_level`, filtered by the selected Organization Unit. Both transitions replace the effective assignment and immediately update every current-assignment read model. A process race still permits one lifecycle initializer only; a later repair with explicit assignment data may initialize a genuinely missing assignment without duplicating lifecycle history.

The Cloudflare Images adapter was present, but local configuration explicitly selected the private-local driver. A live diagnostic with the configured external account reached the Cloudflare Images API and returned HTTP 403 / code 5403 (`account is not valid or authorized`), so live Cloudflare is **not verified** and no asset was created. The adapter itself is covered with mocked upload/delete/API-failure tests, SecretProvider resolution, secret-redacted errors, replace-after-commit cleanup and database-failure compensation. Local remains explicitly selected; it is never reported as a Cloudflare success.
