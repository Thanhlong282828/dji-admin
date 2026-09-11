# ERP process-flow convention

## Purpose

The visible process strip and contextual `?` help share one resolved `menuFlow`. The strip is presentation metadata only: it explains lineage and navigation, and never infers transaction completion or mutates an owner domain.

## Ownership

Definitions for important flows live beside their owning module:

- `Modules/Commerce/resources/process-flows.php`
- `Modules/Procurement/resources/process-flows.php`
- `Modules/Payroll/resources/process-flows.php`
- `Modules/Finance/resources/process-flows.php`
- equivalent files in Organization, Employee, Inventory, Project, Maintenance, Document, Workflow, Notification and Dashboard.

`App\Support\Navigation\ProcessFlowRegistry` is the shared read-only adapter. `AdminHelpRegistry` remains the route-to-help contract and supplies its resolved flow to both render surfaces. The renderer is `resources/views/components/admin-process-flow.blade.php`.

## Adding or changing a flow

1. Open the owning module's `resources/process-flows.php`.
2. Add an entry keyed by the canonical `AdminNavigation` item key.
3. Set `current` to exactly one node key.
4. Add ordered nodes. Use an existing navigation key when the node should deep-link. For an explanatory stage without a dedicated page, give it an explicit permission and leave it without a navigation destination.
5. Never invent a route merely to make a node clickable. Never represent a future node as completed.
6. Update focused route/current-path tests when business order changes.

Each node may provide `key`, `label`, `navigation`, `permission`, `icon` and `help`. Navigation metadata supplies the real named route, parameters and permission. The resolver classifies nodes before the current node as `upstream`, the selected node as `current`, and later nodes as `future`.

## Authorization

The layout filters every node with the same permission semantics as the sidebar. Unauthorized nodes and links are not rendered. A virtual contextual node must carry an explicit owner permission. Named routes are checked before a link is emitted.

## Motion and accessibility

The wordmark, active upstream connectors, current-node border, primary brand buttons and focused input border reuse the same metallic-gold gradient and duration tokens. Future connectors never animate. Danger, warning, success and information colors remain semantic.

`prefers-reduced-motion: reduce` disables brand, connector, button and focus movement while preserving position, border and contrast cues. Links retain keyboard `focus-visible` outlines. On narrow screens the component scrolls internally and does not enlarge the document viewport.

## Compatibility

Routes not yet migrated to a module-owned definition continue to use the established `AdminHelpRegistry` mapping. This preserves a specific guide for all destinations while important business flows move incrementally to their owner modules without duplicating Blade diagrams.
