# Identity and authentication design — C2.5

## Canonical boundaries

`users` remains the canonical account and IAM principal. `user_external_identities` maps a local User to one Google/Facebook provider subject without storing OAuth tokens. `phone_verification_challenges` owns OTP lifecycle; `users.phone_e164` and `phone_verified_at` own the verified result.

Browser flow remains session-based. API flow is Passport Authorization Code + PKCE with refresh tokens. Password and implicit grants are not part of the supported design. Provider credentials are environment configuration; no secret is committed or rendered.
Google/Facebook client-secret references are registered in Secret Governance. Passport signing keys are injected through the deployment environment or protected runtime key files and are never committed.

## Flows

1. Link social identity: authenticated account → provider state redirect → callback → collision check under transaction → safe identity mapping → audit event.
2. Social login: provider callback → exact provider subject lookup → `access_enabled` check → local session regeneration. Unknown identities are rejected; no account is auto-created.
3. Phone verification/login: normalize E.164 → create hashed five-minute challenge → provider boundary → verify under lock → persist attempt/consumed state → mark phone verified → regenerate session on login.
4. API: authorization request + PKCE → explicit consent → short-lived access token → endpoint scope → active-account middleware → Spatie/policy/company scope → controller/CQRS.

## Account security page

`/account` shows safe profile, verification status, linked providers, roles, company/branch scope and aggregate OAuth client/token counts. It does not reveal provider tokens, OAuth secrets or individual sensitive HR/payroll data.
