# Installing and configuring sevenx_authentication_2fa

This document explains how to obtain OAuth credentials for each supported
provider and how to configure the extension.

## Extension activation

1. Activate the extension in `settings/override/site.ini.append.php`:

```ini
[ExtensionSettings]
ActiveExtensions[]=sevenx_authentication_2fa
```

2. Enable the login handler in `settings/override/site.ini.append.php`:

```ini
[UserSettings]
ExtensionDirectory[]=sevenx_authentication_2fa
LoginHandler[user2fa]=sevenxUser2fa
LoginHandler[]=standard
```

3. Regenerate autoloads and clear caches:

```bash
php bin/php/ezpgenerateautoloads.php
php bin/php/ezcache.php --clear-all --allow-root-user
```

## Two-factor authentication

TOTP and e-mail 2FA work without third-party credentials. Users can configure
them at `/user2fa/setup` once logged in.

## Social login (OAuth)

For every OAuth provider the extension needs a `ClientID` and `ClientSecret`
from the provider's developer console. The redirect/callback URI is always:

```text
https://<your-domain>/user2fa/callback/<provider>
```

For `alpha.se7enx.com` the callback URIs are:

- Google: `https://alpha.se7enx.com/user2fa/callback/google`
- Facebook / Meta: `https://alpha.se7enx.com/user2fa/callback/facebook`
- Instagram: `https://alpha.se7enx.com/user2fa/callback/instagram`
- X / Twitter: `https://alpha.se7enx.com/user2fa/callback/twitterx`
- ID.me: `https://alpha.se7enx.com/user2fa/callback/idme`

You can either edit `settings/override/sevenxauthentication2fa.ini.append.php`
manually or use the bundled CLI tool:

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2faconfig.php \
    --provider=Google \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_CLIENT_SECRET \
    --allow-root-user
```

The tool is also registered in `bin/php/console`:

```bash
./bin/php/console ext:sevenx_authentication_2fa:sevenx2faconfig \
    --provider=Google \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_CLIENT_SECRET \
    --allow-root-user
```

To update a siteaccess-specific override instead of the global override:

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2faconfig.php \
    --siteaccess=sevenx_site_user \
    --provider=Google \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_CLIENT_SECRET \
    --allow-root-user
```

For automated/non-interactive use, add `--no-prompt` and the script will use
built-in endpoint defaults for the supported providers. The client secret can
also be supplied via the environment variable `SEVENX_2FA_CLIENT_SECRET` to keep
it out of shell history.

Add the values to `settings/override/sevenxauthentication2fa.ini.append.php`:

```ini
[SocialLogin]
Enabled=enabled
AutoCreateUser=disabled

[Google]
Enabled=enabled
ClientID=your-client-id
ClientSecret=your-client-secret
```

ID.me uses PKCE by default and requires a community scope (for example
`military`, `student`, `teacher`, `responder`, `government`):

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2faconfig.php \
    --provider=IDme \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_CLIENT_SECRET \
    --scope=military \
    --op=signin \
    --allow-root-user
```

### Google

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create or select a project.
3. Open **APIs & services > Credentials**.
4. Click **Create credentials > OAuth client ID**.
5. If this is the first OAuth client, configure the consent screen:
   - choose **External** or **Internal**, set an app name, support email and
     authorized domain.
6. For **Application type** select **Web application**.
7. Add the callback URI to **Authorized redirect URIs**:
   `https://alpha.se7enx.com/user2fa/callback/google`.
8. Click **Create**. Copy the **Client ID** and **Client Secret** into the
   `[Google]` block.

### Facebook / Meta

1. Go to the [Meta for Developers](https://developers.facebook.com/) portal.
2. Create a new app and select **None** or **Other** as the app type.
3. In the app dashboard, open **Settings > Basic** to find the **App ID** and
   **App Secret**.
4. Open **Products > Facebook Login > Settings**.
5. Add the callback URI to **Valid OAuth Redirect URIs**:
   `https://alpha.se7enx.com/user2fa/callback/facebook`.
6. Set **Strict Mode for redirect URIs** to **Yes** for security.
7. Copy the App ID and App Secret into the `[Facebook]` or `[Meta]` block.

Facebook and Meta use the same Graph API endpoints. You can use either the
`[Facebook]` or `[Meta]` block; choose the one that matches your business
registration.

### Instagram

Instagram consumer-login uses the Instagram Basic Display API, which is managed
through the Meta developer portal.

1. Go to the [Meta for Developers](https://developers.facebook.com/) portal and
   create an app.
2. Add the **Instagram Basic Display** product to the app.
3. Open **Instagram Basic Display > Basic Display** in the left menu.
4. Scroll to **Valid OAuth Redirect URIs** and add:
   `https://alpha.se7enx.com/user2fa/callback/instagram`.
5. Copy the **Instagram App ID** and **Instagram App Secret** into the
   `[Instagram]` block.
6. Add at least one test Instagram account under **User Token Generator** if the
   app is not in live mode.

### X (Twitter)

1. Go to the [X Developer Portal](https://developer.x.com/en/portal/dashboard).
2. Sign in and select **Projects & Apps > Create App**.
3. Choose **Web App, Automated App or Bot** (confidential client).
4. Fill in the app name and website.
5. Under **Authentication settings > OAuth 2.0** enable **Authorization code**.
6. Add the callback URI to **Callback URLs / Redirect URIs**:
   `https://alpha.se7enx.com/user2fa/callback/twitterx`.
7. Copy the **Client ID** and **Client Secret** into the `[TwitterX]` block.

X requires the email permission to be explicitly requested if you need the
user's email address. The extension's default `Scope` is `users.read tweet.read`;
add `users.read tweet.read offline.access` and request email access in the app
permissions if needed.

### ID.me

1. Register or sign in at the [ID.me Developer Portal](https://developers.id.me/).
2. Create a new application and fill in the partner details.
3. Add the callback URI to the **Redirect URIs** list:
   `https://alpha.se7enx.com/user2fa/callback/idme`.
4. Copy the **Client ID** and **Client Secret** into the `[Idme]` block.
5. Set the `Scope` to the ID.me community you want to verify, for example
   `military`, `student`, `teacher`, `responder`, `government`, `employee`,
   `hospital_employee`, `alumni`, `nurse`, `medical`, or one of the `_canada`
   variants. For OIDC flows you can add `openid` in addition to the community.
6. Set `Op` to `signin` or `signup` (default: `signin`).
7. Optionally set `EID` to an external identifier and `Nonce` for OIDC replay
   protection.
8. `UsePKCE` is `enabled` by default for ID.me and should not be disabled.

The bundled handler parses both the OAuth 2.0 `attributes.json` response and the
OIDC `/userinfo` JSON response. It extracts `uuid`/`sub` as the user id,
`email`, and the first/last name for account creation.

## Custom providers

The extension ships with a skeleton builder that generates a new OAuth handler
class and a matching `.ini` block.

```bash
php extension/sevenx_authentication_2fa/bin/php/sevenx2fabuild.php \
    --provider=MyProvider \
    --class=MyProvider \
    --client-id=YOUR_CLIENT_ID \
    --client-secret=YOUR_CLIENT_SECRET \
    --authorization-url=https://provider.example.com/oauth/authorize \
    --token-url=https://provider.example.com/oauth/token \
    --userinfo-url=https://provider.example.com/api/user \
    --scope=openid email profile
```

This creates `extension/sevenx_authentication_2fa/login_handler/ezmyprovideruser.php`
and prints the INI block to add to
`settings/override/sevenxauthentication2fa.ini.append.php`.

### Manual custom provider

1. Create a class that extends `eZOAuthUser`:

```php
class eZMyProviderUser extends eZOAuthUser
{
    protected $provider = 'myprovider';

    protected function normalizeUserInfo( $data )
    {
        return array(
            'id'    => $data['id'],
            'email' => $data['email'],
            'name'  => $data['name'],
        );
    }
}
```

2. Add a matching INI block:

```ini
[MyProvider]
Enabled=enabled
ClientID=your-client-id
ClientSecret=your-client-secret
Scope=openid email profile
AuthorizationURL=https://provider.example.com/oauth/authorize
TokenURL=https://provider.example.com/oauth/token
UserInfoURL=https://provider.example.com/api/user
```

3. Add the provider to `settings/override/sevenxauthentication2fa.ini.append.php`
   under `[SocialProviders]` if you want it to appear in the `SocialProviders`
   list used by templates.

## Auto-create users

To create local eZ Publish accounts automatically for first-time social logins,
enable it in the INI:

```ini
[SocialLogin]
AutoCreateUser=enabled
DefaultUserGroupNodeID=12
```

When disabled, users who do not already have an account with a matching email
will be redirected back to the login page and an `oauth_user_not_found_no_autocreate`
entry will be written to `var/log/auth.log`.

## Security audit logging

All authentication events are logged to `var/log/auth.log` and to ezdebug. See
`README.md` or `classes/sevenxauthentication2fahelper.php` for the list of
action tags.
