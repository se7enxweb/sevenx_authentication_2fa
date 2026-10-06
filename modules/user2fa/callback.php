<?php
//
// sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension
// Copyright (C) 1998 - 2026 7x. All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//

/* The provider's answer to a social login (user2fa/callback/<provider>).

   The state must be the one this session sent, for this provider, and not
   older than ten minutes; it is used once. A user found or created this way
   still has the second step of their account to pass. Every outcome that is
   not a sign-in is shown on a page that says what happened (status:
   cancelled, state, not_configured, unknown_provider, no_account, failed).
   After signing in the browser goes to the target given when the login
   started (checked by the safe redirect rules), else '/'. */

$Module = $Params['Module'];
$provider = eZOAuthUser::providerKey( isset( $Params['provider'] ) ? $Params['provider'] : '' );
$handler = $provider !== '' ? eZOAuthUser::handlerFor( $provider ) : null;
sevenxAuthentication2faHelper::noStore();

$status = 'failed';
if ( !$handler )
{
    $status = 'unknown_provider';
    sevenxAuthentication2faHelper::authLog( 'oauth_callback_invalid_provider', 'provider=' . $provider );
}
else
{
    sevenxAuthentication2faHelper::authLog( 'oauth_callback_received', 'provider=' . $provider );
    $user = $handler->handleCallback();

    if ( $user instanceof eZUser )
    {
        $redirect = $handler->takeReturnTarget();
        $userID = (int)$user->attribute( 'contentobject_id' );
        $helper = sevenxAuthentication2faHelper::instance();

        if ( !$user->isEnabled() || ( isset( $GLOBALS['eZCurrentAccess'] ) && !$user->canLoginToSiteAccess( $GLOBALS['eZCurrentAccess'] ) ) )
        {
            $status = 'not_allowed';
            sevenxAuthentication2faHelper::authLog( 'oauth_login_not_allowed', 'provider=' . $provider, $userID );
        }
        else
        {
            // The account's own second step still applies; startChallenge() leaves the request when it is needed
            if ( $helper->isEnabled() && eZsevenxUser2faUser::startChallenge( $user, $redirect ) !== true )
            {
                return;
            }
            sevenxAuthentication2faHelper::authLog( 'oauth_login_succeeded', 'provider=' . $provider, $userID );
            eZsevenxUser2faUser::completeLogin( $user );
            return $Module->redirectTo( $redirect );
        }
    }
    else
    {
        $status = $handler->lastError() ? $handler->lastError() : 'failed';
        sevenxAuthentication2faHelper::authLog( 'oauth_login_failed', 'provider=' . $provider . ' status=' . $status );
    }
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'status', $status );
$tpl->setVariable( 'provider', $provider );
$tpl->setVariable( 'provider_name', $handler ? $handler->displayName() : '' );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:user2fa/callback.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Social login' ), 'url' => false ) );
$pageLayout = sevenxAuthentication2faHelper::pageLayout();
if ( $pageLayout )
    $Result['pagelayout'] = $pageLayout;
