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

/*!
  \class eZsevenxUser2faUser ezsevenxuser2fauser.php
  \ingroup sevenx_authentication_2fa
  \brief Login handler that enforces 2FA after password authentication.

  Usage:
    [UserSettings]
    LoginHandler[]=sevenxUser2fa

  The handler validates the username/password, then either completes the login
  (when 2FA is disabled) or redirects the browser to the user2fa/verify view
  to collect the OTP code.
*/
class eZsevenxUser2faUser extends eZUser
{
    /**
     * Validates credentials and either logs the user in or starts a 2FA challenge.
     * @param string $login
     * @param string $password
     * @param bool $authenticationMatch
     * @return eZUser|false|array
     */
    public static function loginUser( $login, $password, $authenticationMatch = false )
    {
        $helper = sevenxAuthentication2faHelper::instance();

        if ( !$helper->isEnabled() )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_handler_disabled', 'falling back to standard login for login=' . $login, 0 );
            return self::standardLogin( $login, $password, $authenticationMatch );
        }

        sevenxAuthentication2faHelper::authLog( '2fa_login_attempt', 'login=' . $login, 0 );

        $user = self::_loginUser( $login, $password, $authenticationMatch );

        if ( !$user instanceof eZUser )
        {
            self::loginFailed( $user, $login );
            return false;
        }

        if ( self::startChallenge( $user ) === true )
        {
            return self::loginSucceeded( $user );
        }

        return false;
    }

    /**
     * Start a 2FA challenge for the given user if one is required.
     * Exits the request when redirecting to the challenge or setup page.
     * @param eZUser $user
     * @param string|null $redirect
     * @return bool true when no 2FA challenge is required
     */
    public static function startChallenge( eZUser $user, $redirect = null )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $userID = $user->attribute( 'contentobject_id' );
        $method = $helper->userMethod( $userID );

        if ( $method === sevenxAuthentication2faHelper::METHOD_DISABLED )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_not_required', 'user=' . $user->attribute( 'login' ), $userID );
            return true;
        }

        sevenxAuthentication2faHelper::authLog( '2fa_challenge_started', 'method=' . $method, $userID );

        if ( $redirect === null )
        {
            $http = eZHTTPTool::instance();
            $redirect = '/';
            if ( $http->hasSessionVariable( 'RedirectAfterLogin' ) )
            {
                $redirect = $http->sessionVariable( 'RedirectAfterLogin' );
            }
            elseif ( $http->hasPostVariable( 'RedirectURI' ) )
            {
                $redirect = $http->postVariable( 'RedirectURI' );
            }
        }

        if ( $method === sevenxAuthentication2faHelper::METHOD_TOTP )
        {
            $data = $helper->userData( $userID );
            if ( !$data || !$data->secret() )
            {
                // User is supposed to use TOTP but has no secret; fall back to email if allowed.
                if ( $helper->allowEmailFallback() && $user->attribute( 'email' ) )
                {
                    $method = sevenxAuthentication2faHelper::METHOD_EMAIL;
                }
                else
                {
                    return self::redirectToSetup( $user );
                }
            }

            if ( $method === sevenxAuthentication2faHelper::METHOD_TOTP )
            {
                // For TOTP the pending "code" session value is actually the secret.
                // The user reads the current code from their authenticator app.
                $helper->setPendingChallenge( $userID, $method, $data->secret(), 300, $redirect );
            }
        }

        if ( $method === sevenxAuthentication2faHelper::METHOD_EMAIL )
        {
            sevenxAuthentication2faEmail::sendCode( $user, $redirect );
        }

        // Redirect to the 2FA verification view.
        $url = 'user2fa/verify';
        eZURI::transformURI( $url );
        eZHTTPTool::instance()->redirect( $url );
        eZExecution::cleanExit();
        return false;
    }

    /**
     * Complete a normal login after the user has passed 2FA or has 2FA disabled.
     * @param eZUser $user
     * @return eZUser
     */
    protected static function loginSucceeded( $user )
    {
        $userID = $user->attribute( 'contentobject_id' );
        sevenxAuthentication2faHelper::authLog( 'login_succeeded', 'login=' . $user->attribute( 'login' ), $userID );
        eZAudit::writeAudit( 'user-login', array( 'User id' => $userID, 'User login' => $user->attribute( 'login' ) ) );
        eZUser::updateLastVisit( $userID, true );
        eZUser::setCurrentlyLoggedInUser( $user, $userID );
        eZUser::setFailedLoginAttempts( $userID, 0 );
        return $user;
    }

    /**
     * Shortcut for when the extension is disabled.
     * @param string $login
     * @param string $password
     * @param bool $authenticationMatch
     * @return eZUser|false
     */
    protected static function standardLogin( $login, $password, $authenticationMatch )
    {
        $user = self::_loginUser( $login, $password, $authenticationMatch );
        if ( $user instanceof eZUser )
            return self::loginSucceeded( $user );
        return false;
    }

    /**
     * Log failed login attempts.
     * @param mixed $user
     * @param string $login
     */
    protected static function loginFailed( $user, $login )
    {
        $userID = is_numeric( $user ) ? $user : false;
        sevenxAuthentication2faHelper::authLog( 'login_failed', 'login=' . $login, $userID );
        eZAudit::writeAudit( 'user-failed-login', array( 'User id' => $userID, 'User login' => $login ) );
        if ( $userID )
            eZUser::setFailedLoginAttempts( $userID, eZUser::failedLoginAttemptsByUserID( $userID ) + 1 );
    }

    /**
     * Redirect the user to the 2FA setup page if TOTP is required but not configured.
     * @param eZUser $user
     */
    public static function redirectToSetup( eZUser $user )
    {
        $http = eZHTTPTool::instance();
        $userID = $user->attribute( 'contentobject_id' );
        $http->setSessionVariable( 'Sevenx2FA_SetupUserID', $userID );
        sevenxAuthentication2faHelper::authLog( '2fa_setup_redirect', 'login=' . $user->attribute( 'login' ), $userID );
        $url = 'user2fa/setup';
        eZURI::transformURI( $url );
        $http->redirect( $url );
        eZExecution::cleanExit();
    }
}
