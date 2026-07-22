# Known weaknesses and limits of sevenx_authentication_2fa

This document lists known security, functional and operational limitations.
Review these before deploying the extension in production.

## Security limitations

- **No rate limiting or account lockout for 2FA codes.** The extension relies on
  the core `eZUser` failed-login counter for password attempts but does not add
  rate limiting for TOTP/email code verification or for OAuth callback attempts.
  Additional server-level protection (fail2ban, WAF rules, etc.) is recommended.

- **2FA codes can be replayed within a time window.** TOTP codes remain valid for
  the configured `Window` of time steps. The same code can be reused during that
  window; successful codes are not marked as consumed. Email codes are single-use
  because the pending challenge is removed on success, but the same code remains
  valid until it expires if the challenge is not completed. Re-logging in before
  the code expires reuses the same pending challenge, so the same e-mail code can
  be valid across multiple login attempts.

- **TOTP and email secrets are stored in the database unencrypted.** The
  `sevenxauthentication2fa` datatype serializes the 2FA data object (including
  the TOTP secret) into the `data_text` column. If the database is compromised,
  the secrets are exposed. Encrypting the column at rest or using a separate
  secrets store would improve security.

- **OAuth provider secrets are stored in plain-text INI files.** `ClientID` and
  `ClientSecret` are kept in `settings/override/sevenxauthentication2fa.ini.append.php`
  like all other Exponential configuration. Keep these files out of version control
  and restrict file-system access.

- **OAuth email addresses are trusted without explicit verification checks.** The
  extension matches incoming users by the email returned by the provider. If a
  provider returns an unverified email address, an attacker could potentially
  take over a local account. Use providers that guarantee verified email
  addresses, or override `eZOAuthUser::normalizeUserInfo()` to require a
  `email_verified` flag.

- **PKCE is optional and provider-specific.** The base `eZOAuthUser` class only
  enables PKCE when a provider's `UsePKCE` setting is `enabled`. ID.me enables
  PKCE by default, but other providers rely on the traditional authorization-code
  flow unless explicitly configured.

- **No refresh-token handling.** Once the OAuth access token expires, the
  extension does not refresh it. Social login sessions last only as long as the
  local Exponential session.

- **OAuth state is stored in the PHP session.** If the user blocks cookies or
  the session is not shared between `/user2fa/oauth/<provider>` and
  `/user2fa/callback/<provider>`, the `state` check fails. This also means the
  flow is vulnerable to session fixation if the session ID is fixed before the
  OAuth redirect.

- **No JWT/id_token validation.** The extension only calls the provider's
  userinfo endpoint with the access token. It does not validate signed identity
  tokens. The security of this step depends on TLS and on the provider's
  userinfo endpoint integrity.

- **External QR code generation.** The setup templates generate QR codes by
  sending the provisioning URI to `https://chart.googleapis.com/chart`. This
  leaks the TOTP secret to Google and requires an external network request.
  Replace with a local QR-code library for privacy-sensitive deployments.

- **Audit log rotation is small.** `eZLog::write()` rotates `var/log/auth.log`
  at 200 KB and keeps only three backups. High-traffic sites may lose audit
  history quickly. Consider shipping the log to a centralized log store.

- **Audit logs may contain PII.** `var/log/auth.log` records IP addresses and
  user-agent strings. Ensure compliance with local privacy regulations.

## Functional limitations

- **OAuth providers that require `access_token` as a query parameter may not work
  out of the box.** The default `eZOAuthUser::fetchUserInfo()` sends the token in
  an `Authorization: Bearer` header. Providers such as Instagram Basic Display
  expect `access_token` in the userinfo URL. These providers require a custom
  subclass that overrides `fetchUserInfo()` or `httpRequest()`.

- **User matching is by email by default.** If a provider does not return an
  email address (for example X without the email permission), the fallback tries
  to use `id` for the local login name but still matches by email. Set
  `AuthenticationMatch=id` in `sevenxauthentication2fa.ini` for such providers
  and ensure the `id` value is stable and unique.

- **ID.me requires a community scope.** The handler will refuse to redirect until
  `Scope` is set to a valid ID.me community such as `military`, `student`,
  `teacher`, `responder` or `government`. A missing or generic scope will cause
  the login attempt to fail with `oauth_idme_scope_missing` in `var/log/auth.log`.

- **ID.me userinfo response format must be configured.** The handler can parse
  both `https://api.id.me/api/public/v3/attributes.json` and the OIDC
  `/userinfo` endpoint, but you must set `UserInfoURL` to match the response
  format your application policy returns.

- **No account linking UI.** If a user already has a local account and later
  signs in with an OAuth provider that returns the same email, the accounts are
  matched automatically. There is no user-facing flow to manually link or unlink
  social accounts.

- **Social auto-creation creates accounts with a random password.** New users
  created via `AutoCreateUser=enabled` cannot log in with a password unless they
  use the password-reset flow.

- **OAuth requests have no explicit timeout.** `eZOAuthUser::httpRequest()` does
  not set cURL or stream timeouts. A slow or hung provider request can block a
  PHP-FPM worker.

- **cURL errors are silently ignored.** `eZOAuthUser::httpRequest()` returns
  `false` on cURL or `file_get_contents` failure but does not log the underlying
  error. Use `var/log/auth.log` entries and server-level network logs for
  debugging.

- **OAuth callback and redirect URLs are hard-coded to `/user2fa/...`.** The
  extension assumes the site is installed at the document root. Sub-directory
  installs may need to adjust `eZURI::transformURI()` calls and callback URI
  registrations in the provider consoles.

- **The 2FA setup module uses `data_text` storage.** All 2FA data is stored as
  JSON in a single text column. There is no separate table, indexing or
  encryption layer.

- **The `eZOAuthUser::isEnabled()` method overrides `eZUser::isEnabled()`.**
  Instances of OAuth handler classes use `isEnabled()` to mean "this provider is
  enabled in INI". This is normally fine because the handler objects are not
  persisted as users, but it can be surprising if the class is used in a context
  that expects the account-status check.

- **The `Sevenx2FA_SetupUserID` session variable is set but never read.** The
  helper variable set in `eZsevenxUser2faUser::redirectToSetup()` is not used by the
  setup view, which relies on `eZUser::currentUser()` instead.

- **`eZsevenxUser2faUser` does not regenerate the session ID on login.** Successful
  password, 2FA and OAuth logins call `eZUser::setCurrentlyLoggedInUser()` but
  do not force a new session ID. Session fixation is possible if an attacker
  knows the pre-login session ID.

- **The 2FA handler may call the standard handler again.** When
  `eZsevenxUser2faUser::loginUser()` returns `false` for a wrong password, the core
  `eZUserLoginHandler` falls through to the next `LoginHandler[]` entry. If
  `standard` is also enabled, the password is validated a second time.

- **Email OTP delivery is asynchronous and unconfirmed.**
  `sevenxAuthentication2faEmail::sendCode()` stores the pending challenge even
  when `eZMailTransport::send()` reports a failure. The user may be told a code
  was sent when it was not.

## Operational limits

- **Requires Exponential 6.x.** The extension is tested with PHP 8.5.8. No
  Valkey/Redis or kernel patches are required for core 2FA functionality.

- **INI changes require cache clear.** OAuth and 2FA settings are read through
  `eZINI`. After editing `settings/override/sevenxauthentication2fa.ini.append.php`,
  clear caches (`php bin/php/ezcache.php --clear-all --allow-root-user`) for the
  changes to take effect.

- **Template overrides rely on design extension ordering.** The extension uses
  `DesignExtensions[99]` in `design.ini.append.php` to ensure its templates are
  processed last. If another extension uses the same high key, overrides may not
  apply.

- **Template buttons are limited to configured providers.** The bundled
  `login.tpl` and `register.tpl` render buttons for providers enabled in the
  `[SocialProviders]` INI list. To add a custom provider, add it to the
  `SocialProviders` list and ensure the provider class exists.

- **Resumable e-mail link carries the code in the URL.** The verification URL
  `/user2fa/verify/code/<code>` may appear in web server access logs. Ensure
  log access is restricted and rotated.

- **No filesystem locking on pending cache writes.** `file_put_contents()` with
  `LOCK_EX` protects individual file writes, but concurrent re-login/resend for
  the same user can race. High-concurrency sites should monitor the cache
  directory for stale or duplicate files.

- **`sevenx2fabuild.php` overwrites existing files.** Running the skeleton builder
  twice for the same provider name will replace the existing handler class.

- **Filesystem pending cache must be writable.** Cross-session resume and the
  resumable e-mail link depend on `var/site/cache/sevenx_2fa_pending/` being
  writable by the web server. If the directory cannot be created or written to,
  re-login or a different browser will start a new 2FA challenge.

- **Cronjob cleanup does not clear old Valkey keys.** The cleanup script now
  removes expired pending challenges from the current PHP session and from the
  filesystem cache `var/site/cache/sevenx_2fa_pending/`. It no longer relies on
  or clears Valkey/Redis keys.

- **`eZLog` path is relative to the current working directory.** When audit
  entries are written from CLI scripts, ensure the script runs from the eZ
  docroot or `var/log/auth.log` may be created elsewhere.
