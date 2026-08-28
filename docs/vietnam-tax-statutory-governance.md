# Vietnam tax and statutory insurance governance

The project reuses the existing effective-dated PIT and statutory insurance engines. It does not create a second calculator and does not apply the newest policy retroactively. Payroll resolves a policy at the run period date and stores policy id/version on each Payroll Item.

Rules:

- Money uses integer minor units; percentage rates use basis points.
- PIT, BHXH, BHYT and BHTN remain separate component lines.
- Finalized Payroll Items/Runs are immutable.
- A legal source, effective date and internal version are mandatory before enabling a rate.
- VAT/CIT currently have no executable calculator or filing page. Branch P&L is not taxable income and no CIT estimate is emitted.
- Local Government Filing is mock/local only; external government traffic remains disabled.

The source register is governance evidence, not legal certification. Implementation must be re-reviewed when subordinate regulations or amendments change.
