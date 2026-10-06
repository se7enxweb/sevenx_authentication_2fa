{* The way from a user's profile to the two-step sign-in page (user2fa/setup), with its state, for a signed in user
   whose user class has the two-factor field. Variables: style ('box', the default: a small box with a sentence;
   'item': a list item with the link and the state only, for a design's own list of account links). Inline
   styles, so it needs nothing of the page it is on (as apikey/parts/account_link.tpl).
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
<li><a href={'user2fa/setup'|ezurl}>{'Two-step sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}</a> <span class="acc-count"{if $tfa_data.is_active|not} style="background:#fff3df;color:#8a4b00;"{/if}>{$tfa_state|wash}</span></li>
{else}
<div class="tfa-account-link" style="display:flex;flex-wrap:wrap;gap:.4em 1em;align-items:center;justify-content:space-between;margin:0 0 1em;padding:.7em .95em;border:1px solid #d9dde3;border-radius:9px;background:#f5f6f8;color:#1f2430;">
    <p style="margin:0;flex:1 1 18em;"><b>{'Two-step sign-in'|i18n( 'extension/sevenx_authentication_2fa' )}:</b>
        {if $tfa_data.is_active}{'On, with %method.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%method', $tfa_state ) )|wash}{else}{'Off. A code from your phone at sign-in keeps your account safe even if your password leaks.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
    <a href={'user2fa/setup'|ezurl} style="display:inline-block;padding:.4em .95em;border:1px solid #9a3412;border-radius:8px;background:#fff;color:#9a3412;font-weight:650;text-decoration:none;">{if $tfa_data.is_active}{'Manage'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Turn on'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</a>
</div>
{/if}
{undef $tfa_state}
{/if}
{/if}
{undef $tfa_user $tfa_data}
