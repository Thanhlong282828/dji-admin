# Frontend Governance

- Use named routes and centralized navigation metadata; never hard-code full URLs across templates.
- Menu visibility may reflect permissions but never replaces controller/policy/middleware authorization.
- Use semantic buttons for actions and accordion toggles. Every icon-only control needs an accessible name; decorative icons are hidden from assistive technology.
- Every form control needs an explicit or wrapping label; required/error/help state must be textual and associated where the page owns those messages.
- Use `<thead>`, `<th scope="col">` and a keyboard-focusable `.table-scroll` region. Add a domain-specific caption or nearby accessible heading when editing a table.
- Never remove focus outlines without a visible replacement. Respect reduced motion and a practical 44px touch target.
- Keep empty/error states actionable without stack traces. Labels and ARIA metadata must obey data-protection rules.
- Exact 1280×800 and 390×844 browser checks are required for shared-shell changes.
- Full-page Laravel navigation must render the active item/group server-side. Pre-paint restoration may persist only a stable group key and session scroll offset; it must not cache menu HTML, permissions or user data. Initial restoration has no animation, while pagehide/pageshow handling keeps scroll stable and mobile drawer state resets closed.
