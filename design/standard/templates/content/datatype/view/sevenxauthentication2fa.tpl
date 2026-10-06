{* The two-factor field of a user object when the object is viewed: its state only. The key of an authenticator is
   never shown, here or anywhere else once it is confirmed. *}
{def $tfa_data = $attribute.content}
{if $tfa_data.is_active}
    {if eq( $tfa_data.method, 'totp' )}{'On: authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'On: e-mail codes'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
{elseif $tfa_data.is_enrolling}
    {'Off: an authenticator app is being set up'|i18n( 'extension/sevenx_authentication_2fa' )}
{else}
    {'Off'|i18n( 'extension/sevenx_authentication_2fa' )}
{/if}
{undef $tfa_data}
