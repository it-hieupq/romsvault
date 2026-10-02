# Salvage notes from the deleted `auth/` folder (2026-07-23)

A `auth/` folder was copied from another project and **deleted** — it used
`wp_users` + a `member` role + premium tiers + private-site gating + email/password
login + profile pages, all of which conflict with our Plan B (separate `wphh_member`
table, no WP role, no `wp_set_auth_cookie`, cacheable front-end). It was never wired
into the theme. Only two things were worth keeping — captured here.

## 1. Google token verification — the anti-pattern to AVOID (P2.3)

Their `class-google-oauth.php::decode_id_token()` **did NOT verify the JWT signature** —
it split the token, base64url-decoded only the payload (discarding header + `$parts[2]`
signature), then checked `aud/iss/exp/email_verified`. That was tolerable *only* because
they used a server↔Google **authorization-code** flow (TLS is the trust anchor).

**Our flow receives the ID token from the browser (GSI) → an unsigned-verify is trivially
forgeable.** P2.3 MUST verify for real:
- Fetch Google JWKS `https://www.googleapis.com/oauth2/v3/certs`, cache in Redis by `kid`.
- Match the token header `kid` → verify **RS256** signature.
- Then check claims: `aud === ROMSFUN_GOOGLE_CLIENT_ID`; `iss ∈ {accounts.google.com,
  https://accounts.google.com}`; `exp > now`; **`iat` fresh (≤ ~5 min)**; `email_verified` truthy.
- Only `base64url_decode()` (RFC-4648 URL-safe padding) is worth lifting as a helper.

## 2. Admin members UI skeleton (P6)

`class-members-admin.php` had a solid WP-admin pattern worth replicating (NOT the code —
it was coupled to `get_users(role=member)` + premium):
- `add_menu_page` (guard `edit_users` for us, they used `manage_options`).
- Action handler: `check_admin_referer` nonce + capability check + **PRG redirect**.
- List / detail split, filter tabs, search form; `$wpdb->prepare` for all queries.
- For us: data source = `wphh_member` table; actions reduced to **block/unblock**
  (maps onto their grant/revoke pattern).
