{* A social login that did not sign anyone in (user2fa/oauth and user2fa/callback), in every design
   without its own; where LoginPage=custom (the administration) it is
   drawn by loginpagelayout.tpl like the sign-in form. One message per status, each with the way back:
   cancelled, state, not_configured, unknown_provider, no_account, unverified_email, not_allowed, failed.
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/exp_style.tpl'}
{def $name = cond( $provider_name, $provider_name, 'the provider'|i18n( 'extension/sevenx_authentication_2fa' ) )}

<div class="exp-2fa is-signin">
<div class="exp-2fa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7.5v5.5m0 3.2v.3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>

<h1 class="exp-2fa-title">{switch match=$status}
{case match='cancelled'}{'Sign-in was cancelled'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
{case match='no_account'}{'No account for this sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
{case match='not_allowed'}{'This account cannot sign in here'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
{case}{'Social login did not work'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
{/switch}</h1>

<div class="exp-feedback {if eq( $status, 'cancelled' )}is-info{else}is-warn{/if}" role="status">
<p>{switch match=$status}
{case match='cancelled'}{'You stopped the sign-in with %provider, so nothing changed. You can try again or sign in with your username and password.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}{/case}
{case match='state'}{'This answer from %provider does not belong to a sign-in started in this browser, or it came too late. For your safety it was not used. Start the sign-in again.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}{/case}
{case match='not_configured'}{'Signing in with %provider is not set up on this site.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}{/case}
{case match='unknown_provider'}{'This site does not offer that kind of social login.'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
{case match='no_account'}{'%provider confirmed who you are, but no account on this site matches it, and new accounts are not made this way here. Sign in with your username and password, or register first.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}{/case}
{case match='unverified_email'}{'%provider has not verified your e-mail address, so it cannot be used to find your account. Verify it there, or sign in with your username and password.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}{/case}
{case match='not_allowed'}{'Your account was found, but it may not sign in to this part of the site.'|i18n( 'extension/sevenx_authentication_2fa' )}{/case}
{case}{'%provider could not be reached or did not confirm the sign-in. Try again in a moment.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}{/case}
{/switch}</p>
</div>

{if and( $provider, ne( $status, 'unknown_provider' ), ne( $status, 'not_configured' ), ne( $status, 'not_allowed' ) )}
<a class="exp-btn exp-btn-primary is-wide" href={concat( 'user2fa/oauth/', $provider )|ezurl}>{'Try %provider again'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $name ) )|wash}</a>
<a class="exp-btn is-wide" href={'user/login'|ezurl}>{'Sign in with username and password'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
{else}
<a class="exp-btn exp-btn-primary is-wide" href={'user/login'|ezurl}>{'Sign in with username and password'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
{/if}
</div>
{undef $name}
