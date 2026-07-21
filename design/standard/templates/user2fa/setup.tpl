{* sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension *}
{* Copyright (C) 1998 - 2026 7x. All rights reserved. *}
{* GNU General Public License v2.0 (or any later version) *}

<div class="content-view-full">
    <div class="class-user2fa-setup">
        <h1>{'Two-Factor Authentication Setup'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>

        {if $error}
            <div class="warning">
                <p>{$error}</p>
            </div>
        {/if}

        {if $success}
            <div class="feedback">
                <p>{$success}</p>
            </div>
        {/if}

        <form method="post" action={concat( 'user2fa/setup' )|ezurl( 'no' )}>
            <div class="block">
                <label for="Method">{'Authentication method'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                <select id="Method" name="Method">
                    <option value="disabled" {if eq( $data.method, 'disabled' )}selected="selected"{/if}>{'Disabled'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
                    <option value="totp" {if eq( $data.method, 'totp' )}selected="selected"{/if}>{'Authenticator app (TOTP)'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
                    <option value="email" {if eq( $data.method, 'email' )}selected="selected"{/if}>{'E-mail one-time code'|i18n( 'extension/sevenx_authentication_2fa' )}</option>
                </select>
            </div>

            <div class="block" id="totp-setup" style="{if ne( $data.method, 'totp' )}display:none;{/if}">
                {if $data.verified}
                    <h2>{'Authenticator app active'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
                    <p>{'TOTP is configured and verified. Your authenticator app is ready to use.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
                    <input type="hidden" name="Secret" value="{$secret}" />
                {else}
                    <h2>{'Authenticator app setup'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
                    <p>{'Scan the QR code below with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.) or enter the secret manually.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>

                    <p>
                        <img src="{$qr_code}" alt="{'QR Code'|i18n( 'extension/sevenx_authentication_2fa' )}" />
                    </p>

                    <p>
                        <strong>{'Secret:'|i18n( 'extension/sevenx_authentication_2fa' )}</strong> {$secret}
                    </p>

                    <label for="Code">{'Verify code from app'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="Code" type="text" name="Code" value="" maxlength="10" size="10" autocomplete="one-time-code" />
                    <input type="hidden" name="Secret" value="{$secret}" />
                {/if}
            </div>

            <div class="block" id="email-setup" style="{if ne( $data.method, 'email' )}display:none;{/if}">
                <p>{'When you log in, a one-time code will be sent to your registered e-mail address.'|i18n( 'extension/sevenx_authentication_2fa' )}</p>
            </div>

            <div class="block">
                <input class="button" type="submit" name="SetupButton" value="{'Save settings'|i18n( 'extension/sevenx_authentication_2fa' )}" />
            </div>
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
