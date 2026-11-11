# Eloquent Mass Assignment Hardening Audit — 2026-10-18

Evidence report for the isolated worktree `/private/tmp/laraveldji-mass-assignment` at baseline `c618a3468d1b6fc93f16291cdea21c80542a6524`.

## Inventory

- Eloquent models audited: 54
- Models converted from `$guarded` to explicit `$fillable`: 50
- Existing `$fillable` models retained: 3
- Attribute-based `#[Fillable]` models retained: 1 (`App\\Models\\User`)
- Remaining source `$guarded` declarations: 0

## Converted model allowlists

- `Modules\Attendance\Domain\Models\AttendanceDailyRecord`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Attendance\Domain\Models\AttendanceEvent`: `branch_id`, `company_id`, `created_by`, `deduplication_key`, `employee_id`, `event_type`, `local_date`, `occurred_at`, `source`
- `Modules\Attendance\Domain\Models\EmployeeWorkProfile`: `company_id`, `daily_minutes`, `effective_from`, `employee_id`, `fte_basis_points`, `timezone`, `weekly_minutes`, `work_calendar_id`
- `Modules\Attendance\Domain\Models\ShiftBreakRule`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Attendance\Domain\Models\WorkCalendar`: `code`, `company_id`, `effective_from`, `name`, `timezone`, `weekly_work_minutes`
- `Modules\Attendance\Domain\Models\WorkCalendarDay`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Attendance\Domain\Models\WorkShift`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Company\Domain\Models\Company`: `code`, `country_code`, `created_by`, `currency`, `currency_code`, `functional_currency_code`, `is_active`, `name`, `status`, `tax_code`, `timezone`, `updated_by`
- `Modules\Company\Domain\Models\OrganizationUnit`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Employee\Domain\Models\DataConsent`: `actor_id`, `decided_at`, `employee_id`, `expires_at`, `granted`, `policy_version`, `purpose`, `reason`
- `Modules\Employee\Domain\Models\Employee`: `avatar_storage_key`, `company_id`, `employee_code`, `employment_status`, `employment_type`, `full_name`, `identity_number`, `identity_number_hash`, `join_date`, `manager_id`, `nationality`, `official_date`, `personal_email`, `phone`, `source`, `source_application_id`, `source_candidate_id`, `source_offer_id`, `termination_date`, `user_id`, `work_email`
- `Modules\Employee\Domain\Models\EmployeeAssignment`: `allocation_percent`, `assignment_type`, `effective_from`, `effective_to`, `employee_id`, `job_position_id`, `manager_id`, `organization_unit_id`, `work_location`
- `Modules\Employee\Domain\Models\EmployeeCredential`: `employee_id`, `expires_at`, `issued_at`, `issuer`, `name`, `type`
- `Modules\Employee\Domain\Models\EmployeeDocument`: `access_policy`, `checksum`, `employee_id`, `mime_type`, `name`, `scan_status`, `size_bytes`, `storage_key`, `type`, `version`
- `Modules\Employee\Domain\Models\EmployeeDocumentHistory`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Employee\Domain\Models\EmployeeHistory`: `after`, `approved_by`, `before`, `effective_at`, `employee_id`, `event_type`, `reason`
- `Modules\Employee\Domain\Models\EmployeeLifecycleEvent`: `company_id`, `correlation_id`, `created_by`, `effective_at`, `employee_id`, `event_type`, `from_state`, `idempotency_key`, `payload_checksum`, `to_state`
- `Modules\Employee\Domain\Models\EmployeeManagerHistory`: `checksum`, `company_id`, `effective_from`, `employee_id`, `manager_id`
- `Modules\Employee\Domain\Models\EmployeePositionHistory`: `checksum`, `company_id`, `effective_from`, `employee_id`, `organization_unit_id`, `position_id`
- `Modules\Employee\Domain\Models\EmploymentContract`: `base_salary`, `contract_number`, `currency`, `employee_id`, `ends_at`, `starts_at`, `status`, `source_offer_id`, `terms`, `type`
- `Modules\Employee\Domain\Models\EmploymentTransition`: `approved_by`, `checksum`, `company_id`, `effective_at`, `employee_id`, `from_state`, `reason`, `to_state`, `transition_type`
- `Modules\Leave\Domain\Models\LeavePolicy`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Leave\Domain\Models\LeaveRequest`: `company_id`, `days`, `employee_id`, `ends_at`, `idempotency_key`, `leave_policy_id`, `leave_type_id`, `reason`, `requested_minutes`, `starts_at`, `status`, `timezone`
- `Modules\Onboarding\Domain\Models\OnboardingCase`: `application_id`, `buddy_id`, `checklist`, `company_id`, `created_by`, `employee_id`, `job_position_id`, `offer_id`, `organization_unit_id`, `owner_user_id`, `probation_review_date`, `provisioning`, `requisition_id`, `start_date`, `status`, `template_code`, `version`
- `Modules\Onboarding\Domain\Models\OnboardingChecklistItem`: `category`, `code`, `company_id`, `description`, `due_at`, `is_required`, `metadata`, `sort_order`, `status`, `title`
- `Modules\Onboarding\Domain\Models\OnboardingProvisioningItem`: `code`, `company_id`, `due_at`, `is_required`, `metadata`, `provider`, `provider_status`, `status`, `title`, `type`
- `Modules\Organization\Domain\Models\CostCenter`: `annual_budget`, `code`, `company_id`, `currency`, `name`
- `Modules\Organization\Domain\Models\EmployeeAssignment`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Organization\Domain\Models\JobPosition`: `approved_headcount`, `budgeted_cost`, `career_level`, `code`, `company_id`, `competency_requirements`, `is_critical`, `job_grade`, `organization_unit_id`, `title`
- `Modules\Organization\Domain\Models\OrganizationUnit`: `code`, `company_id`, `cost_center_id`, `effective_from`, `effective_to`, `headcount_limit`, `metadata`, `name`, `parent_id`, `type`
- `Modules\Payroll\Domain\Models\EmployeeSalaryComponentAssignment`: `amount_minor`, `company_id`, `created_by`, `currency_code`, `effective_from`, `effective_to`, `employee_id`, `idempotency_key`, `percentage_basis_points`, `quantity_basis_points`, `rate_minor`, `salary_component_id`, `source`, `status`, `updated_by`, `version`
- `Modules\Payroll\Domain\Models\PayrollAnnualTaxReconciliation`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Payroll\Domain\Models\PayrollInputSnapshot`: `company_id`, `approved_overtime_minutes`, `attendance_record_count`, `blocking_issue_count`, `employee_count`, `generated_at`, `generated_by`, `idempotency_key`, `leave_record_count`, `locked_at`, `note`, `overtime_record_count`, `paid_leave_minutes`, `payroll_period_id`, `scheduled_minutes`, `snapshot_version`, `status`, `superseded_at`, `superseded_by_snapshot_id`, `unpaid_leave_minutes`, `validated_at`, `warning_issue_count`, `worked_minutes`
- `Modules\Payroll\Domain\Models\PayrollItem`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Payroll\Domain\Models\PayrollPeriod`: `code`, `company_id`, `created_by`, `cutoff_at`, `idempotency_key`, `name`, `note`, `payment_date`, `period_end`, `period_start`, `status`, `version`
- `Modules\Payroll\Domain\Models\PayrollRun`: `code`, `calculated_at`, `calculation_version`, `company_id`, `created_by`, `currency_code`, `deduction_total`, `deduction_total_minor`, `employee_count`, `employer_contribution_total_minor`, `formula_version`, `finalized_at`, `finalized_by`, `gross_total`, `gross_total_minor`, `idempotency_key`, `insurance_employee_total_minor`, `insurance_employer_total_minor`, `net_after_personal_income_tax_total_minor`, `net_total`, `net_total_minor`, `payroll_input_snapshot_id`, `payroll_period_id`, `personal_income_tax_total_minor`, `period_end`, `period_start`, `approved_at`, `approved_by`, `rejected_at`, `rejected_by`, `run_number`, `status`, `submitted_at`, `submitted_by`, `version`
- `Modules\Payroll\Domain\Models\SalaryComponent`: `affects_gross`, `affects_net`, `calculation_type`, `category`, `code`, `company_id`, `created_by`, `currency_code`, `default_percentage_basis_points`, `default_value_minor`, `description`, `display_order`, `effective_from`, `effective_to`, `insurance_status`, `is_system`, `name`, `status`, `system_key`, `taxable_status`, `updated_by`, `value_type`, `version`
- `Modules\Recruitment\Domain\Models\Application`: `candidate_id`, `hired_at`, `hired_employee_id`, `hiring_reference`, `recruitment_requisition_id`, `stage`, `stage_changed_at`, `stage_reason`, `version`
- `Modules\Recruitment\Domain\Models\ApplicationInterview`: `application_id`, `company_id`, `ends_at`, `instructions`, `interview_type`, `location`, `meeting_url`, `round_number`, `scheduled_by`, `starts_at`, `status`, `timezone`, `title`, `version`
- `Modules\Recruitment\Domain\Models\ApplicationInterviewer`: `application_interview_id`, `company_id`, `role`, `user_id`
- `Modules\Recruitment\Domain\Models\Candidate`: `company_id`, `consent`, `email`, `full_name`, `hired_application_id`, `hired_at`, `hired_employee_id`, `phone`, `profile`, `profile_fingerprint`, `source`, `status`
- `Modules\Recruitment\Domain\Models\CandidateOffer`: `accepted_at`, `application_id`, `base_salary`, `company_id`, `currency`, `employment_type`, `expires_at`, `hired_at`, `hired_employee_id`, `job_position_id`, `offer_number`, `organization_unit_id`, `probation_end_date`, `reservation_status`, `reserved_at`, `start_date`, `status`, `submitted_by`, `terms`, `version`
- `Modules\Recruitment\Domain\Models\HireTransactionMarker`: `actor_id`, `company_id`, `completed_at`, `employee_id`, `idempotency_key`, `onboarding_case_id`, `offer_id`, `status`
- `Modules\Recruitment\Domain\Models\InterviewEvaluation`: `application_interview_id`, `company_id`, `concerns`, `interviewer_user_id`, `notes`, `overall_score`, `recommendation`, `scorecard`, `strengths`, `submitted_at`
- `Modules\Recruitment\Domain\Models\RecruitmentRequisition`: `approval_context`, `budget`, `filled_hires`, `job_position_id`, `requested_by`, `status`, `target_date`, `vacancies`, `version`
- `Modules\Workflow\Domain\Models\WorkflowDefinition`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Workflow\Domain\Models\WorkflowDefinitionVersion`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Workflow\Domain\Models\WorkflowInstance`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Workflow\Domain\Models\WorkflowStep`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_
- `Modules\Workflow\Domain\Models\WorkflowTask`: _empty — query-only Eloquent model; canonical writes use scoped query-builder/service paths_

## Verification evidence

- Database preflight: `APP_ENV=testing`; configured and actual PDO database both `schema_admin_testing`.
- New mass-assignment security suite: 53 tests, 562 assertions, PASS after final formatting.
- Final focused security and regression: 68 tests, 721 assertions, PASS.
- Final bounded-context regression after all four contract corrections: 226 tests, 226 passed, 2,034 assertions, exit code 0 (459.797 seconds).
- Pint: PASS.
- PHP syntax: PASS.
- Secret scan: PASS.
- `git diff --check`: PASS.
- PHPStan tooling diagnosis: the Composer PHAR launcher under the local Herd PHP runtime exited 1 without diagnostics. Running the same PHPStan 2.2.5 payload from an extracted temporary PHAR with a worktree-specific `tmpDir` removed that tooling blocker.
- PHPStan level 10 `app`: PASS, 0 errors.
- PHPStan level 10 changed `$fillable` model/service scope: PASS, 0 errors. Attendance, Company, Employee, Leave, Onboarding, Organization, Payroll, Recruitment, and Workflow model refactor files introduce no PHPStan finding.
- Mandatory PHPStan level 10 `app + Modules`: FAIL, 202 pre-existing out-of-scope findings remain in Commerce (10), Document (21), Employee module config (8), Finance (85), Inventory (5), POS (1), Payroll Process Help (2), Procurement (12), and Tax (58). No baseline, ignore rule, scope exclusion, or level reduction was added.

## Residual review

- The mandatory repository-wide PHPStan gate is not green because the isolated worktree starts from `c618a3468d1b6fc93f16291cdea21c80542a6524`, which predates the separate Phase 2B 213-to-0 PHPStan remediation. These unrelated findings were not copied into or repaired in this isolated refactor.
- Existing intentional `forceFill()` call sites outside this conversion remain: 14 calls, with no `forceCreate()` and no `Model::unguard()`. They are privileged internal command, fixture, identity, functional-currency, and payroll-period state-transition paths and require a separate contract review; this refactor added none.
- No commit, push, deployment, migration, staging, production, or Supabase access occurred.
