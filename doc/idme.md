# ID.me integration

`sevenx_authentication_2fa` includes a dedicated `eZIdmeUser` handler for
[ID.me](https://www.id.me/) OAuth 2.0 / OpenID Connect social login.

## Endpoints

The default endpoints are configured in `settings/sevenxauthentication2fa.ini.append.php`
under `[Idme]`:

| Setting | Default value |
|---------|---------------|
| `AuthorizationURL` | `https://api.id.me/oauth/authorize` |
| `TokenURL` | `https://api.id.me/oauth/token` |
| `UserInfoURL` | `https://api.id.me/api/public/v3/attributes.json` |

For sandbox testing, replace these with the `api.idmelabs.com` endpoints.

## Supported INI settings

```ini
[Idme]
Enabled=disabled
ClientID=your-client-id
ClientSecret=your-client-secret
Scope=military
Op=signin
EID=
Nonce=
UsePKCE=enabled
AuthorizationURL=https://api.id.me/oauth/authorize
TokenURL=https://api.id.me/oauth/token
UserInfoURL=https://api.id.me/api/public/v3/attributes.json
```

- `Scope` — required ID.me community scope. Examples: `military`, `student`,
  `teacher`, `responder`, `government`, `employee`, `hospital_employee`,
  `alumni`, `nurse`, `medical`, and their `_canada` variants. For OIDC you can
  also include `openid`.
- `Op` — `signin` or `signup`.
- `EID` — optional external identifier passed as the `eid` parameter.
- `Nonce` — optional OIDC nonce for replay protection.
- `UsePKCE` — `enabled` by default. Should not be disabled for ID.me.

## User info parsing

The handler normalizes both ID.me response formats to the common keys `id`,
`email` and `name`.

For the `attributes.json` response, the handler extracts:

- `uuid` → `id`
- `email` → `email`
- `fname` + `lname` → `name`

For the OIDC `/userinfo` response, the handler extracts:

- `sub` → `id`
- `email` → `email`
- `given_name` + `family_name` → `name`

## Registration and redirect URI

1. Create an application in the [ID.me Developer Portal](https://developers.id.me/).
2. Add the redirect URI:
   `https://alpha.se7enx.com/user2fa/callback/idme`.
3. Copy the **Client ID** and **Client Secret** into the `[Idme]` block.
4. Choose the `Scope` that matches the community you want to verify.

## CLI configuration

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2faconfig.php \
    --provider=IDme \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_CLIENT_SECRET \
    --scope=military \
    --op=signin \
    --allow-root-user
```

## Module URLs

- `/user2fa/oauth/idme` — redirect to ID.me authorization endpoint.
- `/user2fa/callback/idme` — callback that exchanges code, fetches profile and
  logs in.
