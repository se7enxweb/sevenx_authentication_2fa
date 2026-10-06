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
  \brief Login handler that asks for a second step after the password.

  Usage:
    [UserSettings]
    LoginHandler[]=sevenxUser2fa

  The handler checks the username and password. When the user has no second
  step (and none is enforced) it signs the user in as the standard handler
  does. Otherwise the user is NOT signed in: the session only remembers who
  passed the password (sevenxAuthentication2faHelper::SESSION_PENDING), and
  the browser goes to user2fa/verify, or to user2fa/setup when Enforce2FA asks
  for a method first. Only a right code there signs the user in
  (completeLogin(), which also starts a new session id).
*/
class eZsevenxUser2faUser extends eZUser
{
    /**
     * Validates credentials and either logs the user in or starts a 2FA challenge.
     * @param string $login
     * @param string $password
     * @param bool $authenticationMatch
     * @return eZUser|false
     */
    public static function loginUser( $login, $password, $authenticationMatch = false )
    {
        $helper = sevenxAuthentication2faHelper::instance();

        if ( !$helper->isEnabled() )
        {
            return parent::loginUser( $login, $password, $authenticationMatch );
        }

        $user = self::_loginUser( $login, $password, $authenticationMatch );

        if ( !$user instanceof eZUser )
        {
            // The standard handler, when it comes next, tries the same password and counts the failure itself
            if ( !self::standardHandlerFollows() )
                self::loginFailed( $user, $login );
            sevenxAuthentication2faHelper::authLog( 'login_failed', 'password step', is_numeric( $user ) ? (int)$user : 0 );
            return false;
        }

        // A user who may not use this siteaccess is refused by the login view before any second step
        if ( isset( $GLOBALS['eZCurrentAccess'] ) && !$user->canLoginToSiteAccess( $GLOBALS['eZCurrentAccess'] ) )
        {
            return $user;
        }

        if ( self::startChallenge( $user ) === true )
        {
            self::completeLogin( $user );
            return $user;
        }

        return false;
    }

    /**
     * Is 'standard' one of the login handlers after this one?
     * @return bool
     */
    protected static function standardHandlerFollows()
    {
        $handlers = (array)eZINI::instance()->variable( 'UserSettings', 'LoginHandler' );
        $seen = false;
        foreach ( $handlers as $handler )
        {
            if ( $seen && $handler === 'standard' )
                return true;
            if ( $handler === 'sevenxUser2fa' )
                $seen = true;
        }
        return false;
    }

    /**
     * Where to go after signing in: the login form's RedirectURI, the session's
     * RedirectAfterLogin, the page viewed last, else '/'. Always a safe path.
     * @return string
     */
    public static function loginRedirect()
    {
        $http = eZHTTPTool::instance();
        $candidates = array();
        if ( $http->hasPostVariable( 'RedirectURI' ) )
            $candidates[] = $http->postVariable( 'RedirectURI' );
        if ( $http->hasSessionVariable( 'RedirectAfterLogin', false ) )
            $candidates[] = $http->sessionVariable( 'RedirectAfterLogin' );
        if ( $http->hasSessionVariable( 'LastAccessesURI', false ) )
            $candidates[] = $http->sessionVariable( 'LastAccessesURI' );

        foreach ( $candidates as $candidate )
        {
            if ( !is_string( $candidate ) || trim( $candidate ) === '' || trim( $candidate ) === '/' )
                continue;
            $safe = sevenxAuthentication2faRedirect::safe( $candidate, '' );
            if ( $safe !== '' )
                return $safe;
        }
        return '/';
    }

    /**
     * Start a 2FA challenge for the given user if one is required.
     * Exits the request when redirecting to the challenge or setup page.
     * @param eZUser $user
     * @param string|null $redirect where to go after the second step (checked again)
     * @return bool true when no 2FA challenge is required
     */
    public static function startChallenge( eZUser $user, $redirect = null )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $userID = (int)$user->attribute( 'contentobject_id' );
        $method = $helper->userMethod( $userID );
        $redirect = $redirect === null ? self::loginRedirect() : sevenxAuthentication2faHelper::normalizeRedirect( $redirect );

        // No half-finished second step of an earlier attempt is carried over
        $helper->removePendingChallenge();

        if ( $method === sevenxAuthentication2faHelper::METHOD_DISABLED )
        {
            if ( $helper->isEnforced() )
            {
                sevenxAuthentication2faHelper::authLog( '2fa_enforced_setup_redirect', '', $userID );
                return self::redirectToSetup( $user, $redirect );
            }
            sevenxAuthentication2faHelper::authLog( '2fa_not_required', '', $userID );
            return true;
        }

        if ( $method === sevenxAuthentication2faHelper::METHOD_TOTP )
        {
            $data = $helper->userData( $userID );
            if ( !$data || !$data->isActive() || $data->method() !== sevenxAuthentication2faHelper::METHOD_TOTP )
            {
                // TOTP is asked for but this user has no working authenticator. One that was set up and broke
                // (its secret unreadable, e.g. after the key changed) may fall back to e-mail codes; a user who
                // never set one up (Enforce2FA with DefaultMethod=totp) sets it up now
                $configured = $data && $data->method() === sevenxAuthentication2faHelper::METHOD_TOTP && $data->verified();
                if ( $configured && $helper->allowEmailFallback() && $user->attribute( 'email' ) )
                    $method = sevenxAuthentication2faHelper::METHOD_EMAIL;
                else
                    return self::redirectToSetup( $user, $redirect );
            }
        }

        sevenxAuthentication2faHelper::authLog( '2fa_challenge_started', 'method=' . $method, $userID );
        $limits = $helper->limits();

        if ( $method === sevenxAuthentication2faHelper::METHOD_EMAIL )
        {
            if ( !$user->attribute( 'email' ) )
                return self::redirectToSetup( $user, $redirect );
            sevenxAuthentication2faEmail::sendCode( $user, $redirect );
        }
        else
        {
            $helper->startPending( $userID, sevenxAuthentication2faHelper::METHOD_TOTP, $limits['challenge_ttl'], $redirect );
        }

        // Redirect to the 2FA verification view.
        $url = 'user2fa/verify';
        eZURI::transformURI( $url );
        eZSession::stop();
        eZHTTPTool::instance()->redirect( $url );
        eZExecution::cleanExit();
        return false;
    }

    /**
     * Sign a user in after the second step (or when none is needed): the
     * kernel's house keeping (new session id, last visit, failed logins reset,
     * the audit record), plus the audit entry of earlier versions.
     * @param eZUser $user
     * @return eZUser
     */
    public static function completeLogin( eZUser $user )
    {
        $userID = (int)$user->attribute( 'contentobject_id' );
        parent::loginSucceeded( $user );
        eZAudit::writeAudit( 'user-login', array( 'User id' => $userID, 'User login' => $user->attribute( 'login' ) ) );
        sevenxAuthentication2faHelper::authLog( 'login_succeeded', '', $userID );
        return $user;
    }

    /**
     * Count a wrong second-step code as a failed login of the account, so the
     * account lock (site.ini [UserSettings] MaxNumberOfFailedLogin) applies.
     * @param int $userID
     */
    public static function countFailedCode( $userID )
    {
        eZUser::setFailedLoginAttempts( (int)$userID );
    }

    /**
     * Redirect the user to the 2FA setup page because a method has to be set up first.
     * @param eZUser $user
     * @param string $redirect URL to go to after setup is complete
     */
    public static function redirectToSetup( eZUser $user, $redirect = '/' )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $userID = (int)$user->attribute( 'contentobject_id' );
        $helper->startSetup( $userID, $redirect );
        sevenxAuthentication2faHelper::authLog( '2fa_setup_redirect', '', $userID );
        $url = 'user2fa/setup';
        eZURI::transformURI( $url );
        eZSession::stop();
        eZHTTPTool::instance()->redirect( $url );
        eZExecution::cleanExit();
        return false;
    }
}
