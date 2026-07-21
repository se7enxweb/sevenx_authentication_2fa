{* sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension *}
{* Copyright (C) 1998 - 2026 7x. All rights reserved. *}
{* GNU General Public License v2.0 (or any later version) *}

<div class="content-view-full">
    <div class="class-user-login">
        <h1>{'Two-Factor Authentication'|i18n( 'extension/sevenx_authentication_2fa' )}</h1>

        {if eq( $status, 'failed' )}
            <div class="warning">
                <h2>{'Authentication error'|i18n( 'extension/sevenx_authentication_2fa' )}</h2>
                <p>{$error}</p>
            </div>

            <p>
                <a class="button" href={concat( 'user2fa/verify' )|ezurl( 'no' )}>{'Try again'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
                <a class="button" href="{$redirect_uri|wash}">{'Back'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
            </p>
        {else}
            {if $error}
                <div class="feedback">
                    <p>{$error}</p>
                </div>
            {/if}

            <form method="post" action={concat( 'user2fa/verify' )|ezurl( 'no' )}>
                <p>
                    {if eq( $method, 'email' )}
                        {'A verification code has been sent to your e-mail address. Enter the code below to continue.'|i18n( 'extension/sevenx_authentication_2fa' )}
                    {else}
                        {'Open your authenticator app and enter the current 6-digit code to continue.'|i18n( 'extension/sevenx_authentication_2fa' )}
                    {/if}
                </p>

                <div class="block">
                    <label for="Code">{'Verification code'|i18n( 'extension/sevenx_authentication_2fa' )}</label>
                    <input id="Code" type="text" name="Code" value="" maxlength="10" size="10" autocomplete="one-time-code" />
                </div>

                <div class="block">
                    <input class="button" type="submit" name="VerifyButton" value="{'Verify'|i18n( 'extension/sevenx_authentication_2fa' )}" />
                </div>

                {if eq( $method, 'email' )}
                    <div class="block">
                        <input class="button" type="submit" name="ResendButton" value="{'Resend code'|i18n( 'extension/sevenx_authentication_2fa' )}" />
                    </div>
                {/if}
            </form>

            <div class="block">
                <a class="button" href="{$redirect_uri|wash}">{'Back'|i18n( 'extension/sevenx_authentication_2fa' )}</a>
            </div>
        {/if}
    </div>
</div>
