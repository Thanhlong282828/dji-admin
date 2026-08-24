# Admin Navigation Governance

Phase 3F-25 keeps one Analytics destination and moves it beside Dashboard in the collapsed `Tổng quan` group as `Enterprise Analytics`. It reuses `admin.analytics.workforce` and `analytics.view`; no flat report links or disabled Report module are introduced. Contextual help describes the source/correction flow and separately notes `payroll.assignment.view` and `analytics.export`.

Phase 3F-24 adds permission-filtered, read-only `Data Quality`. Fully permitted Operations now has 15 children. Data Quality requires `operations.view`, retains collapse/active-route behavior and exposes no mutation. API Documentation remains independently filtered by `api.documentation.view`.

The canonical information architecture contains ten groups: Overview, Organization, People/HR Core, Workforce, Talent, Payroll & Finance, Enterprise Automation, Access & Security, Operations/Platform and Content. Phase 3F-26 has 74 server-filtered primary destinations, including Enterprise Analytics and Workforce Forecasting in Overview; Operations has 15 children.

Operations owns Reliability, Observability, Performance, Architecture, Releases, Runtime Configuration, Secrets, Data Protection, Business Continuity, Security Assurance and API Documentation. A page may be added only when its named route exists and permission metadata is defined. Item/group keys and named routes must remain unique.

The active route's group is always expanded in server markup and takes precedence over saved UI state. A tiny pre-paint bootstrap reads only one non-sensitive group key from `localStorage` and menu `scrollTop` from `sessionStorage`, validates the key against permission-filtered rendered groups, applies state before the first visible paint, and disables initial transition animation. `admin-navigation.ts` then owns idempotent interaction, pagehide persistence, BFCache pageshow reconciliation and minimal active-item reveal. Search remains temporary; mobile drawer visibility is never persisted.

Permissions are filtered server-side: children are removed first and any group with no permitted children is omitted from the HTML. The nine operations surfaces require `operations.view`; API Documentation independently requires `api.documentation.view`. JavaScript owns only interaction state and never authorization.

## Collapse hardening evidence

The original `.nav-group-items { display: grid }` author rule overrode the browser's `[hidden] { display: none }`, leaving collapsed children visible and in layout. The corrected contract applies grid only to `.nav-group-items:not([hidden])`, making closed children invisible and non-tabbable.

- Exact Phase 3F-18 desktop 1280×800: 46 permitted links in the DOM, one visible Dashboard child initially, one expanded group; Operations closed/open child counts are 0/9.
- Exact Phase 3F-18 mobile 390×844: drawer toggle, Operations 0/9/0 visible-child cycle, close-on-navigation and Escape focus return pass.
- Enter and Space toggle the parent button, `aria-expanded` stays synchronized, and collapsed children are not reached by Tab.
- Active Security Assurance routes auto-open Operations; search for `security` temporarily reveals only permitted matching navigation.

## API documentation hardening

API Documentation is the tenth Operations child. An actor with `operations.view` but without `api.documentation.view` receives no documentation link HTML. An actor with only `api.documentation.view` receives the Operations parent with that one child. Exact browser regression verifies Operations closed/open counts of 0/10 for the authorized fixture and preserves keyboard, active-route and mobile drawer behavior.

Architecture is the eleventh Operations child and requires `operations.view`. It does not alter API Documentation's independent permission behavior. The active Architecture route auto-opens Operations; collapsed children remain hidden and non-tabbable.
## 2026-08-21 complete work-center audit

Navigation now has 10 collapsed domain groups and 74 server-filtered primary destinations. Twenty-one links were added only after route/controller/view/permission evidence, and Phase 3F-26 adds Workforce Forecasting beside Enterprise Analytics without flattening reports. Detail/create/edit/export/download/mutation routes remain page actions. Integration and Audit child tabs remain inside their complete module hubs. Architecture tests compare every landing route permission boundary, route existence and unique item key; active patterns are disjoint for the separated work centers.
