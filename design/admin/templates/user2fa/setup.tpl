{* sevenx_authentication_2fa - admin 2FA setup view *}
{* Copyright (C) 1998 - 2026 7x. All rights reserved. *}
{* GNU General Public License v2.0 (or any later version) *}

<div class="content-navigation">
    <div class="context-block">
        <div class="box-header">
            <h1 class="context-title">{'Two-Factor Authentication Setup'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
        </div>
        <div class="box-content">
            {if $error}
                <div class="message-error"><p>{$error}</p></div>
            {/if}
            {if $success}
                <div class="message-feedback"><p>{$success}</p></div>
            {/if}

            <form method="post" action={concat( 'user2fa/setup' )|ezurl( 'no' )}>
                <label for="Method">{'Authentication method'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                <select id="Method" name="Method">
                    <option value="disabled" {if eq( $data.method, 'disabled' )}selected="selected"{/if}>{'Disabled'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
                    <option value="totp" {if eq( $data.method, 'totp' )}selected="selected"{/if}>{'Authenticator app (TOTP)'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
                    <option value="email" {if eq( $data.method, 'email' )}selected="selected"{/if}>{'E-mail one-time code'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
                </select>

                <div id="totp-setup" style="{if ne( $data.method, 'totp' )}display:none;{/if}">
                    {if $data.verified}
                        <p><strong>{'Authenticator app active'|i18n( 'extension/sevenx_authentication_2fa' )}</strong></p>
                        <p>{'TOTP is configured and verified. Your authenticator app is ready to use.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                        <input type="hidden" name="Secret" value="{$secret}" />
                    {else}
                        <p>{'Scan the QR code with your authenticator app or enter the secret manually.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                        <p><img src="{$qr_code}" alt="QR" /></p>
                        <p><strong>{'Secret:'|i18n( 'extension/sevenx_authentication_2fa' )}</strong> {$secret}</p>
                        <label for="Code">{'Verify code from app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                        <input id="Code" type="text" name="Code" value="" maxlength="10" size="10" autocomplete="one-time-code" />
                        <input type="hidden" name="Secret" value="{$secret}" />
                    {/if}
                </div>

                <div id="email-setup" style="{if ne( $data.method, 'email' )}display:none;{/if}">
                    <p>{'A one-time code will be sent to your registered e-mail address at login.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                </div>

                <input class="button" type="submit" name="SetupButton" value="{'Save settings'|i18n( 'extension/sevenx_authentication_2fa' )}" />
            </form>

            <script type="text/javascript">
            {literal}
            (function() {
                var methodSelect = document.getElementById( 'Method' );
                var totpSetup    = document.getElementById( 'totp-setup' );
                var emailSetup   = document.getElementById( 'email-setup' );
                if ( !methodSelect || !totpSetup || !emailSetup ) return;

                function updateVisibility() {
                    var method = methodSelect.value;
                    totpSetup.style.display  = ( method === 'totp' )  ? 'block' : 'none';
                    emailSetup.style.display = ( method === 'email' ) ? 'block' : 'none';
                }

                methodSelect.addEventListener( 'change', updateVisibility );
                updateVisibility();
            })();
            {/literal}
            </script>
        </div>
    </div>
</div>
