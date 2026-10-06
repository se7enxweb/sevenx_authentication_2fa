{* The second step of signing in (user2fa/verify) in the media design: the design's page header, then one account
   card in the form column, like the login page before it.

   The form, its action and its fields are those the view reads: Code, VerifyButton, and ResendButton for an
   e-mail code. Status: verify, failed (with the tries left), locked, expired, elsewhere (an e-mail link opened in
   another browser). Works without javascript. Drawn by stylesheets/account.css of the design and
   user2fa/parts/style.tpl. Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/parts/style.tpl'}
{def $is_email = eq( $method, 'email' )
     $is_open = or( eq( $status, 'verify' ), eq( $status, 'failed' ) )}

<header class="full-page-header acc-header text-center no-breadcrumbs">
    <div class="container">
        <h1 class="full-page-title">{if $is_open}{'Confirm it is you'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $status, 'locked' )}{'Too many wrong codes'|i18n( 'extension/sevenx_authentication_2fa' )}{elseif eq( $status, 'elsewhere' )}{'Open this link where you signed in'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'This sign-in has ended'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</h1>
        {if $is_open}<p class="full-page-header-text">{'Your password was right. One more step keeps your account safe.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>{/if}
    </div>
</header>

<div class="full-form-content acc-content">
    <div class="container acc tfa-m">
{if $is_open}
        {if $notice}<div class="acc-notice success" role="status"><p>{$notice|wash}</p></div>{/if}
        {if $error}<div class="acc-notice error" role="alert" id="tfa-verify-error"><p>{$error|wash}</p></div>{/if}

        <form method="post" action={'user2fa/verify'|ezurl} class="embed-form" novalidate>
            <div class="acc-card">
                <h2>{if $is_email}{'Enter the code from your e-mail'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Enter the code from your app'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</h2>
                <p class="acc-lead">
                {if $is_email}
                    {if $masked_email}{'We sent a code to %email. Enter it below.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%email', concat( '<strong>', $masked_email|wash, '</strong>' ) ) )}{else}{'A verification code has been sent to your e-mail address.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
                {else}
                    {'Open your authenticator app and enter the %digits-digit code it shows for this site.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%digits', $digits ) )}
                {/if}
                </p>
                <div class="form-group">
                    <label for="Code" class="form-label">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="Code" class="form-control tfa-code" type="text" name="Code" value="" inputmode="numeric" pattern="[0-9 \-]*" maxlength="12"
                           autocomplete="one-time-code" autocapitalize="off" spellcheck="false" enterkeyhint="go" required autofocus
                           aria-describedby="{if $error}tfa-verify-error {/if}tfa-verify-help"{if eq( $status, 'failed' )} aria-invalid="true"{/if} />
                    <p class="tfa-hint" id="tfa-verify-help">{if $is_email}{'The code is in the e-mail, and in its subject line.'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Spaces are fine. The app shows a new code every few seconds, and each code works once.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
                    {if eq( $status, 'failed' )}<p class="tfa-left" role="status">{'%count tries left before you have to sign in again.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%count', $attempts_left ) )}</p>{/if}
                </div>
                <div class="acc-actions">
                    <button type="submit" class="btn btn-primary" name="VerifyButton" value="1">{'Verify and sign in'|i18n( 'extension/sevenx_authentication_2fa' )}</button>
                    {if and( $is_email, $can_resend )}<button type="submit" class="btn btn-secondary" name="ResendButton" value="1" formnovalidate>{'Send a new code'|i18n( 'extension/sevenx_authentication_2fa' )}</button>{/if}
                    <a class="btn btn-outline" href={'user/login'|ezurl}>{'Cancel'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
                </div>
                {if and( $is_email, gt( $resend_wait, 0 ) )}<p class="tfa-hint">{'A new code can be sent in %seconds seconds.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%seconds', $resend_wait ) )}</p>{/if}
            </div>
        </form>
        {if $is_email|not}<p class="text-center note">{'No access to your app? Ask the site\'s editors to reset your second step.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>{/if}
{else}
        <div class="acc-notice {if eq( $status, 'locked' )}error{elseif eq( $status, 'elsewhere' )}info{else}warning{/if}" role="{if eq( $status, 'locked' )}alert{else}status{/if}">
            <p>{switch match=$status}
            {case match='locked'}{'To keep the account safe, this sign-in has ended. Sign in with your password again to get a new chance; when the account itself is locked, a password reset unlocks it.'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
            {case match='elsewhere'}{'The link in the e-mail only works in the browser where you entered your password. Go back to that window and enter the code there, or sign in here.'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
            {case}{'There is no sign-in waiting for a code in this browser: it took too long, or it was finished or cancelled. Sign in again to get a new code.'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
            {/switch}</p>
        </div>
        <div class="acc-actions">
            <a class="btn btn-primary" href={'user/login'|ezurl}>{if eq( $status, 'elsewhere' )}{'Sign in here'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Sign in again'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</a>
            {if eq( $status, 'locked' )}<a class="btn btn-outline" href={'user/forgotpassword'|ezurl}>{'Reset my password'|i18n( 'extension/sevenx_authentication_2fa' )}</a>{/if}
        </div>
{/if}
    </div>
</div>
{undef $is_email $is_open}
