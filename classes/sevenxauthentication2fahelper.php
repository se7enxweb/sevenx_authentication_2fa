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

  A pending second step lives in the visitor's own session only
  (SESSION_PENDING): which user passed the password, the method, when it
  ends, where to go afterwards, the wrong codes so far and, for an e-mail
  code, its salted hash. Nothing of it is written to the file system, so a
  code can only be used in the browser that typed the password.
*/
class sevenxAuthentication2faHelper
{
    const SESSION_PENDING = 'Sevenx2FA_Pending';
    const SESSION_SETUP = 'Sevenx2FA_Setup';
    const SESSION_ENROL_PREFIX = 'Sevenx2FA_Enrol_';

    // Older names, kept so code of other extensions that reads them still loads
    const SESSION_PENDING_USER_ID = 'Sevenx2FA_PendingUserID';
    const SESSION_PENDING_METHOD  = 'Sevenx2FA_PendingMethod';
    const SESSION_PENDING_CODE    = 'Sevenx2FA_PendingCode';
    const SESSION_PENDING_EXPIRES = 'Sevenx2FA_PendingExpires';
    const SESSION_PENDING_REDIRECT = 'Sevenx2FA_PendingRedirect';

    const SESSION_PENDING_OAUTH_STATE = 'Sevenx2FA_OAuthState';
    const SESSION_PENDING_OAUTH_PROVIDER = 'Sevenx2FA_OAuthProvider';
    const SESSION_PENDING_OAUTH_CODE_VERIFIER = 'Sevenx2FA_OAuthCodeVerifier';
    const SESSION_PENDING_OAUTH_EXPIRES = 'Sevenx2FA_OAuthExpires';
    const SESSION_PENDING_OAUTH_REDIRECT = 'Sevenx2FA_OAuthRedirect';

    const METHOD_DISABLED = 'disabled';
    const METHOD_TOTP     = 'totp';
    const METHOD_EMAIL    = 'email';

    /**
     * A safe, site-relative redirect target ('/' when the given one is not safe).
     * Absolute URLs of other hosts, protocol-relative URLs, backslashes,
     * encoded slashes and schemes are refused (doc/features/6.0/safe-redirects.md).
     * @param string $redirect
     * @return string
     */
    public static function normalizeRedirect( $redirect )
    {
        return sevenxAuthentication2faRedirect::safe( $redirect, '/' );
    }

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
     * A setting as a string, '' when it is not set.
     * @param string $block
     * @param string $variable
     * @return string
     */
    private function stringSetting( $block, $variable )
    {
        if ( !$this->ini->hasVariable( $block, $variable ) )
            return '';
        $value = $this->ini->variable( $block, $variable );
        return is_array( $value ) ? (string)reset( $value ) : trim( (string)$value );
    }

    /**
     * Is the extension enabled globally?
     * @return bool
     */
    public function isEnabled()
    {
        return strtolower( $this->stringSetting( 'General', 'Enabled' ) ) === 'enabled';
    }

    /**
     * Is 2FA enforced for every user?
     * @return bool
     */
    public function isEnforced()
    {
        return strtolower( $this->stringSetting( 'General', 'Enforce2FA' ) ) === 'enabled';
    }

    /**
     * Is fallback to email OTP allowed when TOTP is configured but unavailable?
     * @return bool
     */
    public function allowEmailFallback()
    {
        return strtolower( $this->stringSetting( 'General', 'AllowEmailFallback' ) ) === 'enabled';
    }

    /**
     * Return the configured default method.
     * @return string
     */
    public function defaultMethod()
    {
        $method = $this->stringSetting( 'General', 'DefaultMethod' );
        if ( !in_array( $method, array( self::METHOD_TOTP, self::METHOD_EMAIL ), true ) )
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
        $value = $this->stringSetting( $block, $variable );
        return is_numeric( $value ) ? (int)$value : $default;
    }

    /**
     * Return the configured TOTP issuer name.
     * @return string
     */
    public function issuer()
    {
        $issuer = $this->stringSetting( 'General', 'Issuer' );
        return $issuer !== '' ? $issuer : 'Exponential';
    }

    /**
     * The TOTP parameters: digits, period, window, algorithm and secret bytes.
     * @return array
     */
    public function totpSettings()
    {
        return array(
            'digits'    => max( 6, min( 8, $this->intSetting( 'CodeSettings', 'Length', 6 ) ) ),
            'period'    => max( 15, min( 120, $this->intSetting( 'CodeSettings', 'TimeStep', 30 ) ) ),
            'window'    => max( 0, min( 3, $this->intSetting( 'CodeSettings', 'Window', 1 ) ) ),
            'algorithm' => sevenxAuthentication2faTOTP::algorithm( $this->stringSetting( 'CodeSettings', 'Algorithm' ) ),
            'bytes'     => max( 16, min( 64, $this->intSetting( 'TOTPSettings', 'SecretLength', 20 ) ) ),
        );
    }

    /**
     * The limits of a pending second step.
     * @return array max_attempts, challenge_ttl, resend_interval, max_resends, email_ttl, setup_ttl
     */
    public function limits()
    {
        return array(
            'max_attempts'    => max( 1, $this->intSetting( 'Security', 'MaxAttempts', 5 ) ),
            'challenge_ttl'   => max( 60, $this->intSetting( 'Security', 'ChallengeTTL', 300 ) ),
            'resend_interval' => max( 0, $this->intSetting( 'Security', 'ResendInterval', 60 ) ),
            'max_resends'     => max( 0, $this->intSetting( 'Security', 'MaxResends', 3 ) ),
            'email_ttl'       => max( 60, $this->intSetting( 'CodeSettings', 'EmailTTL', 600 ) ),
            'setup_ttl'       => max( 60, $this->intSetting( 'Security', 'SetupTTL', 900 ) ),
        );
    }

    /**
     * Find the time step of a code for a secret (see sevenxAuthentication2faTOTP::matchStep()).
     * @param string $secret
     * @param string $code
     * @param int|null $lastStep
     * @return int|false
     */
    public function matchTotp( $secret, $code, $lastStep = null )
    {
        $s = $this->totpSettings();
        return sevenxAuthentication2faTOTP::matchStep( $secret, $code, $s['window'], null, $s['digits'], $s['period'], $s['algorithm'], $lastStep );
    }

    /**
     * The sevenxauthentication2fa attribute of a user's object, or null.
     * @param int $userID
     * @return eZContentObjectAttribute|null
     */
    public function userAttribute( $userID )
    {
        $user = eZUser::fetch( (int)$userID );
        if ( !$user )
            return null;

        $object = $user->contentObject();
        if ( !$object )
            return null;

        $language = $object->attribute( 'initial_language_code' );
        if ( !$language )
            $language = false;

        foreach ( $object->fetchDataMap( false, $language ) as $attribute )
        {
            if ( $attribute->attribute( 'data_type_string' ) === 'sevenxauthentication2fa' )
                return $attribute;
        }
        return null;
    }

    /**
     * Does the user's class have the 2FA attribute, so a configuration can be kept?
     * @param int $userID
     * @return bool
     */
    public function canStore( $userID )
    {
        return $this->userAttribute( $userID ) !== null;
    }

    /**
     * Retrieve the 2FA data object for a user.
     * @param int $userID
     * @return sevenxAuthentication2fa|null
     */
    public function userData( $userID )
    {
        $attribute = $this->userAttribute( $userID );
        if ( !$attribute )
            return null;
        $content = $attribute->content();
        return $content instanceof sevenxAuthentication2fa ? $content : null;
    }

    /**
     * Store a user's 2FA data in the current version of the user object.
     * @param int $userID
     * @param sevenxAuthentication2fa $data
     * @return bool
     */
    public function saveUserData( $userID, sevenxAuthentication2fa $data )
    {
        $attribute = $this->userAttribute( $userID );
        if ( !$attribute )
        {
            eZDebug::writeError( 'The class of user ' . (int)$userID . ' has no sevenxauthentication2fa attribute; nothing was stored', __METHOD__ );
            return false;
        }
        $attribute->setAttribute( 'data_text', $data->toJson() );
        $attribute->store();
        return true;
    }

    /**
     * Determine the 2FA method that applies to the given user.
     * A TOTP configuration that was never confirmed does not count.
     * @param int $userID
     * @return string
     */
    public function userMethod( $userID )
    {
        $data = $this->userData( $userID );
        if ( $data && $data->isActive() )
            return $data->method();
        if ( $data && $data->method() === self::METHOD_TOTP && $data->secret() !== '' && !$data->verified() )
        {
            // An enrolment that was never finished: not a working second step
            return $this->isEnforced() ? $this->defaultMethod() : self::METHOD_DISABLED;
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
        $code = '';
        for ( $i = 0; $i < $length; $i++ )
            $code .= (string)random_int( 0, 9 );
        return $code;
    }

    /**
     * An e-mail address with most of its local part hidden (j•••@example.com).
     * @param string $email
     * @return string
     */
    public static function maskEmail( $email )
    {
        $email = (string)$email;
        $at = strrpos( $email, '@' );
        if ( $at === false || $at === 0 )
            return '';
        $local = substr( $email, 0, $at );
        $domain = substr( $email, $at + 1 );
        return mb_substr( $local, 0, 1 ) . str_repeat( "\u{2022}", 3 ) . '@' . $domain;
    }

    /**
     * Start a pending second step in the current session.
     * @param int $userID
     * @param string $method
     * @param int $ttl seconds
     * @param string $redirect a safe target
     * @param array $extra more keys (code_hash, code_salt, sent_at ...)
     */
    public function startPending( $userID, $method, $ttl, $redirect = '/', $extra = array() )
    {
        $pending = array_merge( array(
            'user_id'  => (int)$userID,
            'method'   => $method,
            'expires'  => time() + (int)$ttl,
            'redirect' => self::normalizeRedirect( $redirect ),
            'attempts' => 0,
            'resends'  => 0,
            'sent_at'  => 0,
        ), $extra );
        eZHTTPTool::instance()->setSessionVariable( self::SESSION_PENDING, $pending );
    }

    /**
     * Replace the pending second step (after a wrong code, a new e-mail code).
     * @param array $pending
     */
    public function updatePending( array $pending )
    {
        eZHTTPTool::instance()->setSessionVariable( self::SESSION_PENDING, $pending );
    }

    /**
     * The pending second step of this session, or null when there is none or
     * it ended.
     * @return array|null
     */
    public function pending()
    {
        $http = eZHTTPTool::instance();
        if ( !$http->hasSessionVariable( self::SESSION_PENDING ) )
            return null;
        $pending = $http->sessionVariable( self::SESSION_PENDING );
        $limits = $this->limits();
        if ( !sevenxAuthentication2faAttempts::isOpen( $pending, $limits['max_attempts'], time() ) )
            return null;
        return $pending;
    }

    /**
     * Store a pending 2FA challenge (older signature, kept for other code).
     * The code or secret is not kept: a TOTP secret is read from the user's
     * object when the code is checked, an e-mail code is kept hashed.
     * @param int $userID
     * @param string $method
     * @param string $codeOrSecret for e-mail the expected code; ignored for TOTP
     * @param int $ttl
     * @param string $redirect
     */
    public function setPendingChallenge( $userID, $method, $codeOrSecret, $ttl, $redirect = '' )
    {
        $extra = array();
        if ( $method === self::METHOD_EMAIL )
        {
            $salt = bin2hex( random_bytes( 16 ) );
            $extra = array( 'code_salt' => $salt, 'code_hash' => sevenxAuthentication2faAttempts::codeHash( $codeOrSecret, $salt ), 'sent_at' => time() );
        }
        $this->startPending( $userID, $method, $ttl, $redirect, $extra );
    }

    /**
     * The pending challenge of this session (older signature; the arguments
     * are ignored: a challenge is never looked up across sessions).
     * @return array|null
     */
    public function getPendingChallenge( $code = false, $userID = false )
    {
        return $this->pending();
    }

    /**
     * Remove the pending challenge from the session.
     */
    public function removePendingChallenge()
    {
        $http = eZHTTPTool::instance();
        $http->removeSessionVariable( self::SESSION_PENDING );
    }

    /**
     * Remember that a signed-in-by-password user has to set up a second step
     * first (Enforce2FA), for SetupTTL seconds.
     * @param int $userID
     * @param string $redirect
     */
    public function startSetup( $userID, $redirect )
    {
        $limits = $this->limits();
        eZHTTPTool::instance()->setSessionVariable( self::SESSION_SETUP, array(
            'user_id'  => (int)$userID,
            'redirect' => self::normalizeRedirect( $redirect ),
            'expires'  => time() + $limits['setup_ttl'],
        ) );
    }

    /**
     * The pending setup of this session, or null.
     * @return array|null
     */
    public function pendingSetup()
    {
        $http = eZHTTPTool::instance();
        if ( !$http->hasSessionVariable( self::SESSION_SETUP ) )
            return null;
        $setup = $http->sessionVariable( self::SESSION_SETUP );
        if ( !is_array( $setup ) || empty( $setup['user_id'] ) || !isset( $setup['expires'] ) || $setup['expires'] < time() )
        {
            $http->removeSessionVariable( self::SESSION_SETUP );
            return null;
        }
        return $setup;
    }

    /**
     * End the pending setup.
     */
    public function removeSetup()
    {
        eZHTTPTool::instance()->removeSessionVariable( self::SESSION_SETUP );
    }

    /**
     * The secret of an authenticator enrolment in progress for a user, made
     * once per session so that reloading the page shows the same QR code.
     * @param int $userID
     * @param bool $create make one when there is none
     * @return string
     */
    public function enrolmentSecret( $userID, $create = true )
    {
        $http = eZHTTPTool::instance();
        $name = self::SESSION_ENROL_PREFIX . (int)$userID;
        if ( $http->hasSessionVariable( $name ) )
        {
            $secret = $http->sessionVariable( $name );
            if ( sevenxAuthentication2faTOTP::isValidSecret( $secret ) )
                return $secret;
        }
        if ( !$create )
            return '';
        $settings = $this->totpSettings();
        $secret = sevenxAuthentication2faTOTP::generateSecret( $settings['bytes'] );
        $http->setSessionVariable( $name, $secret );
        return $secret;
    }

    /**
     * Forget the enrolment secret of a user (after it was confirmed or given up).
     * @param int $userID
     */
    public function removeEnrolmentSecret( $userID )
    {
        eZHTTPTool::instance()->removeSessionVariable( self::SESSION_ENROL_PREFIX . (int)$userID );
    }

    /**
     * The page layout for the second step: the login page layout where the
     * siteaccess draws its login page on its own (LoginPage=custom), so a
     * visitor who is not signed in yet sees no menus.
     * @return string|false
     */
    public static function pageLayout()
    {
        $ini = eZINI::instance();
        if ( $ini->variable( 'SiteSettings', 'LoginPage' ) === 'custom' )
            return 'loginpagelayout.tpl';
        return false;
    }

    /**
     * Tell browsers and proxies not to keep a page of the second step
     * (it can show a secret, a QR code or who is signing in).
     */
    public static function noStore()
    {
        if ( !headers_sent() )
        {
            header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
            header( 'Pragma: no-cache' );
        }
    }

    /**
     * Write a security audit entry to var/log/auth.log (and to ezdebug).
     * @param string $action short action tag, e.g. '2fa_verify_failed'
     * @param string $details free-form details; never a code, a secret or a password
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

        $clean = function ( $value ) {
            return str_replace( array( "\r", "\n", '[', ']' ), array( ' ', ' ', '(', ')' ), (string)$value );
        };
        $ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'cli';
        $ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'cli';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'cli';
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        // A code in the address (user2fa/verify/code/123456) is not written to the log
        $uri  = preg_replace( '#(/code/)[^/]+#i', '$1***', (string)$uri );

        $message = sprintf(
            '[action=%s][user_id=%s][login=%s][ip=%s][host=%s][uri=%s][ua=%s] %s',
            $clean( $action ),
            (int)$userID,
            $clean( $login ),
            $clean( $ip ),
            $clean( $host ),
            $clean( $uri ),
            $clean( $ua ),
            $clean( $details )
        );

        eZLog::write( $message, 'auth.log', 'var/log' );
        eZDebug::writeNotice( $message, 'sevenx_authentication_2fa:auth' );
    }

    /**
     * Remove expired temporary data. Used by cronjobs and CLI cleanup tools.
     */
    public static function cleanupExpiredSessions()
    {
        self::cleanupExpiredFiles();
    }

    /**
     * Remove the pending challenge files earlier versions wrote to the cache
     * directory. They held codes and TOTP secrets in plain text, and nothing
     * reads them any more, so every one of them goes, expired or not.
     */
    public static function cleanupExpiredFiles()
    {
        $dir = eZDir::path( array( eZSys::cacheDirectory(), 'sevenx_2fa_pending' ) );
        if ( !is_dir( $dir ) )
            return;

        foreach ( glob( $dir . '/*.json' ) as $file )
        {
            @unlink( $file );
        }
    }
}
