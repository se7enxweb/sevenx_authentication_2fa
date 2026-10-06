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
        <p class="full-page-header-text">{if $is_pending_setup}{'Your password was right. This site asks every account for a second step at sign-in; set it up now to continue.'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'After your password, a code from your phone or your e-mail. Your password alone is then useless to anyone else.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
    </div>
</header>

<div class="full-form-content acc-content">
    <div class="container acc tfa-m">
        {if $is_pending_setup|not}<p class="acc-crumb"><a href={'user/edit'|ezurl}>{'My account'|i18n( 'extension/sevenx_authentication_2fa' )}</a></p>{/if}

        {if $success}<div class="acc-notice success" role="status"><p>{$success|wash}</p></div>{/if}
        {if $error}<div class="acc-notice error" role="alert" id="tfa-setup-error"><p>{$error|wash}</p></div>{/if}

{if $can_store|not}
        <div class="acc-notice warning" role="alert"><p>{'This account cannot keep a second step: its user class has no two-factor field. Ask the site administrator.'|i18n( 'extension/sevenx_authentication_2fa' )}</p></div>
{else}
        {if $is_pending_setup|not}
        <div class="acc-card tfa-state">
            <p><strong>{'Second step at sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}:</strong>
               <span class="tfa-pill{if $active} is-on{/if}">{if eq( $method_now, 'totp' )}{'Authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $method_now, 'email' )}{'E-mail codes'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Off'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</span></p>
            <p class="tfa-hint">{if $is_enforced}{'Required on this site.'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif $active}{'Set up on %date.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%date', $data.created_at|l10n( shortdate ) ) )}{else}{'Your choice: it is off now.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
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
                {if $is_pending_setup}<a class="btn btn-outline" href={'user/login'|ezurl}>{'Cancel'|i18n( 'extension/sevenx_authentication_2fa' )}</a>{else}<a class="btn btn-outline" href={'user/edit'|ezurl}>{'Back to my account'|i18n( 'extension/sevenx_authentication_2fa' )}</a>{/if}
            </div>
        </form>

        {if and( ne( $method_now, 'disabled' ), $is_enforced|not, $is_pending_setup|not )}
        <div class="acc-card" style="margin-top: 2rem;">
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
<script>
{literal}
(function () {
    var form = document.getElementById( 'tfa-setup-form' ), enrol = document.getElementById( 'tfa-enrol' );
    if ( !form || !enrol ) return;
    function update() {
        var picked = form.querySelector( 'input[name="Method"]:checked' );
        enrol.hidden = !picked || picked.value !== 'totp';
    }
    form.addEventListener( 'change', function ( e ) { if ( e.target && e.target.name === 'Method' ) update(); } );
    update();
})();
{/literal}
</script>
{/if}
{undef $active $method_now $show_enrol}
