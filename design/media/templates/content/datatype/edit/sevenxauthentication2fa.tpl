{* The two-factor field of a user object in the media design's registration and public edit forms, with the
   design's form controls. A confirmed authenticator is kept as it is when the form is saved and its key is never
   shown; choosing the authenticator shows a new key and QR code after saving once, and the code from the app
   confirms it. Field names are those the datatype reads: <base>_sevenxauthentication2fa_method_<id> and
   <base>_sevenxauthentication2fa_code_<id>. Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/parts/style.tpl'}
{default attribute_base=ContentObjectAttribute}
{def $data = $attribute.content
     $method = $data.method
     $id_base = concat( 'ezcoa-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )
     $is_enforced = and( ezini_hasvariable( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' ), eq( ezini( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' ), 'enabled' ) )}
{if ne( $attribute_base, 'ContentObjectAttribute' )}
    {set $id_base = concat( 'ezcoa-', $attribute_base, '-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )}
{/if}
<div class="tfa-m">
    <select id="{$id_base}" class="form-control" name="{$attribute_base}_sevenxauthentication2fa_method_{$attribute.id}" aria-describedby="{$id_base}_state">
        {if not( $is_enforced )}<option value="disabled"{if eq( $method, 'disabled' )} selected="selected"{/if}>{'Off: the password alone signs in'|i18n( 'extension/sevenx_authentication_2fa' )}</option>{/if}
        <option value="totp"{if eq( $method, 'totp' )} selected="selected"{/if}>{'Authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
        <option value="email"{if eq( $method, 'email' )} selected="selected"{/if}>{'E-mail code'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
    </select>
    <p class="tfa-hint" id="{$id_base}_state">
    {if $data.is_active}
        {if eq( $method, 'totp' )}{'On: an authenticator app is set up and confirmed.'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'On: a code is sent by e-mail at each sign-in.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
    {elseif $data.is_enrolling}
        {'Waiting for the code that confirms the authenticator app.'|i18n( 'extension/sevenx_authentication_2fa' )}
    {elseif $is_enforced}
        {'Two-factor authentication is required. You must choose an authentication method.'|i18n( 'extension/sevenx_authentication_2fa' )}
    {else}
        {'A code from your phone or your e-mail after the password. You can also set it up later in your account.'|i18n( 'extension/sevenx_authentication_2fa' )}
    {/if}
    </p>
    {if $data.is_enrolling}
    <div class="tfa-pair" style="margin-top: 1rem;">
        {if $data.enrolment_qr}<figure class="tfa-qr"><img src="{$data.enrolment_qr}" width="180" height="180" alt="{'QR code for your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}" /></figure>{/if}
        <dl class="tfa-key">
            <dt>{'Or type this key'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
            <dd><code class="tfa-secret">{$data.enrolment_secret_grouped|wash}</code></dd>
        </dl>
    </div>
    <label for="{$id_base}_code" class="form-label" style="margin-top: 1rem;">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
    <input id="{$id_base}_code" class="form-control tfa-code" type="text" name="{$attribute_base}_sevenxauthentication2fa_code_{$attribute.id}" value="" inputmode="numeric" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
    <p class="tfa-hint">{'Add the key to the app, then enter the code it shows and save.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
    {/if}
</div>
{undef $data $method $id_base $is_enforced}
{/default}
