# Recruitment realtime, notification and panel summary audit

Audit date: 2026-07-24.

| Feature | Current implementation | Status | Gap |
|---|---|---|---|
| Backend broadcast | `RecruitmentWorkflowChanged`, private company channel, safe payload | FUNCTIONAL | Frontend subscriber absent |
| Echo dependency | Not present in `package.json` | MISSING | Install `laravel-echo` and `pusher-js` |
| Echo bootstrap | Organization code probes optional `window.Echo` | MISSING | Initialize and import before module listeners |
| Interview UI realtime | No DOM contract | MISSING | Add scoped reload endpoint/listener |
| Offer UI realtime | No DOM contract | MISSING | Add scoped reload endpoint/listener |
| Panel summary | Raw evaluations loaded on detail | PARTIAL | Typed aggregate/query/ViewModel/UI |
| Notifications | No recipient resolver or matrix | MISSING | Typed resolver, queued notifications and listeners |
| External calendar | No credentials/provider | NOT_CONNECTED | Keep internal scheduling independent |

