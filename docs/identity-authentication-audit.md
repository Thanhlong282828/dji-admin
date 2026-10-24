# Identity and authentication audit — C2.5

## Baseline and decision

- Browser authentication remains Laravel Breeze/session with verified-email and `users.access_enabled` enforcement.
- The active API contract previously used Sanctum bearer middleware. C2.5 deliberately migrates the same 24 documented operations to Laravel Passport OAuth 2.0 Authorization Code + PKCE.
- Spatie Permission, policies and company/branch scope remain the authorization source of truth. OAuth scopes are an additional coarse API boundary, never a replacement.
- Google/Facebook were not previously integrated. Social login is therefore link-first: provider identities are only usable after explicit linking by an authenticated local User.
- Phone verification was absent. The new boundary stores normalized E.164 phone data and hashed, expiring, single-use challenges.

## Threat findings and controls

| Threat | Control |
|---|---|
| Provider email account takeover/merge | No automatic matching or merging by email; unique provider ID + explicit authenticated linking |
| Provider token leakage | Access/refresh tokens are never persisted or logged; only safe provider identity metadata is stored |
| Disabled-account bypass | Social login and Passport API requests enforce `access_enabled`; disabling an Employee account revokes active OAuth tokens |
| OAuth token overreach | Short-lived access token, refresh-token expiry, endpoint scopes, then IAM/policy and tenant checks |
| OTP brute force/replay | Hash-at-rest, expiry, persisted attempt counter, request throttles and consumed timestamp |
| Phone enumeration | Login request returns the same response for known and unknown phone numbers |

## Live provider status

Google, Facebook and a production SMS/OTP provider are **NOT VERIFIED** locally because no valid provider credentials were supplied. Fake/provider-boundary tests cover the code without external traffic.

## Final evidence

- Focused identity boundary: 12 tests, 43 assertions; broader auth/API/Data Quality regression: 42 tests, 137 assertions. Both PASS.
- Full PHPUnit: 581 tests, 580 passed, 7,725 assertions, 1 skipped, exit 0.
- PHPStan app + Modules level 10 and affected identity test: 0 errors.
- API Contract: 24/24 operations, Passport Authorization Code + PKCE, PASS.
- Data Quality: 24/24 rules, identity issues 0.
- Browser 1280×800 and 390×844: Account Security + local OTP PASS; console/page errors/overflow 0; fixture residue 0.
- Database parity: 70 migrations / 243 tables in the three active local databases; development DB absent; VPS untouched.
