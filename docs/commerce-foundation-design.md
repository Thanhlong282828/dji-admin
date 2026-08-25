# Commerce C1/C2 foundation design

## Domain boundaries

```text
Company -> Branch -> branch_user_access
Company -> Organization Unit -> Job Position -> Employee Assignment (unchanged)

User -> Buyer Profile
User -> Seller Profile -> Store

Article / Store / Employee avatar
  -> MediaService -> MediaStorageInterface
  -> CloudflareImagesStorage | LocalMediaStorage
  -> media_assets
```

Branch is a physical/authorization scope. Organization Unit remains the HR reporting hierarchy. C1/C2 intentionally does not infer that every Unit belongs to exactly one Branch.

## Media contract

Upload validates MIME, dimensions and size, uploads the provider object, persists the owner reference in the business transaction, and compensates the new object if persistence fails.

Replace uploads new, commits the new reference, then deletes old owned assets. Cleanup failure is logged with a reference hash and does not invalidate committed business data. Delete removes ownership first and calls the provider only when no other media row references that asset. Archive retains media.

Local/browser storage requires explicit `MEDIA_DRIVER=local`; production does not silently fall back when Cloudflare fails.

## Authorization and UX

- Branch uses `branch.*` plus company/branch scope.
- Seller Center derives Seller from authenticated User, preventing owner-ID tampering.
- Seller Governance uses `commerce.seller.governance.*` and a bounded transition map.
- Store media follows Store permission; no generic media-delete bypass exists.
- Article uses existing `article.*` permissions and company access.
- Article supports cover plus 12 gallery images per request. Blob preview has no upload side effect; body never embeds Base64.
- Public navigation exposes only implemented Home, Articles, Stores and authentication/control-center links in a white/blue visual system.

Product, Order, Cart, Inventory, Warehouse and transactional commerce remain deferred. Branch-to-Employee assignment and Seller Workflow approval require a future explicit authority/invariant; no placeholder route/table is created.

Article legacy rows with no company owner remain public when published, but their admin mutation is restricted to `organization.tenant-all`. Creating or publishing an Article requires verified authentication, company access and the existing Article capability; `article.publish` is checked separately from create/update. Branch, Store and Article mutations emit safe identifier-only activity evidence.
