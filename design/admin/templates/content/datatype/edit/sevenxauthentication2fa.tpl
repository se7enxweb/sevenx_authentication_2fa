{* The two-factor field of a user object in the administration's content edit form (and in registration).

   Shows the state; a confirmed authenticator is kept as it is when the form is saved (no code needed) and its
   key is never shown; choosing the authenticator for an account without one shows a new key and QR code after
   saving once, and the code from the app confirms it. An administrator can set an account to e-mail codes or off
   here, which is how a user who lost the phone gets back in.

   Field names are those the datatype reads: <base>_sevenxauthentication2fa_method_<id> and
   <base>_sevenxauthentication2fa_code_<id>. Works without javascript.
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{include uri='design:user2fa/exp_style.tpl'}
{default attribute_base=ContentObjectAttribute}
{def $data = $attribute.content
     $method = $data.method
     $id_base = concat( 'ezcoa-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )
     $is_enforced = and( ezini_hasvariable( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' ), eq( ezini( 'General', 'Enforce2FA', 'sevenxauthentication2fa.ini' ), 'enabled' ) )}
{if ne( $attribute_base, 'ContentObjectAttribute' )}
    {set $id_base = concat( 'ezcoa-', $attribute_base, '-', $attribute.contentclassattribute_id, '_', $attribute.contentclass_attribute_identifier )}
{/if}

<div class="exp-2fa" id="{$id_base}_box">
    <p class="exp-meta" style="margin-bottom: 8px;">
        {if $data.is_active}
            {if eq( $method, 'totp' )}{'On: an authenticator app is set up and confirmed.'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'On: a code is sent by e-mail at each sign-in.'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}
        {elseif $data.is_enrolling}
            {'Waiting for the code that confirms the authenticator app.'|i18n( 'extension/sevenx_authentication_2fa' )}
        {else}
            {'Off: the password alone signs in.'|i18n( 'extension/sevenx_authentication_2fa' )}
        {/if}
    </p>

    {if $is_enforced}
    <div class="exp-feedback is-info"><p>{'Two-factor authentication is required. You must choose an authentication method.'|i18n( 'extension/sevenx_authentication_2fa' )}</p></div>
    {/if}

    <div class="exp-field" style="max-width: 420px;">
        <label for="{$id_base}_method">{'Authentication method'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
        <select id="{$id_base}_method" name="{$attribute_base}_sevenxauthentication2fa_method_{$attribute.id}">
            {if not( $is_enforced )}<option value="disabled"{if eq( $method, 'disabled' )} selected="selected"{/if}>{'Disabled'|i18n( 'extension/sevenx_authentication_2fa' )}</option>{/if}
            <option value="totp"{if eq( $method, 'totp' )} selected="selected"{/if}>{'Authenticator app (TOTP)'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
            <option value="email"{if eq( $method, 'email' )} selected="selected"{/if}>{'E-mail one-time code'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
        </select>
    </div>

    {if $data.is_enrolling}
    <div class="exp-panel" id="{$id_base}_totp" style="margin-top: 12px;">
        <div class="exp-pair">
            {if $data.enrolment_qr}<figure class="exp-qr"><img src="{$data.enrolment_qr}" width="180" height="180" alt="{'QR code for your authenticator app'|i18n( 'extension/sevenx_authentication_2fa' )}" /></figure>{/if}
            <dl class="exp-key">
                <dt>{'Or type this key'|i18n( 'extension/sevenx_authentication_2fa' )}</dt>
                <dd><code class="exp-secret">{$data.enrolment_secret_grouped|wash}</code></dd>
            </dl>
        </div>
        <div class="exp-field" style="margin-top: 12px;">
            <label for="{$id_base}_code">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
            <input id="{$id_base}_code" class="exp-code" type="text" name="{$attribute_base}_sevenxauthentication2fa_code_{$attribute.id}" value="" inputmode="numeric" maxlength="12" autocomplete="one-time-code" spellcheck="false" />
            <span class="exp-help">{'Add the key to the app, then enter the code it shows and save.'|i18n( 'extension/sevenx_authentication_2fa' )}</span>
        </div>
    </div>
    {elseif and( eq( $method, 'totp' ), $data.is_active|not )}
    <p class="exp-help" style="margin-top: 8px;">{'Select TOTP and save to generate a secret.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
    {/if}
</div>
{undef $data $method $id_base $is_enforced}
{/default}
