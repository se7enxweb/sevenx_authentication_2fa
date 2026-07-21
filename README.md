# sevenx_authentication_2fa

7x Two-Factor and Social Authentication extension for Exponential / eZ Publish Legacy.

Copyright (C) 1998 - 2026 7x. All rights reserved.
Licensed under the GNU General Public License v2.0 (or any later version).

- Version: 1.0.1
- GitHub: https://github.com/se7enxweb/sevenx_authentication_2fa
- Composer: https://packagist.org/packages/se7enxweb/sevenx_authentication_2fa

## About

`sevenx_authentication_2fa` adds two-factor authentication and OAuth social-login
handlers to Exponential / eZ Publish Legacy. It is built to work on older eZ
Publish 4 installations with minimal dependencies, while remaining compatible
with PHP 8.x.

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
- **Cleanup tools** — cronjob and `bin/php` script to remove expired pending
  challenges.

## Requirements

- Exponential / eZ Publish Legacy 4.x or later (tested with PHP 5.3+ and 8.x).
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

Available 2FA methods:

- `totp` — authenticator app
- `email` — one-time code sent by e-mail
- `disabled` — 2FA not used

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
authentication. The setup page displays the TOTP secret and a QR code image
generated via Google Chart API. If you prefer not to use an external service, the
`otpauth://` URI and the plain text secret are also shown.

### Login and register pages

The extension provides template overrides for `user/login.tpl` and
`user/register.tpl` that add Google and ID.me sign-in buttons. Buttons are shown
when `SocialLogin` and the matching provider are enabled.

### User edit page

A "Two-Factor Authentication" link is added to the `user/edit` view templates so
users can manage 2FA from their profile.

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
