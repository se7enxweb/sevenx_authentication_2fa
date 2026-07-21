{* sevenx_authentication_2fa - admin 2FA verify view *}
{* Copyright (C) 1998 - 2026 7x. All rights reserved. *}
{* GNU General Public License v2.0 (or any later version) *}

<div class="content-navigation">
    <div class="context-block">
        <div class="box-header">
            <h1 class="context-title">{'Two-Factor Authentication'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>
        </div>
        <div class="box-content">
            {if eq( $status, 'failed' )}
                <div class="message-error">
                    <h2>{'Authentication error'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
                    <p>{$error}</p>
                </div>

                <p>
                    <a class="button" href={concat( 'user2fa/verify' )|ezurl( 'no' )}>{'Try again'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
                    <a class="button" href="{$redirect_uri|wash}">{'Back'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
                </p>
            {else}
                {if $error}
                    <div class="message-feedback">
                        <p>{$error}</p>
                    </div>
                {/if}

                <form method="post" action={concat( 'user2fa/verify' )|ezurl( 'no' )}>
                    <p>
                        {if eq( $method, 'email' )}
                            {'A verification code has been sent to your e-mail address.'|i18n( 'extension/sevenx_authentication_2fa' )}
                        {else}
                            {'Open your authenticator app and enter the current 6-digit code.'|i18n( 'extension/sevenx_authentication_2fa' )}
                        {/if}
                    </p>

                    <label for="Code">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="Code" type="text" name="Code" value="" maxlength="10" size="10" autocomplete="one-time-code" />

                    <input class="button" type="submit" name="VerifyButton" value="{'Verify'|i18n( 'extension/sevenx_authentication_2fa' )}" />

                    {if eq( $method, 'email' )}
                        <input class="button" type="submit" name="ResendButton" value="{'Resend code'|i18n( 'extension/sevenx_authentication_2fa' )}" />
                    {/if}
                </form>

                <p>
                    <a class="button" href="{$redirect_uri|wash}">{'Back'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
                </p>
            {/if}
        </div>
    </div>
</div>
