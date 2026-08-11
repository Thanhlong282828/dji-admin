# Admin Navigation Governance

The canonical information architecture contains nine groups: Overview, Organization, People/HR Core, Talent, Payroll & Finance, Enterprise Automation, Access & Security, Operations/Platform and Content.

Operations owns exactly Reliability, Observability, Releases, Runtime Configuration, Secrets, Data Protection, Business Continuity and Security Assurance. A page may be added only when its named route exists and permission metadata is defined. Item/group keys and named routes must remain unique.

The active route's group is always expanded on initial render, even when saved UI state says it was closed. Client persistence stores at most one non-sensitive group key, so the sidebar behaves as a compact accordion. Search temporarily opens matching groups and clearing it restores the active-route/saved state. Navigation generation must remain query-free beyond the already authenticated user's cached authorization model.

Permissions are filtered server-side: children are removed first and any group with no permitted children is omitted from the HTML. Operations additionally requires `operations.view`; without it both its parent button and all eight child links are absent. JavaScript owns only interaction state and never authorization.

## Collapse hardening evidence

The original `.nav-group-items { display: grid }` author rule overrode the browser's `[hidden] { display: none }`, leaving collapsed children visible and in layout. The corrected contract applies grid only to `.nav-group-items:not([hidden])`, making closed children invisible and non-tabbable.

- Exact desktop 1280×800: 45 permitted links in the DOM, one visible Dashboard child initially, one expanded group; Operations closed/open child counts are 0/8.
- Exact mobile 390×844: drawer toggle, Operations 0/8/0 visible-child cycle, close-on-navigation and Escape focus return pass.
- Enter and Space toggle the parent button, `aria-expanded` stays synchronized, and collapsed children are not reached by Tab.
- Active Security Assurance routes auto-open Operations; search for `security` temporarily reveals only permitted matching navigation.
