{* Two-step sign-in (user2fa/setup) in the media design: the design's page header, the state, the choice of method,
   the authenticator enrolment (a QR code drawn on this server, the key in groups of four, the code that confirms
   it) and removing the second step behind a confirmation, in account cards with a link back to My account. A
   first setup that Enforce2FA asks for before signing in ($is_pending_setup) has the same form and ends with
   signing in.

   The form, its action and its fields are those the view reads: Method (totp, email, disabled), Code, Secret,
   SetupButton, and ResetButton. Moving away from a confirmed authenticator needs a current code from it
   ($needs_confirmation). Works without javascript; the script only hides the enrolment while another method is
   chosen. Drawn by stylesheets/account.css and user2fa/parts/style.tpl.
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/parts/style.tpl'}
{def $active = $data.is_active
     $method_now = cond( $active, $data.method, 'disabled' )
     $show_enrol = and( $can_store, $needs_confirmation|not, ne( $secret, '' ) )}

<header class="full-page-header acc-header text-center no-breadcrumbs">
    <div class="container">
        <h1 class="full-page-title">{if $is_pending_setup}{'Set up a second step'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Two-step sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</h1>
        <p class="full-page-header-text">{if $is_pending_setup}{'Your password was right. This site asks your account for a second step at sign-in; set it up now to continue.'|i18n( 'extension/sevenx_authentication_2fa' )}{if gt( $grace_days_left, 0 )} {'You may also set it up later: for %days more days the password alone still signs you in.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%days', $grace_days_left ) )}{/if}{else}{'After your password, a code from your phone or your e-mail. Your password alone is then useless to anyone else.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
    </div>
</header>

<div class="full-form-content acc-content">
    <div class="container acc tfa-m">
        {if $is_pending_setup|not}<p class="acc-crumb"><a href={'user/edit'|ezurl}>{'My account'|i18n( 'extension/sevenx_authentication_2fa' )}</a></p>{/if}

        {if $success}<div class="acc-notice success" role="status"><p>{$success|wash}</p></div>{/if}
        {if $error}<div class="acc-notice error" role="alert" id="tfa-setup-error"><p>{$error|wash}</p></div>{/if}
        {if $recovery_codes}
        <div class="acc-card">
            <h2>{'Your recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
            <p class="acc-lead">{'If you lose your phone, each of these codes signs you in once in place of the app\'s code. Print them or keep them in a password manager now: they are shown only this once.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            <ol class="tfa-recovery">{foreach $recovery_codes as $recovery_code}<li><code>{$recovery_code|wash}</code></li>{/foreach}</ol>
            {if $continue_url}<div class="acc-actions"><a class="btn btn-primary" href={$continue_url|ezurl}>{'I have saved them, continue'|i18n( 'extension/sevenx_authentication_2fa' )}</a></div>{/if}
        </div>
        {/if}

{if $can_store|not}
        <div class="acc-notice warning" role="alert"><p>{'This account cannot keep a second step: its user class has no two-factor field. Ask the site administrator.'|i18n( 'extension/sevenx_authentication_2fa' )}</p></div>
{else}
        {if $is_pending_setup|not}
        <div class="acc-card tfa-state">
            <p><strong>{'Second step at sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}:</strong>
               <span class="tfa-pill{if $active} is-on{/if}">{if eq( $method_now, 'totp' )}{'Authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $method_now, 'email' )}{'E-mail codes'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Off'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</span></p>
            <p class="tfa-hint">{if $is_enforced}{'Required on this site.'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif $active}{'Set up on %date.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%date', $data.created_at|l10n( shortdate ) ) )}{else}{'Your choice: it is off now.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
            {if and( $needs_confirmation, $recovery_enabled )}<p class="tfa-hint">{'Recovery codes left'|i18n( 'extension/sevenx_authentication_2fa' )}: {$recovery_codes_left}</p>{/if}
        </div>
        {/if}

        <form method="post" action={'user2fa/setup'|ezurl} id="tfa-setup-form" class="embed-form" novalidate>
            <div class="acc-card">
                <fieldset class="tfa-methods">
                    <legend>{'How do you want to confirm it is you?'|i18n( 'extension/sevenx_authentication_2fa' )}</legend>
                    {foreach $allowed_methods as $choice}
                    <label class="tfa-method">
                        <input type="radio" name="Method" value="{$choice|wash}"{if eq( $choice, $selected_method )} checked{/if} />
                        <span>
                        {if eq( $choice, 'totp' )}
                            <strong>{'Authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}</strong>
                            <span>{'A code from an app such as Google Authenticator, Microsoft Authenticator, Authy or 1Password. Works offline. Recommended.'|i18n( 'extension/sevenx_authentication_2fa' )}</span>
                        {elseif eq( $choice, 'email' )}
                            <strong>{'E-mail code'|i18n( 'extension/sevenx_authentication_2fa' )}</strong>
                            <span>{'A code sent to %email at each sign-in.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%email', $masked_email ) )|wash}</span>
                        {else}
                            <strong>{'Off'|i18n( 'extension/sevenx_authentication_2fa' )}</strong>
                            <span>{'The password alone signs in.'|i18n( 'extension/sevenx_authentication_2fa' )}</span>
                        {/if}
                        </span>
                    </label>
                    {/foreach}
                </fieldset>
            </div>

            {if $show_enrol}
            <div class="acc-card" id="tfa-enrol">
                <h2>{'Set up your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
                <ol class="tfa-steps">
                    <li class="tfa-step">
                        <h3>{'Scan the code'|i18n( 'extension/sevenx_authentication_2fa' )}</h3>
                        <p>{'In the app, add an account and scan this QR code. It is drawn on this server; the key is not sent anywhere else.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                        <div class="tfa-pair">
                            {if $qr_code}<figure class="tfa-qr"><img src="{$qr_code}" width="180" height="180" alt="{'QR code for your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}" /></figure>{/if}
                            <dl class="tfa-key">
                                <dt>{'Or type this key'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
                                <dd><code class="tfa-secret">{$secret_grouped|wash}</code></dd>
                                <dt>{'Account'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
                                <dd>{$issuer|wash}: {$account_name|wash}</dd>
                            </dl>
                        </div>
                    </li>
                    <li class="tfa-step">
                        <h3><label for="tfa-code">{'Enter the code the app shows'|i18n( 'extension/sevenx_authentication_2fa' )}</label></h3>
                        <p id="tfa-code-help">{'This confirms the app is set up right. Until then nothing changes.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                        <input id="tfa-code" class="form-control tfa-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12"
                               autocomplete="one-time-code" spellcheck="false" aria-describedby="{if $error}tfa-setup-error {/if}tfa-code-help"{if $error} aria-invalid="true"{/if} />
                    </li>
                </ol>
                <input type="hidden" name="Secret" value="{$secret|wash}" />
            </div>
            {elseif $needs_confirmation}
            <div class="acc-card">
                <h2>{'Your authenticator app is set up'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
                <p class="acc-lead">{'To switch to e-mail codes or turn the second step off, confirm with the code your app shows now. Its key is never shown again; to move to a new phone, remove the second step and set it up there.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                <div class="form-group">
                    <label for="tfa-code" class="form-label">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="tfa-code" class="form-control tfa-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12"
                           autocomplete="one-time-code" spellcheck="false"{if $error} aria-invalid="true" aria-describedby="tfa-setup-error"{/if} />
                </div>
            </div>
            {/if}

            <div class="acc-actions">
                <button type="submit" class="btn btn-primary" name="SetupButton" value="1">{if $is_pending_setup}{'Save and sign in'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Save settings'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</button>
                {if gt( $grace_days_left, 0 )}<button type="submit" class="btn btn-secondary" name="LaterButton" value="1" formnovalidate>{'Set up later'|i18n( 'extension/sevenx_authentication_2fa' )}</button>{/if}
                {if $is_pending_setup}<a class="btn btn-outline" href={'user/login'|ezurl}>{'Cancel'|i18n( 'extension/sevenx_authentication_2fa' )}</a>{else}<a class="btn btn-outline" href={'user/edit'|ezurl}>{'Back to my account'|i18n( 'extension/sevenx_authentication_2fa' )}</a>{/if}
            </div>
        </form>

        {if and( $needs_confirmation, $recovery_enabled, $is_pending_setup|not )}
        <div class="acc-card">
            <h2>{'Recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
            <p class="acc-lead">{'One-time codes that sign you in when your phone is lost. Making new ones makes the old ones stop working.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            <details class="tfa-confirm">
                <summary>{'Make new recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</summary>
                <form method="post" action={'user2fa/setup'|ezurl} class="embed-form" novalidate>
                    <div class="form-group">
                        <label for="tfa-recovery-code" class="form-label">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                        <input id="tfa-recovery-code" class="form-control tfa-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
                    </div>
                    <div class="acc-actions">
                        <button type="submit" class="btn btn-outline" name="RecoveryButton" value="1">{'Make new recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
                    </div>
                </form>
            </details>
        </div>
        {/if}

        {if and( $app_passwords_enabled, $active )}
        <div class="acc-card" id="app-passwords">
            <h2>{'App passwords'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
            <p class="acc-lead">{'For a WebDAV drive, a script or an app that signs in with a password and cannot ask for the second step. Each one works only where you allow it, and you can revoke it at any time.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            {if $new_app_password}
            <div role="status">
                <h3>{'Your new app password for %name'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%name', $new_app_password_name ) )|wash}</h3>
                <ol class="tfa-recovery"><li><code>{$new_app_password|wash}</code></li></ol>
                <p class="acc-lead">{'Type or paste it in place of your password in that client. Copy it now: it is shown only this once.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            </div>
            {/if}
            {if $app_passwords}
            <ul class="tfa-apppw-list">
            {foreach $app_passwords as $app_password}
                <li>
                    <span><strong>{$app_password.name|wash}</strong><br />
                    <small>{foreach $app_password.channels as $channel}{if eq( $channel, 'rest' )}{'REST interface'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $channel, 'webdav' )}{'WebDAV'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'SOAP'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}{delimiter}, {/delimiter}{/foreach}
                    &middot; {'Made'|i18n( 'extension/sevenx_authentication_2fa' )} {$app_password.created|l10n( shortdate )}
                    &middot; {if $app_password.last_used}{'Last used'|i18n( 'extension/sevenx_authentication_2fa' )} {$app_password.last_used|l10n( shortdatetime )}{else}{'Never used'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
                    &middot; {if $app_password.expired}{'Expired'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif $app_password.expires}{'Expires'|i18n( 'extension/sevenx_authentication_2fa' )} {$app_password.expires|l10n( shortdate )}{else}{'Works until revoked'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</small></span>
                    <form method="post" action={'user2fa/setup'|ezurl} class="embed-form" novalidate>
                        <input type="hidden" name="AppPasswordID" value="{$app_password.id|wash}" />
                        <button type="submit" class="btn btn-outline" name="AppPasswordRevokeButton" value="1">{'Revoke'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
                    </form>
                </li>
            {/foreach}
            </ul>
            {else}
            <p class="acc-lead">{'No app passwords yet.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            {/if}
            {if lt( $app_passwords|count, $app_passwords_max )}
            <details class="tfa-confirm tfa-neutral"{if and( $error, $new_app_password|not )} open{/if}>
                <summary>{'Make an app password'|i18n( 'extension/sevenx_authentication_2fa' )}</summary>
                <form method="post" action={'user2fa/setup'|ezurl} class="embed-form" novalidate>
                    <div class="form-group">
                        <label for="tfa-apppw-name" class="form-label">{'Name'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                        <input id="tfa-apppw-name" class="form-control" type="text" name="AppPasswordName" value="" maxlength="60" autocomplete="off" />
                    </div>
                    <fieldset class="form-group tfa-apppw-scopes">
                        <legend class="form-label">{'Where it may be used'|i18n( 'extension/sevenx_authentication_2fa' )}</legend>
                        {foreach $app_password_channels as $channel}
                        <label><input type="checkbox" name="AppPasswordChannels[]" value="{$channel|wash}" /> {if eq( $channel, 'rest' )}{'REST interface'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $channel, 'webdav' )}{'WebDAV'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'SOAP'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</label>
                        {/foreach}
                    </fieldset>
                    {if $needs_confirmation}
                    <div class="form-group">
                        <label for="tfa-apppw-code" class="form-label">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                        <input id="tfa-apppw-code" class="form-control tfa-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
                    </div>
                    {/if}
                    <div class="acc-actions">
                        <button type="submit" class="btn btn-primary" name="AppPasswordCreateButton" value="1">{'Make an app password'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
                    </div>
                </form>
            </details>
            {/if}
            <p class="acc-lead">{if gt( $app_password_expiry_days, 0 )}{'You can have up to %count app passwords. A new one works for %days days.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%count', $app_passwords_max, '%days', $app_password_expiry_days ) )}{else}{'You can have up to %count app passwords. A new one works until you revoke it.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%count', $app_passwords_max ) )}{/if}</p>
        </div>
        {/if}

        {if and( ne( $method_now, 'disabled' ), $is_enforced|not, $is_pending_setup|not )}
        <div class="acc-card tfa-reset-card">
            <h2>{'Reset two-factor authentication'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
            <p class="acc-lead">{'Use this button only if you want to remove your existing 2FA configuration permanently.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            <details class="tfa-confirm">
                <summary>{'Reset 2FA'|i18n( 'extension/sevenx_authentication_2fa' )}</summary>
                <form method="post" action={'user2fa/setup'|ezurl} class="embed-form" novalidate>
                    <p>{'This removes the second step: the password alone will sign in until you set it up again. An app entry for this site stops working.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                    {if $needs_confirmation}
                    <div class="form-group">
                        <label for="tfa-reset-code" class="form-label">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                        <input id="tfa-reset-code" class="form-control tfa-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
                    </div>
                    {/if}
                    <div class="acc-actions">
                        <button type="submit" class="btn btn-outline" name="ResetButton" value="1">{'Remove the second step'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
                    </div>
                </form>
            </details>
        </div>
        {/if}
{/if}
    </div>
</div>

{if $show_enrol}
{* the enrolment is shown while "totp" is picked: sevenx_authentication_2fa_setup.js, no inline script *}
{ezscript( array( 'sevenx_authentication_2fa_setup.js' ) )}
{/if}
{undef $active $method_now $show_enrol}
