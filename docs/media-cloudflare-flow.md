# Media / Cloudflare Flow

## One boundary

`Validated upload → MediaService::stage → MediaStorageInterface → configured provider → StoredMedia → MediaService::attach → media_assets → owner relation/read query → delivery_url → Blade`.

Product cover/gallery, Employee avatar, Article media and Store media share this boundary. Controllers do not instantiate a Cloudflare client and provider credentials are never stored in media rows.

Product media is attached with the Product's canonical Store → Seller `company_id`. Migration 86 repairs older unscoped Product rows without creating a second ownership relation.

## Provider metadata

`media_assets` stores owner type/id, purpose, provider, provider asset ID, optional delivery URL, MIME, size, checksum and order. It does not store base64 payloads or API tokens.

## Testing

Testing/browser fixtures bind a fake/local `MediaStorageInterface` but still exercise the same `MediaService` staging/attach lifecycle. External Cloudflare calls are zero. A live Cloudflare provider is not reported VERIFIED until credentials and delivery configuration succeed.

Local storage persists `/storage/{asset}` rather than `http://localhost/...`, so the same record renders under Artisan, Playwright, a different port, or a configured local hostname. Migration 87 normalizes existing local rows only; Cloudflare delivery URLs remain provider-owned.

## Display source

Product list/detail, Seller Inventory and Branch Warehouse resolve Product cover from `media_assets` (`owner_type=product`, `purpose=product_cover`). Missing media renders the intentional placeholder; a fabricated remote image URL is never substituted.

## Hosted Images readiness and responsive presentation

The existing adapter posts through `CloudflareImagesStorage` to `/accounts/{account_id}/images/v1`; no second adapter or R2 path is used. Runtime variables are `MEDIA_DRIVER`, `MEDIA_MAX_BYTES`, `CLOUDFLARE_IMAGES_TIMEOUT`, `CLOUDFLARE_IMAGES_ACCOUNT_ID`, `CLOUDFLARE_IMAGES_API_TOKEN`, and `CLOUDFLARE_IMAGES_DELIVERY_HASH`.

Current local audit: `MEDIA_DRIVER=local`; Account ID/API token are configured; delivery hash is missing. Therefore live upload/delivery verification is `CONFIGURATION_REQUIRED` and was not attempted. The project currently has no configured semantic Cloudflare thumbnail/medium/large variant names, so it must not claim provider-side variant optimization.

Product source policy accepts decoded JPEG/PNG/WebP images up to 10 MiB, 6000 pixels per side and the global decoded-pixel ceiling. A native 3840×2160 source is accepted unchanged by the application media boundary; checksum, MIME and byte size provide provenance. Application code does not upscale images. Automated coverage uses a fake `MediaStorageInterface`, proving native 4K metadata while making zero Cloudflare calls. Local PHP/Nginx request-size configuration must also allow the selected application limit; a PHP `upload_max_filesize=2M` would reject a valid 4K file before Laravel and is an environment blocker, not a reason to bypass validation.

Presentation remains provider-neutral. Product table contexts render 64×48 px on desktop and 56×42 px on mobile; the POS selector renders 88×66 and 72×54 px. Public cards use their bounded card aspect ratio, while detail media is contained by the responsive detail column. Provider URLs are never assembled in Blade.
# RGBA ingress hardening (2026-09-01)

The reported valid 1254×1254 RGBA PNG (~2.9 MB) was rejected by the local PHP ingress limit (`upload_max_filesize=2M`, `post_max_size=2M`) before Laravel validation. It was not an RGBA decoder failure. Local project ingress is now 12 MB / 14 MB while Product application policy remains 10 MB and 6000 px.

`MediaImageInspector` verifies actual MIME, dimensions, pixel ceiling and full GD/Imagick decode. Storage still receives the original bytes, so PNG alpha is not flattened or recompressed. PHPUnit remains forced to the local media driver and performs zero Cloudflare writes.
