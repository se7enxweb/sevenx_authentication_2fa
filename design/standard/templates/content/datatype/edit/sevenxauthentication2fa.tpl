{* The two-factor field of a user object in a public edit or registration form (designs without their own).

   A confirmed authenticator is kept as it is when the form is saved and its key is never shown; choosing the
   authenticator shows a new key and QR code after saving once, and the code from the app confirms it. Field
   names are those the datatype reads: <base>_sevenxauthentication2fa_method_<id> and
   <base>_sevenxauthentication2fa_code_<id>. Inline styles only, so it fits any design. *}
{default attribute_base=ContentObjectAttribute}
{def $data = $attribute.content
     $method = $data.method
     $id_base = concat( 'ezcoa-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )
     $is_enforced = and( ezini_hasvariable( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' ), eq( ezini( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' ), 'enabled' ) )}
{if ne( $attribute_base, 'ContentObjectAttribute' )}
    {set $id_base = concat( 'ezcoa-', $attribute_base, '-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )}
{/if}

<div class="tfa-field">
<p style="margin:0 0 .5em;color:#5d6573;">
    {if $data.is_active}
        {if eq( $method, 'totp' )}{'On: an authenticator app is set up and confirmed.'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'On: a code is sent by e-mail at each sign-in.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
    {elseif $data.is_enrolling}
        {'Waiting for the code that confirms the authenticator app.'|i18n( 'extension/sevenx_authentication_2fa' )}
    {else}
        {'Off: the password alone signs in.'|i18n( 'extension/sevenx_authentication_2fa' )}
    {/if}
</p>

{if $is_enforced}
<div class="warning"><p>{'Two-factor authentication is required. You must choose an authentication method.'|i18n( 'extension/sevenx_authentication_2fa' )}</p></div>
{/if}

<div class="block">
    <label for="{$id_base}_method">{'Authentication method'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
    <select id="{$id_base}_method" class="ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" name="{$attribute_base}_sevenxauthentication2fa_method_{$attribute.id}">
        {if not( $is_enforced )}<option value="disabled"{if eq( $method, 'disabled' )} selected="selected"{/if}>{'Disabled'|i18n( 'extension/sevenx_authentication_2fa' )}</option>{/if}
        <option value="totp"{if eq( $method, 'totp' )} selected="selected"{/if}>{'Authenticator app (TOTP)'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
        <option value="email"{if eq( $method, 'email' )} selected="selected"{/if}>{'E-mail one-time code'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
    </select>
</div>

{if $data.is_enrolling}
<div class="block" id="{$id_base}_totp" style="display:flex;flex-wrap:wrap;gap:1em 1.5em;align-items:flex-start;padding:1em;border:1px solid #d9dde3;border-radius:10px;">
    {if $data.enrolment_qr}<img src="{$data.enrolment_qr}" width="180" height="180" style="padding:8px;background:#fff;border:1px solid #d9dde3;border-radius:8px;" alt="{'QR code for your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}" />{/if}
    <div style="flex:1 1 14em;min-width:0;">
        <p style="margin:0 0 .3em;">{'Or type this key'|i18n( 'extension/sevenx_authentication_2fa' )}:</p>
        <p style="margin:0 0 1em;"><code style="font-size:1.05em;letter-spacing:.06em;overflow-wrap:anywhere;">{$data.enrolment_secret_grouped|wash}</code></p>
        <label for="{$id_base}_code">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
        <input id="{$id_base}_code" type="text" name="{$attribute_base}_sevenxauthentication2fa_code_{$attribute.id}" value="" inputmode="numeric" maxlength="12" size="10" autocomplete="one-time-code" spellcheck="false" />
    </div>
</div>
{elseif and( eq( $method, 'totp' ), $data.is_active|not )}
<p>{'Select TOTP and click Register to generate a secret.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
{/if}
</div>
{undef $data $method $id_base $is_enforced}
{/default}
