{* The second step of signing in (user2fa/verify) in every design without its own. Where the siteaccess draws its own
   login page (LoginPage=custom: the administration) it is drawn by loginpagelayout.tpl like the
   sign-in form before it, in its light and dark mode.

   The form, its action and its fields are those the view reads: Code, VerifyButton, and ResendButton for an
   e-mail code. Status: verify, failed (a wrong code, with the tries left), locked (too many wrong codes, or the
   account is locked), expired (no sign-in in progress here) and elsewhere (an e-mail link opened in another
   browser). Works without javascript. Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/exp_style.tpl'}
{def $is_email = eq( $method, 'email' )
     $is_open = or( eq( $status, 'verify' ), eq( $status, 'failed' ) )}

<div class="exp-2fa is-signin">

<div class="exp-2fa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28"><path d="M12 2.5 4.5 5.5v6c0 4.6 3.1 8.6 7.5 10 4.4-1.4 7.5-5.4 7.5-10v-6L12 2.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m8.5 12 2.4 2.4 4.6-4.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>

{if $is_open}

<h1 class="exp-2fa-title">{'Confirm it is you'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
<p class="exp-2fa-sub">
{if $is_email}
    {if $masked_email}{'We sent a code to %email. Enter it below.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%email', concat( '<strong>', $masked_email|wash, '</strong>' ) ) )}{else}{'A verification code has been sent to your e-mail address.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
{else}
    {'Open your authenticator app and enter the %digits-digit code it shows for this site.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%digits', $digits ) )}
{/if}
</p>

{if $notice}<div class="exp-feedback is-ok" role="status"><p>{$notice|wash}</p></div>{/if}
{if $error}<div class="exp-feedback is-bad" role="alert" id="tfa-verify-error"><p>{$error|wash}</p></div>{/if}

<form method="post" action={'user2fa/verify'|ezurl} novalidate="novalidate">
    <div class="exp-field">
        <label for="Code">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
        <input id="Code" class="exp-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12"
               autocomplete="one-time-code" autocapitalize="off" spellcheck="false" enterkeyhint="go" required="required" autofocus="autofocus"
               aria-describedby="{if $error}tfa-verify-error {/if}tfa-verify-help"{if eq( $status, 'failed' )} aria-invalid="true"{/if} />
        <span class="exp-help" id="tfa-verify-help">{if $is_email}{'The code is in the e-mail, and in its subject line.'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Spaces are fine. The app shows a new code every few seconds, and each code works once.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</span>
    </div>
    {if eq( $status, 'failed' )}
    <p class="exp-attempts" role="status">{'%count tries left before you have to sign in again.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%count', $attempts_left ) )}</p>
    {/if}

    <button type="submit" class="exp-btn exp-btn-primary is-wide" name="VerifyButton" value="1">{'Verify and sign in'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
    {if and( $is_email, $can_resend )}
    <button type="submit" class="exp-btn is-wide" name="ResendButton" value="1" formnovalidate="formnovalidate"{if gt( $resend_wait, 0 )} aria-describedby="tfa-resend-wait"{/if}>{'Send a new code'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
    {if gt( $resend_wait, 0 )}<p class="exp-attempts" id="tfa-resend-wait">{'A new code can be sent in %seconds seconds.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%seconds', $resend_wait ) )}</p>{/if}
    {/if}
</form>

<div class="exp-links">
    <p><a href={'user/login'|ezurl}>{'Cancel and sign in again'|i18n( 'extension/sevenx_authentication_2fa' )}</a></p>
    {if $is_email|not}<p class="exp-muted">{'No access to your app? Ask an administrator to reset your second step.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>{/if}
</div>

{elseif eq( $status, 'locked' )}

<h1 class="exp-2fa-title">{'Too many wrong codes'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
<div class="exp-feedback is-bad" role="alert">
    <p>{'To keep the account safe, this sign-in has ended. Sign in with your password again to get a new chance; when the account itself is locked, a password reset or an administrator unlocks it.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
</div>
<a class="exp-btn exp-btn-primary is-wide" href={'user/login'|ezurl}>{'Sign in again'|i18n( 'extension/sevenx_authentication_2fa' )}</a>

{elseif eq( $status, 'elsewhere' )}

<h1 class="exp-2fa-title">{'Open this link where you signed in'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
<div class="exp-feedback is-info" role="status">
    <p>{'The link in the e-mail only works in the browser where you entered your password. Go back to that window and enter the code there, or sign in here.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
</div>
<a class="exp-btn exp-btn-primary is-wide" href={'user/login'|ezurl}>{'Sign in here'|i18n( 'extension/sevenx_authentication_2fa' )}</a>

{else}

<h1 class="exp-2fa-title">{'This sign-in has ended'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
<div class="exp-feedback is-warn" role="status">
    <p>{'There is no sign-in waiting for a code in this browser: it took too long, or it was finished or cancelled. Sign in again to get a new code.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
</div>
<a class="exp-btn exp-btn-primary is-wide" href={'user/login'|ezurl}>{'Sign in again'|i18n( 'extension/sevenx_authentication_2fa' )}</a>

{/if}

</div>
{undef $is_email $is_open}
