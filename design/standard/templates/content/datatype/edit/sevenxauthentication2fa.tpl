{* sevenx_authentication_2fa - eZ content edit template for the 7x 2FA datatype *}
{* Copyright (C) 1998 - 2026 7x. All rights reserved. *}
{* GNU General Public License v2.0 (or any later version) *}

{default attribute_base=ContentObjectAttribute}

{def $data = $attribute.content
     $method = $data.method
     $secret = $data.secret
     $id_base = concat( 'ezcoa-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )
     $is_enforced = ezini( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' )|eq( 'enabled' )}

{if ne( $attribute_base, 'ContentObjectAttribute' )}
    {set $id_base = concat( 'ezcoa-', $attribute_base, '-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )}
{/if}

{if $is_enforced}
<div class="warning">
    <p>{'Two-factor authentication is required. You must choose an authentication method.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
</div>
{/if}

<div class="block">
    <label for="{$id_base}_method">{'Authentication method'|i18n( 'extension/sevenx_authentication_2fa' )}:</label>
    <select id="{$id_base}_method" class="ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" name="{$attribute_base}_sevenxauthentication2fa_method_{$attribute.id}">
        {if not( $is_enforced )}<option value="disabled"{if eq( $method, 'disabled' )} selected="selected"{/if}>{'Disabled'|i18n( 'extension/sevenx_authentication_2fa' )}</option>{/if}
        <option value="totp"{if eq( $method, 'totp' )} selected="selected"{/if}>{'Authenticator app (TOTP)'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
        <option value="email"{if eq( $method, 'email' )} selected="selected"{/if}>{'E-mail one-time code'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
    </select>
</div>

<div class="block" id="{$id_base}_totp" style="{if ne( $method, 'totp' )}display:none;{/if}">
    {if $secret}
        <p>{'Open your authenticator app and add the secret below, then enter the current 6-digit code to continue.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
        <p><strong>{'Secret:'|i18n( 'extension/sevenx_authentication_2fa' )}</strong> <code>{$secret|wash( xhtml )}</code></p>
        <input type="hidden" name="{$attribute_base}_sevenxauthentication2fa_secret_{$attribute.id}" value="{$secret|wash( xhtml )}" />

        <div class="block">
            <label for="{$id_base}_code">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}:</label>
            <input id="{$id_base}_code" type="text" name="{$attribute_base}_sevenxauthentication2fa_code_{$attribute.id}" value="" maxlength="10" size="10" autocomplete="one-time-code" />
        </div>
    {else}
        <p>{'Select TOTP and click Register to generate a secret.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
    {/if}
</div>

<div class="block" id="{$id_base}_email" style="{if ne( $method, 'email' )}display:none;{/if}">
    <p>{'A one-time code will be sent to your registered e-mail address at login.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
</div>

<script type="text/javascript">
{literal}
(function() {
    var methodSelect = document.getElementById( '{/literal}{$id_base}_method{literal}' );
    var totpDiv      = document.getElementById( '{/literal}{$id_base}_totp{literal}' );
    var emailDiv     = document.getElementById( '{/literal}{$id_base}_email{literal}' );
    if ( !methodSelect || !totpDiv || !emailDiv ) return;

    function updateVisibility() {
        var method = methodSelect.value;
        totpDiv.style.display  = ( method === 'totp' )  ? 'block' : 'none';
        emailDiv.style.display = ( method === 'email' ) ? 'block' : 'none';
    }

    methodSelect.addEventListener( 'change', updateVisibility );
    updateVisibility();
})();
{/literal}
</script>

{/default}
