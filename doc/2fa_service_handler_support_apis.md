# 2FA and Social Login Service Handler Support APIs

## Overview

`sevenx_authentication_2fa` exposes a small set of classes and module URLs that let other extensions and custom code hook into two-factor authentication and OAuth social-login flows.

## PHP Classes

### `sevenxAuthentication2faHelper`

Central configuration, session and filesystem helper.

```php
$helper = sevenxAuthentication2faHelper::instance();

// Is the extension enabled?
$helper->isEnabled();

// Is 2FA enforced for all users?
$helper->isEnforced();

// What is the effective 2FA method for a user (totp|email|disabled)?
$method = $helper->userMethod( $userID );

// Read the stored 2FA data object for a user.
$data = $helper->userData( $userID );

// Create a pending challenge. Also writes JSON files to
// var/site/cache/sevenx_2fa_pending/ for cross-session resume.
$helper->setPendingChallenge( $userID, 'totp', $secret, 300, $redirectURI );
$helper->setPendingChallenge( $userID, 'email', $code, 600, $redirectURI );

// Inspect / remove the pending challenge. getPendingChallenge() checks the
// current session first, then the filesystem cache by code or user ID.
$pending = $helper->getPendingChallenge();
$pending = $helper->getPendingChallenge( $code );
$pending = $helper->getPendingChallenge( false, $userID );
$helper->removePendingChallenge();

// Remove expired data (cronjob/CLI use).
$helper->cleanupExpiredSessions();
$helper->cleanupExpiredFiles();
```

### `sevenxAuthentication2faTOTP`

RFC 6238 TOTP implementation with no external dependencies.

```php
$secret = sevenxAuthentication2faTOTP::generateSecret();
$uri    = sevenxAuthentication2faTOTP::provisioningUri( $login, $secret, 'Exponential' );
$code   = sevenxAuthentication2faTOTP::code( $secret );
$ok     = sevenxAuthentication2faTOTP::verify( $secret, $code );
```

### `sevenxAuthentication2faEmail`

E-mail one-time password helper.

```php
// Generates a code, sends it, and stores the pending challenge.
// If a valid pending challenge already exists for the user, the existing code
// is reused and no new e-mail is sent (unless $resend is true).
$code = sevenxAuthentication2faEmail::sendCode( $user, $redirectURI );
$code = sevenxAuthentication2faEmail::sendCode( $user, $redirectURI, true );

// Verifies the pending challenge against the user-supplied code.
// Looks up the challenge in the current session and the filesystem cache.
$ok = sevenxAuthentication2faEmail::verifyCode( $code );
```

### `sevenxAuthentication2fa` (data object)

Value object stored by the `sevenxauthentication2fa` datatype.

```php
$data = new sevenxAuthentication2fa( 'totp', $secret, true );
$json = $data->toJson();
$data = sevenxAuthentication2fa::fromJson( $json );
```

### `eZOAuthUser`

Base class for OAuth 2.0 social-login handlers. Extend it for any provider.

```php
class eZMyProviderUser extends eZOAuthUser
{
    protected $provider = 'myprovider';

    protected function normalizeUserInfo( $info )
    {
        return array(
            'id'    => $info['id'],
            'email' => $info['email'],
            'name'  => $info['name'],
        );
    }
}
```

Use the `sevenx2fabuild.php` CLI to scaffold a new provider.

### `eZIdmeUser`

ID.me-specific handler that extends `eZOAuthUser`.

- Always uses PKCE (`code_challenge` / `code_verifier`).
- Supports the `op` (`signin`/`signup`), `eid` and `nonce` authorization
  parameters.
- Requires a non-empty community `Scope` such as `military`, `student`,
  `teacher`, `responder` or `government`.
- Parses both the ID.me `attributes.json` response and the OIDC `/userinfo`
  response.
- Normalizes `uuid`/`sub` to `id`, `email` and `fname`/`lname` or
  `given_name`/`family_name` to `name`.

## Module URLs

| URL | Purpose |
|-----|---------|
| `/user2fa/verify` | 2FA code verification view. Used by `eZsevenxUser2faUser` after password login. |
| `/user2fa/verify/code/<code>` | Resumable verification link from the e-mail OTP. Auto-validates the code. |
| `/user2fa/setup` | User self-service page to enable/disable TOTP or e-mail 2FA. |
| `/user2fa/oauth/<provider>` | Redirect to the OAuth authorization endpoint. |
| `/user2fa/callback/<provider>` | OAuth callback that exchanges code, fetches profile and logs in. |

### Module permissions

The `user2fa` module declares four policy functions in `module.php`:
`setup`, `verify`, `oauth`, and `callback`. The extension relies on Exponential
**roles and policies** only.

| View | Policy function | Required role policies |
|------|-----------------|------------------------|
| `/user2fa/setup` | `setup` | Grant `user2fa/setup` to **Member**, **Editor**, **Partner**, **Administrator**, or any role that should manage 2FA. |
| `/user2fa/verify` | `verify` | Grant `user2fa/verify` to the **Anonymous** role and to every role that uses 2FA, such as Member, Editor, Partner or Administrator. |
| `/user2fa/verify/code/<code>` | `verify` | Same as `/user2fa/verify` — resumable e-mail link. |
| `/user2fa/oauth/<provider>` | `oauth` | Grant `user2fa/oauth` to the **Anonymous** role. |
| `/user2fa/callback/<provider>` | `callback` | Grant `user2fa/callback` to the **Anonymous** role. |

The views enforce their own state checks. For example, `verify.php` only
processes a request when a valid pending challenge exists, so
granting `user2fa/verify` to Anonymous is safe.

## Login Handlers

Place a class file in `extension/<ext>/login_handler/ez<protocol>user.php`:

```php
class eZMyAuthUser extends eZUser
{
    public static function loginUser( $login, $password, $authenticationMatch = false )
    {
        // Validate, then return eZUser, false or redirect.
    }
}
```

Register the handler in `site.ini`:

```ini
[UserSettings]
ExtensionDirectory[]=my_extension
LoginHandler[]=myauth
```

## Cronjob / CLI

| Script | Purpose |
|--------|---------|
| `extension/sevenx_authentication_2fa/cronjobs/sevenx2facleanup.php` | Remove expired pending 2FA challenges from sessions and the filesystem cache. Register in `cronjobs.ini`. |
| `extension/sevenx_authentication_2fa/bin/php/sevenx2facleanup.php` | CLI version of the cleanup job. |
| `extension/sevenx_authentication_2fa/bin/php/sevenx2fabuild.php` | Generate a new OAuth handler skeleton by parameters. |
| `extension/sevenx_authentication_2fa/bin/php/sevenx2faconfig.php` | Configure provider credentials and endpoints interactively. |

## INI Settings

See `settings/sevenxauthentication2fa.ini.append.php` for:

- `General/Enabled`, `General/Enforce2FA`, `General/DefaultMethod`
- `CodeSettings/Length`, `CodeSettings/TimeStep`, `CodeSettings/Window`, `CodeSettings/EmailTTL`
- `EmailSettings/Subject`, `EmailSettings/Sender`
- `EmailSettings/Body` — optional plain-text override. Supports `{code}`, `{expires}`, `{site_url}` and `{verify_url}`. If empty/commented, the template is used.
- `SocialLogin/Enabled`, `SocialLogin/AutoCreateUser`, `SocialLogin/DefaultUserGroupNodeID`
- Provider blocks (`Google`, `Facebook`, `TwitterX`, `Instagram`, `Meta`, `Idme`) with `ClientID`, `ClientSecret`, `Scope`, endpoints
- `Idme` also supports `Op`, `EID`, `Nonce` and `UsePKCE`

## E-mail template

The OTP e-mail body is rendered from:

```
extension/sevenx_authentication_2fa/design/standard/templates/mail/2fa_code.tpl
```

Override it by placing a copy in your site design. Available variables:
`{$code}`, `{$expires}`, `{$site_url}`, `{$verify_url}`.

## Adding a New OAuth Provider

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

The command creates:

- `extension/sevenx_authentication_2fa/login_handler/ezmyprovideruser.php`
- A matching INI block in `settings/sevenxauthentication2fa.ini.append.php`

## Notes

- The TOTP implementation is dependency-free and uses only `hash_hmac` and PHP built-ins.
- Pending 2FA challenges are stored in the current session and in the filesystem cache. No Valkey/Redis server is required.
- The e-mail OTP body is rendered from a template and supports a resumable `/user2fa/verify/code/<code>` link.
- The OAuth handlers are skeletons; real integrations need valid OAuth credentials and may require provider-specific adjustments.
- No plaintext password is stored during the 2FA challenge; only the user ID, method, TOTP secret or e-mail code, and redirect URI are kept in session and filesystem cache.
