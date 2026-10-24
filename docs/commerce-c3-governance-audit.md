# Commerce C3 governance audit

## Verified baseline

- Branch access already uses `branches` and `branch_user_access`; the missing domain fact was a canonical leader relation.
- Platform roles, company access, branch access, Employee position, Seller status and Store ownership are independent authorization dimensions.
- Seller governance already enforces `pending → verified → active/suspended/banned`; no replacement state machine is needed.
- Enterprise compensation analytics reads effective salary-component assignments, but local components lacked canonical `system_key`/allowance classification.
- Unified media previously applied one 8 MB / 6000 px limit to every image purpose.

## Decisions

- Add only `branches.leader_employee_id`, referencing Employee. Name and position remain derived from Employee's current primary assignment.
- Branch create/update requires both route permission and platform role `Super Admin` or `System Admin`.
- Role assignment uses a centralized explicit rank map; career level 1–6 remains a separate workforce hierarchy.
- Article cover/gallery use 15 MB, 8192×8192 and a 67,108,864-pixel ceiling; avatar/logo remain bounded separately.
- Compensation analytics remains tenant-scoped, effective-dated and server-side permission filtered.
