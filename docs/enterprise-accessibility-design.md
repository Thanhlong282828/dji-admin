# Enterprise Accessibility Design

The admin shell uses semantic `<aside>`, `<nav aria-label="Điều hướng chính">`, grouped `<section>` landmarks and `<main id="main-content">`. A visible-on-focus skip link targets main content.

`AdminNavigation` is the centralized source for group/item keys, labels, named routes, route patterns, parameters and permissions. Backend route middleware remains authoritative. The service filters links through the current user's existing permissions and computes active state using named-route patterns; it never queries the database from Blade.

Desktop uses a nine-group accordion with at most the active group plus one user-selected group. Current route always wins over stored preference. Mobile uses a bounded drawer with overlay, accessible open/close buttons, Escape close, focus return, focus cycling and body-scroll control. Persisted state contains only group keys—no identity, permission or secret data.

Focus uses a high-contrast gold outline. Primary controls have a practical 44px minimum target. Reduced-motion preferences collapse animations and transitions. Status/error messages expose textual roles, not color alone.
