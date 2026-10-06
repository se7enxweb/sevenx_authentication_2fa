# sevenx_authentication_2fa

7x Two-Factor and Social Authentication extension for Exponential 6.x.

Copyright (C) 1998 - 2026 7x. All rights reserved.
Licensed under the GNU General Public License v2.0 (or any later version).

- Version: 1.0.2
- GitHub: https://github.com/se7enxweb/sevenx_authentication_2fa
- Composer: https://packagist.org/packages/se7enxweb/sevenx_authentication_2fa

## About

`sevenx_authentication_2fa` adds two-factor authentication and OAuth social-login
handlers to Exponential 6.x. It is built to work on Exponential 6.x
installations with minimal dependencies, while remaining compatible with PHP 8.5.8.

## Features

- **Time-based One-Time Passwords (TOTP)** — RFC 6238 implementation using only
  PHP built-ins (`hash_hmac`, `pack`). Works with Google Authenticator, Authy,
  Microsoft Authenticator and any other RFC-compliant app.
- **E-mail OTP fallback** — sends a numeric code via `eZMail` when TOTP is
  unavailable or configured as the primary method.
- **2FA datatype** — attach `sevenxauthentication2fa` to the `user` content
  class to let users configure their own method and secret.
- **Login handler** — `eZsevenxUser2faUser` validates the password and then either
  completes the login or redirects to the 2FA challenge view.
- **OAuth / social login skeletons** — ready-to-customise handlers for Google,
  Facebook, Twitter/X, Instagram and Meta, plus a base class and CLI builder for
  additional providers.
- **Resumable e-mail verification link** — the e-mail body contains a direct
  link `/user2fa/verify/code/<code>` that finishes the login when it is opened
  in the browser where the password was entered.
- **No Valkey/Redis required** — a pending second step lives in the visitor's
  own session only; nothing of it is written to the file system.
- **Hardened second step** — the password alone never signs in; TOTP codes are
  accepted once (replay refused) within a configurable window; wrong codes are
  limited per sign-in and count as failed logins of the account; e-mail codes
  are kept hashed; TOTP secrets can be encrypted at rest; every redirect target
  passes the safe redirect rules. See [Security](#security).
- **On-model pages** — the second step, setup, social login answers and the
  2FA field are drawn in the look of the admin (admin4 light and dark, admin,
  Admin UI) and of the media site design. See `doc/two-factor-pages.md`.
- **Template-based e-mail body** — the OTP e-mail is rendered from an
  overridable template so you can add newlines, branding and multiple links.
- **Cleanup tools** — cronjob and `bin/php` script remove expired pending
  challenges from sessions and the filesystem.

## Requirements

- Exponential 6.x (tested with PHP 8.5.8).
- PHP `hash` extension (for TOTP).
- For OAuth handlers, `curl` or `allow_url_fopen` is required for HTTP requests.

## Installation

### Manual install

1. Copy or checkout the extension into `extension/sevenx_authentication_2fa`.

2. Make the extension active in `settings/override/site.ini.append.php`:

```ini
[ExtensionSettings]
ActiveExtensions[]=sevenx_authentication_2fa
```

3. Regenerate autoloads:

```bash
php bin/php/ezpgenerateautoloads.php -e
```

4. (Optional) Clear caches:

```bash
php bin/php/ezcache.php --clear-all --allow-root-user
```

### Composer install

```bash
composer require se7enxweb/sevenx_authentication_2fa
```

Then activate the extension and regenerate autoloads as described above.

## Configuration

### Activating 2FA

Add the login handler to `settings/override/site.ini.append.php`. Because the
standard handler logs the user in before the 2FA challenge can run, it must be
listed **after** `sevenxUser2fa`:

```ini
[UserSettings]
ExtensionDirectory[]=sevenx_authentication_2fa
LoginHandler[user2fa]=sevenxUser2fa
LoginHandler[]=standard
```

If `settings/site.ini` already contains `LoginHandler[]=standard`, that line
should be removed or commented so the override order takes effect.

Configure the extension in `extension/sevenx_authentication_2fa/settings/sevenxauthentication2fa.ini.append.php`
or override it in `settings/override/sevenxauthentication2fa.ini.append.php`.

### Role policies and permissions

The `user2fa` module defines four policy functions: `setup`, `verify`, `oauth`,
and `callback`. Grant them through Exponential **Roles and policies** only.

`verify`, `oauth`, `callback` and `setup` happen before the user is signed in,
when the visitor is the anonymous user. In an administration siteaccess the
anonymous user may not use the siteaccess at all, so no policy could let the
second step through there. The extension therefore lists the four views in
`[RoleSettings] PolicyOmitList` (as the kernel does for `user/login`) and in
`[SiteAccessSettings] AnonymousAccessList`, and each view checks its own state:

| View | Who gets through |
|------|------------------|
| `/user2fa/verify` (and `/user2fa/verify/code/<code>`) | Only a session that has just passed the password. |
| `/user2fa/oauth/<provider>` | Anyone, when social login and the provider are enabled. |
| `/user2fa/callback/<provider>` | Only the answer to a login this session started (state check). |
| `/user2fa/setup` | A signed in user with the policy **`user2fa/setup`** (grant it to Member, Editor, Administrator ...), or a session that passed the password while `Enforce2FA` asks for a method first. |

Policies for the anonymous role are no longer needed, and do no harm.

Available 2FA methods:

- `totp` — authenticator app
- `email` — one-time code sent by e-mail
- `disabled` — 2FA not used

### E-mail OTP template and resumable link

The e-mail OTP body is rendered from:

```
extension/sevenx_authentication_2fa/design/standard/templates/mail/2fa_code.tpl
```

Available template variables:

- `{$code}` — the one-time code
- `{$expires}` — code lifetime in minutes
- `{$site_url}` — `eZSys::serverURL()`
- `{$verify_url}` — resumable link such as `/user2fa/verify/code/<code>`

To override the e-mail, copy that file into your site design, e.g.
`design/sevenx_site_admin/templates/mail/2fa_code.tpl`.

You can still override the body from INI by setting
`EmailSettings.Body` in `sevenxauthentication2fa.ini`. It supports the
placeholders `{code}`, `{expires}`, `{site_url}` and `{verify_url}`. If the
INI body is empty or commented out, the template is used.

The e-mail resumable link `/user2fa/verify/code/<code>` accepts the code as a
module unordered parameter. Opened in the browser where the password was
entered, it validates the code and completes the login while the challenge is
open; opened anywhere else it says so and signs nothing in (a link that worked
in any browser would let whoever sees the mail sign in without the password
step).

### Pending challenge storage

A pending second step is kept in the visitor's session only
(`Sevenx2FA_Pending`): the user who passed the password, the method, when it
ends, where to go afterwards, the wrong codes so far and, for an e-mail code,
its salted hash. Versions up to 1.0.2 also wrote JSON files to
`var/<site>/cache/sevenx_2fa_pending/`, keyed by user and by the code, holding
the code or the TOTP secret in plain text and looked up across sessions; the
cleanup cronjob and `bin/php/sevenx2facleanup.php` now delete every such file.

### Social login

OAuth handler skeletons are provided for Google, Facebook, Twitter/X, Instagram,
Meta and ID.me. To enable a provider:

1. Create an OAuth application with the provider and obtain a client id / secret.
   See `INSTALL.md` for step-by-step sign-up instructions for each service.
2. Fill in the matching block in `settings/override/sevenxauthentication2fa.ini.append.php`.
3. Set `SocialLogin=enabled` and `AutoCreateUser=enabled` (if desired).
4. Link users to `/user2fa/oauth/<provider>`, for example `/user2fa/oauth/google`.

Users can also be redirected through the login handler system by setting
`LoginHandler[]=google` etc.

See `doc/idme.md` for ID.me-specific setup details.

## Usage

### User 2FA setup

After installation, users can visit `/user2fa/setup` to enable TOTP or e-mail
authentication. While an authenticator is being set up the page shows a QR
code, drawn on the server into a `data:` URI (no external QR service sees the
secret), and the key in groups of four. The key is made on the server and kept
in the session until a code from the app confirms it; once confirmed it is
never shown again. Moving away from a confirmed authenticator (to e-mail codes,
off, or a reset) needs a current code from it.

### Login and register pages

The social login buttons come from one partial,
`design:user2fa/parts/social_buttons.tpl`, with a button for every provider
that `SocialLogin` and the provider's own `Enabled` switch on. The pages that
show it include it themselves when the module exists
(`{if ezmodule( 'user2fa/oauth' )}`): the login pages of Exponential 6.0.15
(admin, admin4) and of the Admin UI, the kernel's `user/register.tpl`, and the
media design's login and registration pages.

The extension no longer ships copies of `user/login.tpl`, `user/register.tpl`
and `user/edit.tpl`: active, those copies hid the kernel's newer templates (the
Cancel button, the e-mail preference and API key links). A design of your own
adds the include lines shown in `doc/two-factor-pages.md`.

### User edit page

`design:user2fa/parts/account_link.tpl` shows the state of the second step with
a link to `/user2fa/setup` (a box, or a list item for a design's own list of
account links, as the media design's profile uses it). The kernel's
`user/edit.tpl` includes it; admin4's account page has a card of its own for it.

### Adding a new OAuth provider

Use the skeleton builder:

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2fabuild.php \
    --provider=MyProvider \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_SECRET \
    --authorization-url=https://provider.example.com/oauth/authorize \
    --token-url=https://provider.example.com/oauth/token \
    --userinfo-url=https://provider.example.com/api/me \
    --scope="email profile"
```

This creates `extension/sevenx_authentication_2fa/login_handler/ezmyprovideruser.php`
and appends the matching INI block. Edit the `normalizeUserInfo()` method to match
the provider's response format.

### Cleanup

A `cronjobs/sevenx2facleanup.php` script is included. Register it in `cronjobs.ini` or run manually:

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2facleanup.php
```

The script removes expired pending challenge data from the current PHP session
and from the filesystem cache `var/site/cache/sevenx_2fa_pending/`.

## Security

- **The password alone never signs in.** The login handler checks the
  password and the siteaccess, then keeps only "this user passed the password"
  in the session; the user is signed in (with a new session id) after the right
  code. Up to 1.0.2 the user was signed in before the second step, so leaving
  the code page was enough.
- **Codes**: a TOTP code is accepted within `[CodeSettings] Window` steps (0 to
  3, default 1) and only once: the step that signed in is stored and that step
  and older ones are refused. `[Security] MaxAttempts` (default 5) wrong codes
  end the sign-in, and every wrong code counts as a failed login of the account
  (`site.ini [UserSettings] MaxNumberOfFailedLogin`). E-mail codes are kept as a
  salted HMAC; a new one can be asked for after `ResendInterval` seconds, at
  most `MaxResends` times.
- **Secrets at rest**: set `[TOTPSettings] SecretKey` (or the environment
  variable `SEVENX_2FA_SECRET_KEY`) in `settings/override` to store TOTP
  secrets AES-256-GCM encrypted in the user object. Without a key they are
  stored as before. Secrets are 160 bits from `random_bytes()`.
- **Social login**: the state is 256 random bits, bound to the provider and
  to ten minutes, used once and compared in constant time; PKCE where
  configured; an address the provider marks `email_verified: false` is not
  used to find an account; endpoints must be https and redirects are not
  followed when the token is fetched; an account found this way still has its
  own second step.
- **Redirects**: every target from outside the code (the login form's
  `RedirectURI`, `RedirectAfterLogin`, the social login's `RedirectURI`) passes
  `eZRedirectManager::unsafeReason()` (Exponential 6.0.15,
  `doc/features/6.0/safe-redirects.md`), or the same rules built in on older
  kernels, and is reduced to a path of the site.
- **Pages** with a secret, a QR code or a pending sign-in are sent with
  `Cache-Control: no-store`; forms are posted with the form token of
  `ezformtoken`.

## Tests

The logic that needs no database has PHPUnit tests (RFC 6238 vectors, the time
window, replay, attempt and resend limits, e-mail code hashing, the redirect
rules, secret encryption):

```bash
php vendor/bin/phpunit --no-configuration \
    --bootstrap extension/sevenx_authentication_2fa/tests/bootstrap.php \
    extension/sevenx_authentication_2fa/tests/unit
```

## Service handler APIs

See `doc/2fa_service_handler_support_apis.md` for the full PHP class API,
module URLs, login handler conventions and cronjob / CLI reference.

## License

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING THE
WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.

The GNU General Public License is available at http://www.gnu.org/licenses/.

## Author

Developed and maintained by **7x** — https://se7enx.com


