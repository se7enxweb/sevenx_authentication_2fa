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

$provider = isset( $Params['provider'] ) ? strtolower( $Params['provider'] ) : '';

if ( !$provider || !class_exists( 'eZ' . ucfirst( $provider ) . 'User' ) )
{
    sevenxAuthentication2faHelper::authLog( 'oauth_callback_invalid_provider', 'provider=' . $provider );
    eZHTTPTool::redirect( '/user/login' );
    eZExecution::cleanExit();
}

sevenxAuthentication2faHelper::authLog( 'oauth_callback_received', 'provider=' . $provider );

$className = 'eZ' . ucfirst( $provider ) . 'User';
$handler = new $className();

if ( !$handler instanceof eZOAuthUser )
{
    sevenxAuthentication2faHelper::authLog( 'oauth_callback_wrong_class', 'class=' . $className );
    eZHTTPTool::redirect( '/user/login' );
    eZExecution::cleanExit();
}

$user = $handler->handleCallback();

if ( $user instanceof eZUser )
{
    $helper = sevenxAuthentication2faHelper::instance();
    if ( $helper->isEnabled() )
    {
        // Load the 2FA login handler so its challenge logic is available.
        eZUserLoginHandler::instance( 'sevenxUser2fa' );
        if ( eZsevenxUser2faUser::startChallenge( $user ) !== true )
        {
            // startChallenge exits when redirecting to the 2FA challenge/setup page.
            // Reaching this point should not happen, but exit cleanly just in case.
            eZExecution::cleanExit();
        }
    }

    $userID = $user->attribute( 'contentobject_id' );
    sevenxAuthentication2faHelper::authLog( 'oauth_login_succeeded', 'provider=' . $provider . ' login=' . $user->attribute( 'login' ), $userID );
    eZAudit::writeAudit( 'user-login', array( 'User id' => $userID, 'User login' => $user->attribute( 'login' ) ) );
    eZUser::updateLastVisit( $userID, true );
    eZUser::setCurrentlyLoggedInUser( $user, $userID );
    eZUser::setFailedLoginAttempts( $userID, 0 );
    eZHTTPTool::redirect( '/' );
    eZExecution::cleanExit();
}

sevenxAuthentication2faHelper::authLog( 'oauth_login_failed', 'provider=' . $provider );
eZHTTPTool::redirect( '/user/login' );
eZExecution::cleanExit();
