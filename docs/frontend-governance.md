# Frontend Governance

- Use named routes and centralized navigation metadata; never hard-code full URLs across templates.
- Menu visibility may reflect permissions but never replaces controller/policy/middleware authorization.
- Use semantic buttons for actions and accordion toggles. Every icon-only control needs an accessible name; decorative icons are hidden from assistive technology.
- Every form control needs an explicit or wrapping label; required/error/help state must be textual and associated where the page owns those messages.
- Use `<thead>`, `<th scope="col">` and a keyboard-focusable `.table-scroll` region. Add a domain-specific caption or nearby accessible heading when editing a table.
- Never remove focus outlines without a visible replacement. Respect reduced motion and a practical 44px touch target.
- Keep empty/error states actionable without stack traces. Labels and ARIA metadata must obey data-protection rules.
- Exact 1280×800 and 390×844 browser checks are required for shared-shell changes.
- Admin views MUST use a fluid application shell and fluid main content. Do not impose a global fixed `min-width` or content `max-width` that locks the whole page; grid and flex children that may shrink must use `min-width: 0` where required.
- Responsive coverage MUST include phone widths 320, 360, 375, 390 and 430 px, tablet widths 768 and 1024 px, and a desktop width of at least 1440 px. The same routes, authorization, data and primary actions must remain available at every supported viewport.
- Page-level horizontal overflow is prohibited. Wide tables and domain visualizations must use their own bounded, keyboard-focusable horizontal scroll region; they must not expand `body`, `.admin-shell` or the main content column.
- UI scale from 90% through 110% must remain usable without forcing horizontal page scroll. Prefer fluid primitives such as `width: 100%`, `max-width: 100%`, `minmax(0, 1fr)`, `clamp()` and content-driven breakpoints over fixed viewport assumptions.
- Large ERP workspaces should expand to use the available screen. Apply a readable line-length constraint only to prose that benefits from it; do not cap dashboards, forms or business tables with a global content maximum without a documented design reason.
- Shared layout changes require executable overflow checks for the page and bounded table containers, keyboard/focus checks, touch-target checks and browser console inspection. If a safe browser runtime is unavailable, report browser validation as `NOT_VERIFIED` rather than inferring PASS from static CSS.
- Full-page Laravel navigation must render the active item/group server-side. Pre-paint restoration may persist only a stable group key and session scroll offset; it must not cache menu HTML, permissions or user data. Initial restoration has no animation, while pagehide/pageshow handling keeps scroll stable and mobile drawer state resets closed.
