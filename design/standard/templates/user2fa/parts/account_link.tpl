{* The way from a user's profile to the two-step sign-in page (user2fa/setup), with its state, for a signed in user
   whose user class has the two-factor field. Variables: style ('box', the default: a small box with a sentence;
   'item': a list item with the link and the state only, for a design's own list of account links).
   No style attribute: the rules are the .exp-2fa-account* classes of stylesheets/sevenx_authentication_2fa.css, linked
   here as well, so it needs nothing of the page it is on (designs whose page layout loads no stylesheet list).
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{def $tfa_user = fetch( 'user', 'current_user' )
     $tfa_data = false()}
{if $tfa_user.is_logged_in}
{foreach $tfa_user.contentobject.data_map as $tfa_attribute}
    {if eq( $tfa_attribute.data_type_string, 'sevenxauthentication2fa' )}{set $tfa_data = $tfa_attribute.content}{break}{/if}
{/foreach}
{if $tfa_data}
{def $tfa_state = cond( $tfa_data.is_active, cond( eq( $tfa_data.method, 'totp' ), 'Authenticator app'|i18n( 'extension/sevenx_authentication_2fa' ), 'E-mail codes'|i18n( 'extension/sevenx_authentication_2fa' ) ), 'Off'|i18n( 'extension/sevenx_authentication_2fa' ) )}
{if eq( first_set( $style, 'box' ), 'item' )}
<li><link rel="stylesheet" type="text/css" href={'stylesheets/sevenx_authentication_2fa.css'|ezdesign} /><a href={'user2fa/setup'|ezurl}>{'Two-step sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}</a> <span class="acc-count{if $tfa_data.is_active|not} exp-2fa-account-off{/if}">{$tfa_state|wash}</span></li>
{else}
<link rel="stylesheet" type="text/css" href={'stylesheets/sevenx_authentication_2fa.css'|ezdesign} />
<div class="tfa-account-link exp-2fa-account">
    <p class="exp-2fa-account-text"><b>{'Two-step sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}:</b>
        {if $tfa_data.is_active}{'On, with %method.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%method', $tfa_state ) )|wash}{else}{'Off. A code from your phone at sign-in keeps your account safe even if your password leaks.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
    <a class="exp-2fa-account-link" href={'user2fa/setup'|ezurl}>{if $tfa_data.is_active}{'Manage'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Turn on'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</a>
</div>
{/if}
{undef $tfa_state}
{/if}
{/if}
{undef $tfa_user $tfa_data}
