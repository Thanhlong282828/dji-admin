# ERP Admin Design System

## Direction

The admin interface uses a dark, high-density enterprise shell. White → ivory → bright gold → metallic gold is a selective brand accent, never a replacement for semantic success, warning, danger, or information colors.

## Central contracts

- Semantic CSS tokens live in `resources/css/app.css` under the `--erp-*` namespace.
- Shared Blade primitives live in `resources/views/components/erp/`: page header, KPI card, and status badge.
- Navigation state, drawer focus, ARIA expansion, search, dialogs, and table accessibility stay centralized in `resources/ts/admin-navigation.ts`.
- Existing page-specific styles remain temporarily compatible. They are removed only after references reach zero.

## Sidebar rule

Every completed operator-facing capability must be reachable from canonical Sidebar navigation unless it is intentionally embedded as a sub-workspace or action of another reachable capability. Backend-only services, jobs, handlers, listeners, and internal APIs do not require menu entries.

## Accessibility

Gold text has a solid-color fallback. Semantic red/green/blue states remain distinct. Focus rings, `aria-current`, `aria-expanded`, mobile focus trapping, 44px primary controls, intentional table scrollers, and reduced-motion preferences are preserved.
