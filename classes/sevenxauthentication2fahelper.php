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
  \class sevenxAuthentication2faHelper sevenxauthentication2fahelper.php
  \ingroup sevenx_authentication_2fa
  \brief Central helper for 2FA configuration, session state and provider access.
*/
class sevenxAuthentication2faHelper
{
    const SESSION_PENDING_USER_ID = 'Sevenx2FA_PendingUserID';
    const SESSION_PENDING_METHOD  = 'Sevenx2FA_PendingMethod';
    const SESSION_PENDING_CODE    = 'Sevenx2FA_PendingCode';
    const SESSION_PENDING_EXPIRES = 'Sevenx2FA_PendingExpires';
    const SESSION_PENDING_REDIRECT = 'Sevenx2FA_PendingRedirect';
    const SESSION_PENDING_OAUTH_STATE = 'Sevenx2FA_OAuthState';
    const SESSION_PENDING_OAUTH_PROVIDER = 'Sevenx2FA_OAuthProvider';
    const SESSION_PENDING_OAUTH_CODE_VERIFIER = 'Sevenx2FA_OAuthCodeVerifier';
    const SESSION_TOTP_SECRET_PREFIX = 'Sevenx2FA_TOTPSecret_';

    const METHOD_DISABLED = 'disabled';
    const METHOD_TOTP     = 'totp';
    const METHOD_EMAIL    = 'email';

    /**
     * Singleton instance.
     * @var sevenxAuthentication2faHelper
     */
    private static $instance;

    /**
     * @var eZINI
     */
    private $ini;

    public static function instance()
    {
        if ( !isset( self::$instance ) )
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
    }

    /**
     * @return eZINI
     */
    public function ini()
    {
        return $this->ini;
    }

    /**
     * Is the extension enabled globally?
     * @return bool
     */
    public function isEnabled()
    {
        return strtolower( $this->ini->variable( 'General', 'Enabled' ) ) === 'enabled';
    }

    /**
     * Is 2FA enforced for every user?
     * @return bool
     */
    public function isEnforced()
    {
        return strtolower( $this->ini->variable( 'General', 'Enforce2FA' ) ) === 'enabled';
    }

    /**
     * Is fallback to email OTP allowed when TOTP is configured but unavailable?
     * @return bool
     */
    public function allowEmailFallback()
    {
        return strtolower( $this->ini->variable( 'General', 'AllowEmailFallback' ) ) === 'enabled';
    }

    /**
     * Return the configured default method.
     * @return string
     */
    public function defaultMethod()
    {
        $method = $this->ini->variable( 'General', 'DefaultMethod' );
        if ( !$method )
            $method = self::METHOD_DISABLED;
        return $method;
    }

    /**
     * Read an INI integer with a fallback.
     * @param string $block
     * @param string $variable
     * @param int $default
     * @return int
     */
    public function intSetting( $block, $variable, $default )
    {
        if ( $this->ini->hasVariable( $block, $variable ) )
        {
            $value = $this->ini->variable( $block, $variable );
            if ( is_array( $value ) )
                $value = reset( $value );
            if ( is_numeric( $value ) )
                return (int)$value;
        }
        return $default;
    }

    /**
     * Return the configured TOTP issuer name.
     * @return string
     */
    public function issuer()
    {
        $issuer = $this->ini->variable( 'General', 'Issuer' );
        if ( !$issuer )
            $issuer = 'Exponential';
        return $issuer;
    }

    /**
     * Retrieve the 2FA data object for a user.
     * @param int $userID
     * @return sevenxAuthentication2fa|null
     */
    public function userData( $userID )
    {
        $user = eZUser::fetch( $userID );
        if ( !$user )
            return null;

        $object = $user->contentObject();
        if ( !$object )
            return null;

        $language = $object->attribute( 'initial_language_code' );
        if ( !$language )
            $language = 'eng-US';

        foreach ( $object->fetchDataMap( false, $language ) as $attribute )
        {
            if ( $attribute->attribute( 'data_type_string' ) === 'sevenxauthentication2fa' )
            {
                return $attribute->content();
            }
        }
        return null;
    }

    /**
     * Determine the 2FA method that applies to the given user.
     * @param int $userID
     * @return string
     */
    public function userMethod( $userID )
    {
        $data = $this->userData( $userID );
        if ( $data )
        {
            $method = $data->method();
            if ( $method && $method !== self::METHOD_DISABLED )
                return $method;
        }
        if ( $this->isEnforced() )
            return $this->defaultMethod();
        return self::METHOD_DISABLED;
    }

    /**
     * Generate a cryptographically secure random numeric code of the given length.
     * @param int $length
     * @return string
     */
    public static function randomCode( $length )
    {
        $length = max( 1, (int)$length );
        $digits = '0123456789';
        $code = '';
        if ( function_exists( 'random_int' ) )
        {
            for ( $i = 0; $i < $length; $i++ )
                $code .= $digits[random_int( 0, 9 )];
        }
        else
        {
            for ( $i = 0; $i < $length; $i++ )
                $code .= $digits[mt_rand( 0, 9 )];
        }
        return $code;
    }

    /**
     * Store a pending 2FA challenge in the current session.
     * @param int $userID
     * @param string $method
     * @param string $codeOrSecret for email this is the expected code, for TOTP this is the secret
     * @param int $ttl
     * @param string $redirect
     */
    public function setPendingChallenge( $userID, $method, $codeOrSecret, $ttl, $redirect = '' )
    {
        $http = eZHTTPTool::instance();
        $http->setSessionVariable( self::SESSION_PENDING_USER_ID, $userID );
        $http->setSessionVariable( self::SESSION_PENDING_METHOD, $method );
        if ( $method === self::METHOD_TOTP )
            $http->setSessionVariable( self::SESSION_PENDING_CODE, $codeOrSecret );
        else
            $http->setSessionVariable( self::SESSION_PENDING_CODE, $codeOrSecret );
        $http->setSessionVariable( self::SESSION_PENDING_EXPIRES, time() + $ttl );
        $http->setSessionVariable( self::SESSION_PENDING_REDIRECT, $redirect );
    }

    /**
     * Read pending challenge data from the current session.
     * @return array|null
     */
    public function getPendingChallenge()
    {
        $http = eZHTTPTool::instance();
        if ( !$http->hasSessionVariable( self::SESSION_PENDING_USER_ID ) )
            return null;

        return array(
            'user_id'  => $http->sessionVariable( self::SESSION_PENDING_USER_ID ),
            'method'   => $http->sessionVariable( self::SESSION_PENDING_METHOD ),
            'code'     => $http->sessionVariable( self::SESSION_PENDING_CODE ),
            'expires'  => $http->sessionVariable( self::SESSION_PENDING_EXPIRES ),
            'redirect' => $http->sessionVariable( self::SESSION_PENDING_REDIRECT ),
        );
    }

    /**
     * Write a security audit entry to var/log/auth.log (and to ezdebug).
     * @param string $action short action tag, e.g. '2fa_verify_failed'
     * @param string $details free-form details
     * @param int|null $userID user id if known, null uses current user
     */
    public static function authLog( $action, $details = '', $userID = null )
    {
        $currentUser = eZUser::currentUser();
        if ( !$currentUser || !$currentUser->isRegistered() )
            $currentUser = null;

        if ( $userID === null && $currentUser )
            $userID = $currentUser->attribute( 'contentobject_id' );

        $login = 'anonymous';
        if ( $userID )
        {
            $user = eZUser::fetch( $userID );
            if ( $user )
                $login = $user->attribute( 'login' );
        }
        elseif ( $currentUser )
        {
            $login = $currentUser->attribute( 'login' );
        }

        $ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'cli';
        $ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'cli';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'cli';
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

        $message = sprintf(
            '[action=%s][user_id=%s][login=%s][ip=%s][host=%s][uri=%s][ua=%s] %s',
            $action,
            (int)$userID,
            $login,
            $ip,
            $host,
            $uri,
            $ua,
            $details
        );

        eZLog::write( $message, 'auth.log', 'var/log' );
        eZDebug::writeNotice( $message, 'sevenx_authentication_2fa:auth' );
    }

    /**
     * Remove the pending challenge from the current session.
     */
    public function removePendingChallenge()
    {
        $http = eZHTTPTool::instance();
        $vars = array(
            self::SESSION_PENDING_USER_ID,
            self::SESSION_PENDING_METHOD,
            self::SESSION_PENDING_CODE,
            self::SESSION_PENDING_EXPIRES,
            self::SESSION_PENDING_REDIRECT,
        );
        foreach ( $vars as $var )
        {
            if ( $http->hasSessionVariable( $var ) )
                $http->removeSessionVariable( $var );
        }
    }

    /**
     * Remove expired temporary data. Used by cronjobs and CLI cleanup tools.
     */
    public static function cleanupExpiredSessions()
    {
        $http = eZHTTPTool::instance();
        foreach ( $_SESSION as $key => $value )
        {
            if ( strpos( $key, 'Sevenx2FA_' ) === 0 && is_array( $value ) && isset( $value['expires'] ) && $value['expires'] < time() )
            {
                $http->removeSessionVariable( $key );
            }
        }
    }
}
