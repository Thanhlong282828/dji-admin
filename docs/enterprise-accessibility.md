# Enterprise Accessibility and UX Quality

Phase 3F-20 adds Test Quality to the exact-viewport navigation regression. It remains server-side permission filtered, participates in keyboard collapse/active state, and brings the fully permitted Operations group to 12 children.

Phase 3F-17 replaces the flat admin menu with a permission-aware grouped navigation shell and establishes WCAG 2.2 AA-oriented frontend governance. It adds keyboard/mobile drawer behavior, skip navigation, visible focus, reduced motion, touch targets, table enhancement and exact-viewport automation.

Phase 3F-26 final navigation hardening removes full-page accordion flicker: active state remains server-rendered, a no-animation pre-paint bootstrap restores one permission-valid group and session scroll position, and idempotent TypeScript reconciles BFCache without restoring the mobile drawer. Active items are revealed only when outside the menu viewport, avoiding unconditional centering and scroll jumps.

This is not a formal accessibility certification. Page-specific legacy form help/error associations, modal behavior and table captions remain governed incremental work whenever those pages are changed.

## Completion evidence

- Sidebar: nine groups, 45 live named-route items and eight consolidated Operations children; no disabled/dead placeholder links.
- Focused tests: 14 tests / 199 assertions. Full PHPUnit: 446 tests, 445 passed, 4,708 assertions, one skipped, exit 0.
- PHPStan app/Modules and affected tests: level 10, zero errors. Pint, Blade, Vite, Composer/npm audits, secret scan and Docker configuration passed.
- Browser: 15 representative pages at exact 1280×800 and 390×844; overflow, duplicate IDs, console warnings/errors and page errors all zero. Enter, Escape, drawer focus return, skip link, active-group persistence and mobile close-on-navigation passed.
- Browser database cleanup: schema-only, zero users/companies/audit events, 61 migrations.
- No migration. All four environments remain at 61 migrations and 230 tables. Production counts remained unchanged.

## Sidebar completion hardening

Runtime DOM inspection found that the group `hidden` attribute was correct but an author-level `display: grid` rule kept its child container rendered. Grid is now applied only while the container is not hidden. Exact browser automation verifies actual computed visibility and layout: Dashboard starts with 1 of 45 navigation links visible, Operations has 0 children visible when closed and 8 when open, and only one group is expanded. Permission filtering occurs before Blade rendering; a user without `operations.view` receives neither the Operations parent nor Security Assurance HTML. Local development login (`admin@local.test`) verified Dashboard, Employees and Security Assurance at HTTP 200 with zero overflow or console problems.

Hardening gate: 15 focused/relevant tests, 14 passed, 1 skipped, 1,415 assertions; PHPStan level 10 zero errors; Pint, Blade and Vite pass. Phase 3F-18 adds Performance as the ninth Operations child; exact 1280×800 and 390×844 regression checks cover 16 pages with zero overflow, console warnings/errors or page errors.

Swagger completion hardening adds API Documentation as the tenth Operations child under its own server-side `api.documentation.view` permission. Exact desktop/mobile automation verifies the 0/10 collapse cycle, Swagger UI and protected specification navigation, zero overflow and zero console/page problems.

The final project audit closes the vendor-layout gap with an admin-layout API Documentation landing. All 78 primary destinations now expose the contextual-help trigger and shared navigation semantics; Swagger UI/OpenAPI JSON remain protected child resources rather than pretending the vendor view is an admin shell.

Phase 3F-19 adds Architecture as the eleventh authorized Operations child. It retains server-side filtering, hidden/non-tabbable collapse, keyboard accordion, active-group auto-open and exact desktop/mobile regression coverage.
