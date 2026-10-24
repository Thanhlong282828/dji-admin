# Vietnam tax policy source register

Verified against the official Government legal-document portal on 2026-08-28.

| Type | Official source | Effective | Runtime status |
|---|---|---:|---|
| VAT / GTGT | [Law 48/2024/QH15](https://vanban.chinhphu.vn/?docid=212476&pageid=27160) and [amending Law 149/2025/QH15](https://vanban.chinhphu.vn/?classid=1&docid=216588&orggroupid=1&pageid=27160) | 2025-07-01; amendment 2026-01-01 | `CONFIGURATION_REQUIRED`; no VAT calculator is enabled |
| PIT / TNCN | [Law 109/2025/QH15](https://vanban.chinhphu.vn/?docid=216495&pageid=27160) | 2026-07-01 | Effective-dated configured engine exists; configured brackets/deductions require policy-level source evidence |
| CIT / TNDN | [Law 67/2025/QH15](https://vanban.chinhphu.vn/?docid=214607&pageid=27160) | 2025-10-01 | `CONFIGURATION_REQUIRED`; no CIT calculator/return is enabled |
| Social insurance / BHXH | [Law 41/2024/QH15](https://vanban.chinhphu.vn/?classid=1&docid=211199&orggroupid=1&pageid=27160) | 2025-07-01 | Effective-dated configured engine exists; rate/cap details remain configuration governed |
| BHYT/BHTN | No complete current subordinate-source set verified in this hardening | — | `NOT_VERIFIED`; do not silently introduce rates |

The table registers primary laws and verified effective dates only. It does not assert that every implementing decree/circular or temporary reduction has been incorporated. Each enabled policy row must retain its own effective period/version and traceable source reference in governed documentation/configuration.
