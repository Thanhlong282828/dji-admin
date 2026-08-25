# API authentication migration: Sanctum to Passport

## Breaking boundary

The 24 existing OpenAPI operations keep their paths and business semantics, but the 22 protected method records now require Passport OAuth 2.0 bearer tokens and endpoint scopes. The two public Article reads remain public. This authentication change is intentionally recorded as a new approved API contract baseline.

Supported grant: Authorization Code + PKCE, with refresh token. Password and implicit grants are unsupported. Clients must be registered as public or confidential authorization-code clients as appropriate; client secrets must never be committed.

Scope mapping:

- `company:read`, `company:write`
- `organization:read`
- `onboarding:read`, `onboarding:write`
- `performance:read`, `performance:write`
- `recruitment:read`, `recruitment:write`

Scopes are coarse consent boundaries. Existing Spatie permissions, policies, company scope and `access_enabled` checks still apply. Existing Sanctum personal tokens are not accepted by the active API guard. Clients must complete a new Passport authorization flow and handle access-token renewal through the refresh-token grant.

Rollback is application-level only while the additive OAuth schema remains harmless: deploy the previous code and contract, then explicitly revoke Passport tokens. Do not drop OAuth/identity tables as part of an emergency rollback.
