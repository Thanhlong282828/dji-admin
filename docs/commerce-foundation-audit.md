# Commerce C1/C2 foundation audit

Date: 2026-08-24. Scope: local repository and the three governed local databases only.

## Existing runtime truth

- `Modules/Article`, `ArticleCategory`, `Media`, `Product` and `ProductCategory` are disabled skeleton modules. Their providers/routes are not runtime authorities and were not enabled.
- The actual Article runtime is `App\Models\Article`, `App\Http\Controllers\ArticleController`, `StoreArticleRequest`, root web/API routes and `resources/views/admin/articles`.
- Article previously accepted only a required remote `hero_image` URL. The disabled module controller had empty mutations. This was the exact reason Article had no Cloudflare upload path.
- Employee avatar already had correct replace/compensation behavior, but its Cloudflare/local adapters were Employee-specific. Cloudflare credentials already used `SecretProviderInterface` and `secret://media/cloudflare-images`.
- Company tenant access is `company_user_access`; Organization Unit is an HR hierarchy node and is not a physical/authorization Branch.
- No Branch, Buyer Profile, Seller Profile, Store or shared media ownership tables existed. Additive persistence is therefore justified.

## Decisions

- Keep the disabled skeleton modules disabled. Implement the proven cross-cutting boundary under `app/Support/Media` and the existing root Article runtime.
- Add Branch as a company-owned entity plus explicit `branch_user_access`. Do not add `branch_id` to Employee or assignment until a governed Unit-to-Branch invariant exists.
- Keep User as authentication identity. Buyer and Seller are optional profiles; Seller business status is independent from Spatie role access.
- One Seller owns one or more Stores. Marketplace Store is not coupled to HR Company in C1/C2.
- `media_assets` owns safe provider references only; no API token or raw Cloudflare response is persisted.
- Do not create Product, Cart, Order, Inventory, Voucher or Campaign persistence in this phase.

## Risks and controls

| Finding | Control |
| --- | --- |
| Article mutation skeleton could be mistaken for runtime | Module stays disabled; root controller remains authority |
| Article image URL bypassed provider lifecycle | Cover/gallery now use `MediaService` |
| Cloudflare client duplicated per consumer | Employee adapter delegates to shared storage |
| Seller IAM role mistaken for business approval | Seller state separately gates Store mutation |
| Branch role could leak all branches | company then explicit branch access validation |
| Provider upload could orphan after DB failure | staged object compensation |
| Replace could lose old image | upload new, commit, cleanup old |
| Shared reference could be deleted | provider delete only after last reference |

Cloudflare live status remains **NOT VERIFIED / BLOCKED** by the previously observed HTTP 403, code 5403. No repeated external diagnostic was performed.

## Final local evidence

- Schema parity: 64 migrations / 236 tables in all three governed databases; `schema_admin_development` absent.
- Local synthetic foundation: 8 Branches, 1 Buyer Profile, 1 Seller Profile and 1 Store; bootstrap was executed twice without duplicate keys or external delivery.
- Navigation/help: 82/82 primary destinations at 1280×800 and 390×844, all HTTP 200; console/page errors and overflow zero.
- Data Quality: 20/20 rules, score 100, `commerce_issues=0`.
- Focused Commerce/media/help regression: 15 tests / 72 assertions, then Article Edit regression 4 tests / 19 assertions.
- Full regression: 572 tests, 571 passed, 7,694 assertions, one expected skip, exit 0.
- PHPStan level 10: application/modules and affected tests both zero errors. Composer/npm advisories and tracked-secret findings: zero.
