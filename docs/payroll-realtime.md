# Payroll realtime fragments

Payroll Run detail renders six shared Blade partials for both the initial page and authorized HTTP refreshes: summary, readiness, items, validation, history and actions. `PayrollFragmentController::show()` verifies company access and `PayrollRunPolicy::view`, dispatches `GetPayrollRunDetailQuery`, then renders the selected allowlisted partial.

`PayrollRunChanged` broadcasts only `company_id`, entity/run ID, action, status, record/calculation versions, timestamp and refresh scopes on `private-company.{companyId}`. It never broadcasts payroll amounts or statutory/banking identifiers.

`resources/ts/realtime/workforce.ts` reuses the shared Echo instance, debounces each region by 250 ms, coalesces duplicates, aborts an older in-flight request, ignores foreign company/run and older versions, and reads refresh URLs only from the DOM. If realtime is unavailable, initial page rendering, mutation redirects and manual refresh continue to work. Automatic polling is not implemented. Live two-browser verification: `NOT_VERIFIED`.
Payroll Input additionally registers `payroll-input-list`, `payroll-input-summary`, `payroll-input-readiness`, `payroll-input-employees`, `payroll-input-validation`, `payroll-input-history` and `payroll-input-actions`. `WorkforcePayrollInputChanged` carries the safe scope allowlist through the same Echo instance.

Phase 3B-3 retains the six Run fragment scopes after Calculate/Submit/Approve/Reject/Finalize. Fragment requests independently authorize Company and Run; events contain no salary details. Source contract and fragment tests pass. Manual maker/approver sessions verified redirected UI state, but the isolated browser server used `BROADCAST_CONNECTION=null`; therefore live two-browser Reverb refresh remains `NOT_VERIFIED`.
# Phase 3B-4

Source capture continues to emit the existing tenant-safe Payroll Input refresh event. Employee paging/detail adds no salary or source payload to broadcasts. Live two-browser verification remains `NOT_VERIFIED`.
## Payment Batch event

`PaymentBatchChanged` broadcasts only company ID, batch ID, status, non-sensitive count, refresh scopes and timestamp on the private company channel. It excludes account, employee, amount, salary and storage data. Source contract is covered statically; live two-browser Reverb remains `NOT_VERIFIED`.
# Accounting Journal realtime contract

`AccountingJournalChanged` broadcasts on private `company.{companyId}` as `payroll.accounting-journal.changed`. Payload is limited to `company_id`, `journal_id`, `status`, `line_count`, `balanced`, safe refresh scopes and `updated_at`. The shared Echo listener uses company/entity filtering, 250 ms debounce and AbortController-driven refresh. Salary values, employee identity and private file metadata are excluded. Live two-browser verification: `NOT_VERIFIED`.
# Adjustment realtime

`PayrollAdjustmentChanged` broadcasts safe identifiers/status/refresh scopes on private `company.{companyId}`. Shared Echo uses 250 ms debounce and AbortController to refresh list/detail HTML. Amounts, employee names and evidence paths are excluded. Live two-browser Reverb: NOT_VERIFIED.

C3 browser verification used an isolated server with the log broadcaster to avoid coupling fixture creation to a live Reverb process. Desktop/mobile DOM refresh contracts and tenant-safe payload code remain verified by automated tests; live two-authenticated-session Reverb delivery is therefore still explicitly `NOT_VERIFIED`.
