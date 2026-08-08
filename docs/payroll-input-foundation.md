# Payroll Input Foundation

Phase 3B-1 now links an exact locked input snapshot to Payroll Run calculations. Existing calculated runs never read Attendance/Leave/Overtime live or write results back to those domains.

Payroll periods progress through draft, collecting, review and locked. Generation rejects unresolved attendance anomalies, adjustments, leave or overtime. Snapshots capture approved attendance, approved paid/unpaid leave, payable overtime, active references and FTE. Lock freezes snapshots and attendance records. No gross/net salary, tax, insurance, bank transfer, payslip or filing is implemented.
