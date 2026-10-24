# Commerce C3 governance design

`Branch → leader Employee → User + current primary assignment → Job Position` is the canonical leadership read path. Assigning a leader idempotently grants `branch_user_access`; replacing a leader never removes the old leader's legitimate access. Changes emit safe audit metadata containing identifiers only.

Employee 360 is the existing account-administration surface. Its account form uses a same-company multi-select and synchronizes only that company's rows in `branch_user_access`; it neither creates a duplicate access model nor removes another company's access.

Platform IAM role rank and Job Position career level are deliberately separate. Backend services enforce role ceilings; Blade only filters choices for usability. Seller governance remains permission-controlled and prevents self-governance.

Compensation aggregates derive Base, KPI and Allowance from active, effective salary assignments and calculate Target Gross from integer minor-unit averages. Article media is staged through the existing unified storage adapter; preview is browser-local and provider upload is compensated on database failure.
