# Customer Visit and Checkout Flow

1. The authenticated actor is scoped to Company and a rendered Branch access record.
2. Check-in references the canonical Company Customer and Branch.
3. Open Visit lines reference canonical Company Service or Product Variant + Branch Inventory Location; names/SKU/prices are snapshotted for transaction history.
4. Checkout locks the Visit, refuses empty/already-closed mutation, and uses server-side totals.
5. Product lines atomically decrease inventory and append movement evidence.
6. One paid POS Receipt and one recognized revenue row are created in the same transaction.
7. Sales, Customer history, Branch Finance and Dashboard read these same records.

Customer/Service delete is allowed only before usage. Once referenced, records must be made inactive so history remains intact.
