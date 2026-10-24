{* Setting up, changing and removing the second step of signing in (user2fa/setup) in every design without its own
   (the administration designs, the Admin UI, and public designs other than media, which has design/media).

   Signed in: a page in the admin's main card with the state at a glance, the choice of method, the authenticator
   enrolment (a QR code drawn on this server, the key in groups of four, the code that confirms it), and removing
   the second step behind a confirmation. A first setup that Enforce2FA asks for before signing in
   ($is_pending_setup) is drawn by loginpagelayout.tpl in the sign-in frame, with the same form.

   The form, its action and its fields are those the view reads: Method (totp, email, disabled), Code, Secret
   (the key shown, so a page that went out of date is noticed), SetupButton, ResetButton, RecoveryButton (new
   recovery codes, with a current code) and, while a grace period lasts ($grace_days_left), LaterButton. The
   recovery codes ($recovery_codes) are shown once, right after they were made; $continue_url goes on to where a
   first sign-in was going. Moving away from a
   confirmed authenticator needs a current code from it ($needs_confirmation). Works without javascript; the
   script only hides the enrolment while another method is chosen.
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/exp_style.tpl'}
{def $active = $data.is_active
     $method_now = cond( $active, $data.method, 'disabled' )
     $show_enrol = and( $can_store, $needs_confirmation|not, ne( $secret, '' ) )
     $setup_node = first_set( $setup_user.contentobject.main_node, false() )}

{if $is_pending_setup}
<div class="exp-2fa is-signin">
<div class="exp-2fa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28"><path d="M12 2.5 4.5 5.5v6c0 4.6 3.1 8.6 7.5 10 4.4-1.4 7.5-5.4 7.5-10v-6L12 2.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 8v4m0 3.5v.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
<h1 class="exp-2fa-title">{'Set up a second step'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
<p class="exp-2fa-sub">{'Your password was right. This site asks your account for a second step at sign-in; set it up now to continue.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
{if gt( $grace_days_left, 0 )}<p class="exp-2fa-sub">{'You may also set it up later: for %days more days the password alone still signs you in.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%days', $grace_days_left ) )}</p>{/if}
{else}
<div class="context-block exp-2fa">
<div class="box-header"><div class="box-ml">
<div class="exp-title-row">
<h1 class="context-title">{'Two-Factor Authentication Setup'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
</div>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
<p class="exp-intro">{'A second step makes your password alone useless to anyone else: after the password, the sign-in asks for a code from an authenticator app on your phone, or one sent to your e-mail address.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
{/if}

{if $success}<div class="exp-feedback is-ok" role="status"><p>{$success|wash}</p></div>{/if}
{if $error}<div class="exp-feedback is-bad" role="alert" id="tfa-setup-error"><p>{$error|wash}</p></div>{/if}
{if $recovery_codes}
<section class="exp-panel" aria-labelledby="tfa-recovery-title">
    <div class="exp-panel-head"><h2 class="exp-h2" id="tfa-recovery-title">{'Your recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</h2></div>
    <p class="exp-muted">{'If you lose your phone, each of these codes signs you in once in place of the app\'s code. Print them or keep them in a password manager now: they are shown only this once.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
    <ol class="exp-recovery">{foreach $recovery_codes as $recovery_code}<li><code>{$recovery_code|wash}</code></li>{/foreach}</ol>
    {if $continue_url}<p><a class="exp-btn exp-btn-primary" href={$continue_url|ezurl}>{'I have saved them, continue'|i18n( 'extension/sevenx_authentication_2fa' )}</a></p>{/if}
</section>
{/if}
{if and( $is_enforced, $is_pending_setup|not )}<div class="exp-feedback is-info"><p>{'Two-factor authentication is required on this site. You must choose an authentication method.'|i18n( 'extension/sevenx_authentication_2fa' )}</p></div>{/if}

{if $can_store|not}
<div class="exp-feedback is-warn" role="alert"><p>{'This account cannot keep a second step: its user class has no two-factor field. Ask the site administrator.'|i18n( 'extension/sevenx_authentication_2fa' )}</p></div>
{else}

{if $is_pending_setup|not}
<section aria-labelledby="tfa-state-title">
<h2 class="exp-sr" id="tfa-state-title">{'State'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
<ul class="exp-figures">
    <li class="exp-figure{if $active} is-ok{else} is-attention{/if}"><strong>{if eq( $method_now, 'totp' )}{'Authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $method_now, 'email' )}{'E-mail codes'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Off'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</strong> <span>{'Second step at sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}</span></li>
    <li class="exp-figure"><strong>{if $is_enforced}{'Required'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Your choice'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</strong> <span>{'On this site'|i18n( 'extension/sevenx_authentication_2fa' )}</span></li>
    {if $active}<li class="exp-figure"><strong>{$data.created_at|l10n( shortdate )}</strong> <span>{'Set up on'|i18n( 'extension/sevenx_authentication_2fa' )}</span></li>{/if}
    {if and( $needs_confirmation, $recovery_enabled )}<li class="exp-figure{if eq( $recovery_codes_left, 0 )} is-attention{/if}"><strong>{$recovery_codes_left}</strong> <span>{'Recovery codes left'|i18n( 'extension/sevenx_authentication_2fa' )}</span></li>{/if}
</ul>
</section>
{/if}

<form method="post" action={'user2fa/setup'|ezurl} id="tfa-setup-form" novalidate="novalidate">

<div class="exp-panel">
<fieldset class="exp-methods">
    <legend>{'Second step at sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}</legend>
    <div class="exp-method-list">
    {foreach $allowed_methods as $choice}
    <label class="exp-method">
        <input type="radio" name="Method" value="{$choice|wash}"{if eq( $choice, $selected_method )} checked="checked"{/if} />
        <span>
        {if eq( $choice, 'totp' )}
            <strong>{'Authenticator app (TOTP)'|i18n( 'extension/sevenx_authentication_2fa' )}</strong>
            <span>{'A code from an app such as Google Authenticator, Microsoft Authenticator, Authy or 1Password. Works offline. Recommended.'|i18n( 'extension/sevenx_authentication_2fa' )}</span>
        {elseif eq( $choice, 'email' )}
            <strong>{'E-mail one-time code'|i18n( 'extension/sevenx_authentication_2fa' )}</strong>
            <span>{'A code sent to %email at each sign-in.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%email', $masked_email ) )|wash}</span>
        {else}
            <strong>{'Off'|i18n( 'extension/sevenx_authentication_2fa' )}</strong>
            <span>{'The password alone signs in.'|i18n( 'extension/sevenx_authentication_2fa' )}</span>
        {/if}
        </span>
    </label>
    {/foreach}
    </div>
</fieldset>
</div>

{if $show_enrol}
<div class="exp-panel" id="tfa-enrol" data-tfa-for="totp">
    <div class="exp-panel-head"><h2 class="exp-h2">{'Set up your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}</h2></div>
    <ol class="exp-steps">
        <li class="exp-step">
            <h3>{'Scan the code'|i18n( 'extension/sevenx_authentication_2fa' )}</h3>
            <p class="exp-muted">{'In the app, add an account and scan this QR code. It is drawn on this server; the key is not sent anywhere else.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            <div class="exp-pair">
                {if $qr_code}<figure class="exp-qr"><img src="{$qr_code}" width="180" height="180" alt="{'QR code for your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}" /></figure>{/if}
                <dl class="exp-key">
                    <dt>{'Or type this key'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
                    <dd><code class="exp-secret">{$secret_grouped|wash}</code></dd>
                    <dt>{'Account'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
                    <dd>{$issuer|wash}: {$account_name|wash}</dd>
                    <dt>{'Type'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
                    <dd>{'Time based, %digits digits, every %period seconds'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%digits', $digits, '%period', $period ) )}</dd>
                </dl>
            </div>
        </li>
        <li class="exp-step">
            <h3><label for="tfa-code">{'Enter the code the app shows'|i18n( 'extension/sevenx_authentication_2fa' )}</label></h3>
            <p class="exp-muted" id="tfa-code-help">{'This confirms the app is set up right. Until then nothing changes.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            <div class="exp-confirm-row">
                <input id="tfa-code" class="exp-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12"
                       autocomplete="one-time-code" spellcheck="false" aria-describedby="{if $error}tfa-setup-error {/if}tfa-code-help"{if $error} aria-invalid="true"{/if} />
            </div>
        </li>
    </ol>
    <input type="hidden" name="Secret" value="{$secret|wash}" />
</div>
{elseif $needs_confirmation}
<div class="exp-panel">
    <div class="exp-panel-head"><h2 class="exp-h2">{'Your authenticator app is set up'|i18n( 'extension/sevenx_authentication_2fa' )}</h2></div>
    <p class="exp-muted">{'To switch to e-mail codes or turn the second step off, confirm with the code your app shows now. Its key is never shown again; to move to a new phone, remove the second step and set it up there.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
    <div class="exp-field exp-field-spaced">
        <label for="tfa-code">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
        <input id="tfa-code" class="exp-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12"
               autocomplete="one-time-code" spellcheck="false"{if $error} aria-invalid="true" aria-describedby="tfa-setup-error"{/if} />
    </div>
</div>
{/if}

<div class="exp-bottombar">
    <div class="exp-actions">
        <button type="submit" class="exp-btn exp-btn-primary{if $is_pending_setup} is-wide{/if}" name="SetupButton" value="1">{if $is_pending_setup}{'Save and sign in'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Save settings'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</button>
        {if gt( $grace_days_left, 0 )}<button type="submit" class="exp-btn is-wide" name="LaterButton" value="1" formnovalidate="formnovalidate">{'Set up later'|i18n( 'extension/sevenx_authentication_2fa' )}</button>{/if}
        {if and( $is_pending_setup|not, $setup_node )}<a class="exp-btn" href={$setup_node.url_alias|ezurl}>{'Back to my account'|i18n( 'extension/sevenx_authentication_2fa' )}</a>{/if}
    </div>
    {if $is_pending_setup|not}<p class="exp-meta">{'The new setting applies from the next sign-in.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>{/if}
</div>

</form>

{if and( $needs_confirmation, $recovery_enabled, $is_pending_setup|not )}
<section class="exp-section" aria-labelledby="tfa-recovery-new-title">
<h2 class="exp-h2" id="tfa-recovery-new-title">{'Recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
<p class="exp-muted">{'One-time codes that sign you in when your phone is lost. Making new ones makes the old ones stop working.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
<details class="exp-confirm">
    <summary>{'Make new recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</summary>
    <div>
        <form method="post" action={'user2fa/setup'|ezurl} novalidate="novalidate">
            <div class="exp-confirm-row">
                <div class="exp-field">
                    <label for="tfa-recovery-code">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="tfa-recovery-code" class="exp-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
                </div>
                <button type="submit" class="exp-btn" name="RecoveryButton" value="1">{'Make new recovery codes'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
            </div>
        </form>
    </div>
</details>
</section>
{/if}

{if and( ne( $method_now, 'disabled' ), $is_enforced|not, $is_pending_setup|not )}
<section class="exp-section exp-reset" aria-labelledby="tfa-reset-title">
<h2 class="exp-h2" id="tfa-reset-title">{'Reset two-factor authentication'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
<p class="exp-muted exp-reset-intro">{'Use this button only if you want to remove your existing 2FA configuration permanently.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
<details class="exp-confirm">
    <summary>{'Reset 2FA'|i18n( 'extension/sevenx_authentication_2fa' )}</summary>
    <div>
        <form method="post" action={'user2fa/setup'|ezurl} novalidate="novalidate">
            <p>{'This removes the second step: the password alone will sign in until you set it up again. An app entry for this site stops working.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            <div class="exp-confirm-row">
                {if $needs_confirmation}
                <div class="exp-field">
                    <label for="tfa-reset-code">{'Current code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="tfa-reset-code" class="exp-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
                </div>
                {/if}
                <button type="submit" class="exp-btn exp-btn-outline-danger" name="ResetButton" value="1">{'Remove the second step'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
            </div>
        </form>
    </div>
</details>
</section>
{/if}

{/if}

{if $is_pending_setup}
<div class="exp-links"><p><a href={'user/login'|ezurl}>{'Cancel and sign in again'|i18n( 'extension/sevenx_authentication_2fa' )}</a></p></div>
</div>
{else}
</div></div></div>
</div>
{/if}

{if $show_enrol}
{* the enrolment is shown while "totp" is picked: sevenx_authentication_2fa_setup.js, no inline script *}
{ezscript( array( 'sevenx_authentication_2fa_setup.js' ) )}
{/if}
{undef $active $method_now $show_enrol $setup_node}
