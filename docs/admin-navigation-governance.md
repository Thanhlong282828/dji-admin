# Admin Navigation Governance

The canonical information architecture contains nine groups: Overview, Organization, People/HR Core, Talent, Payroll & Finance, Enterprise Automation, Access & Security, Operations/Platform and Content.

Operations owns Reliability, Observability, Performance, Releases, Runtime Configuration, Secrets, Data Protection, Business Continuity, Security Assurance and API Documentation. A page may be added only when its named route exists and permission metadata is defined. Item/group keys and named routes must remain unique.

The active route's group is always expanded on initial render, even when saved UI state says it was closed. Client persistence stores at most one non-sensitive group key, so the sidebar behaves as a compact accordion. Search temporarily opens matching groups and clearing it restores the active-route/saved state. Navigation generation must remain query-free beyond the already authenticated user's cached authorization model.

Permissions are filtered server-side: children are removed first and any group with no permitted children is omitted from the HTML. The nine operations surfaces require `operations.view`; API Documentation independently requires `api.documentation.view`. JavaScript owns only interaction state and never authorization.

## Collapse hardening evidence

The original `.nav-group-items { display: grid }` author rule overrode the browser's `[hidden] { display: none }`, leaving collapsed children visible and in layout. The corrected contract applies grid only to `.nav-group-items:not([hidden])`, making closed children invisible and non-tabbable.

- Exact Phase 3F-18 desktop 1280×800: 46 permitted links in the DOM, one visible Dashboard child initially, one expanded group; Operations closed/open child counts are 0/9.
- Exact Phase 3F-18 mobile 390×844: drawer toggle, Operations 0/9/0 visible-child cycle, close-on-navigation and Escape focus return pass.
- Enter and Space toggle the parent button, `aria-expanded` stays synchronized, and collapsed children are not reached by Tab.
- Active Security Assurance routes auto-open Operations; search for `security` temporarily reveals only permitted matching navigation.

## API documentation hardening

API Documentation is the tenth Operations child. An actor with `operations.view` but without `api.documentation.view` receives no documentation link HTML. An actor with only `api.documentation.view` receives the Operations parent with that one child. Exact browser regression verifies Operations closed/open counts of 0/10 for the authorized fixture and preserves keyboard, active-route and mobile drawer behavior.
