# The two-factor pages

This page is for designers and site builders: which pages and parts the
extension draws, where each template lives, and how to give a design of your
own the same look. Security behaviour is in the README (Security).

## The pages and their states

| View | What it shows | States |
|---|---|---|
| `user2fa/verify` | The second step after the password: one code field, **Verify and sign in**, **Send a new code** for e-mail codes, the way back to the login form | `verify`, `failed` (wrong code, tries left), `locked` (too many wrong codes or a locked account), `expired` (nothing pending in this browser), `elsewhere` (an e-mail link opened in another browser) |
| `user2fa/setup` | The state, the choice of method, the authenticator enrolment (QR code drawn on the server, the key in groups of four, the confirming code), the confirmation with a current code before moving away from a confirmed authenticator, and the removal behind a confirmation | signed in; a first setup before signing in when `Enforce2FA` asks for one (`$is_pending_setup`) |
| `user2fa/oauth`, `user2fa/callback` | Only when a social login does not sign anyone in | `cancelled`, `state`, `not_configured`, `unknown_provider`, `no_account`, `unverified_email`, `not_allowed`, `failed` |

Where the siteaccess draws its own login page (`[SiteSettings] LoginPage=custom`,
the administration) `verify`, `callback` and a first setup are drawn by
`loginpagelayout.tpl`, like the login form before them. Elsewhere they are
ordinary pages of the site.

Form fields and button names are fixed by the views: `Code`, `VerifyButton`,
`ResendButton`; `Method` (`totp`, `email`, `disabled`), `Code`, `Secret`,
`SetupButton`, `ResetButton`. Every page works without JavaScript; the only
script hides the enrolment while another method is chosen.

## Where the templates live

| Template | Used by |
|---|---|
| `design/standard/templates/user2fa/verify.tpl`, `setup.tpl`, `callback.tpl`, `exp_style.tpl` | Every design without its own: admin4 (light and dark), admin3, admin2, admin, the Admin UI, and public designs such as ezwebin or simple. `exp_style.tpl` is the module-local stylesheet, scoped to `.exp-2fa`, in the language of the redesigned admin pages; it takes admin4's tokens (`--a4-*`) where they exist and follows admin4's dark sign-in page (`html[data-a4-theme="dark"]`). |
| `design/media/templates/user2fa/verify.tpl`, `setup.tpl`, `callback.tpl`, `parts/style.tpl` | The media design (sevenx_themes_media): its page header, form column, account cards, notices and buttons (`full-page-header`, `acc-card`, `acc-notice`, `btn`), plus `parts/style.tpl` for the parts it has no furniture for (code field, method choice, QR code, steps, social buttons), scoped to `.tfa-m`. The media design has no dark mode. |
| `design/standard/templates/user2fa/parts/social_buttons.tpl` | The social login buttons, one per enabled provider; included by login and registration pages. |
| `design/standard/templates/user2fa/parts/account_link.tpl` | The state of the second step with a link to the setup, for profile pages (`style='box'` or `style='item'`). |
| `design/admin/templates/content/datatype/edit/sevenxauthentication2fa.tpl` | The 2FA field in the administration's content edit form. |
| `design/media/templates/content/datatype/edit/sevenxauthentication2fa.tpl` | The 2FA field in the media design's registration and edit forms. |
| `design/standard/templates/content/datatype/edit/…` and `…/view/sevenxauthentication2fa.tpl` | The field elsewhere; the view template shows the state only, never a key. |

The media templates are in this extension rather than in sevenx_themes_media:
they belong to the module, which only exists where the extension is active, so
the theme carries no templates for a module it may not have. The theme only
hooks the extension's partials into its own login, registration and profile
pages, guarded by `{if ezmodule( 'user2fa/oauth' )}` (or `user2fa/setup`), so
nothing changes where the extension is not active. The admin4 login page of
the kernel and the Admin UI's login page do the same.

## A design of your own

Copy `design/standard/templates/user2fa/*.tpl` (or the media ones, if your
design is built like media) into `design/<yours>/templates/user2fa/`, keep the
field and button names, and swap the furniture. To add the social buttons to
your login page:

```
{if ezmodule( 'user2fa/oauth' )}
    {include uri='design:user2fa/exp_style.tpl'}
    {include uri='design:user2fa/parts/social_buttons.tpl' context='login' redirect=$User:redirect_uri}
{/if}
```

## Checked

Light and dark (admin4), at 1440 pixels, at 960 pixels with device scale 2,
and at 390 pixels for the media design, with no sideways scrolling; text has at
least 4.5:1 contrast, every control is labelled, errors are announced
(`role="alert"`) and tied to their field (`aria-describedby`, `aria-invalid`).
