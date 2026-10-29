<?php
//
// sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension
// Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
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
    /** The second step answered with one of the account's passkeys (the kernel's, expPasskeyService) */
    const METHOD_PASSKEY  = 'passkey';

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
     * Is Enforce2FA switched on? Whom it applies to: isEnforcedFor().
     * @return bool
     */
    public function isEnforced()
    {
        return strtolower( $this->stringSetting( 'General', 'Enforce2FA' ) ) === 'enabled';
    }

    /**
     * The values of an array setting, trimmed, without empty ones.
     * @param string $block
     * @param string $variable
     * @return string[]
     */
    public function arraySetting( $block, $variable )
    {
        if ( !$this->ini->hasVariable( $block, $variable ) )
            return array();
        $values = array();
        foreach ( (array)$this->ini->variable( $block, $variable ) as $value )
        {
            $value = trim( (string)$value );
            if ( $value !== '' )
                $values[] = $value;
        }
        return $values;
    }

    /**
     * Object ids of user groups named in a setting: a number is the group's node id (as the administration
     * shows it), anything else its object remote id (the same on every installation made from the same data,
     * e.g. Administrator users 9b47a45624b023b1a76c73b74d704acf, Editors 3c160cca19fb135f83bd02d911f04db2).
     * Names that match no object are left out and logged.
     * @param string[] $identifiers
     * @return int[]
     */
    public static function groupObjectIDs( array $identifiers )
    {
        $ids = array();
        foreach ( $identifiers as $identifier )
        {
            $object = null;
            if ( ctype_digit( $identifier ) )
            {
                $node = eZContentObjectTreeNode::fetch( (int)$identifier );
                if ( $node instanceof eZContentObjectTreeNode )
                    $object = $node->object();
            }
            else
            {
                $object = eZContentObject::fetchByRemoteID( $identifier );
            }
            if ( $object instanceof eZContentObject )
                $ids[] = (int)$object->attribute( 'id' );
            else
                eZDebug::writeError( 'sevenxauthentication2fa.ini names a user group that does not exist: ' . $identifier, __METHOD__ );
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * Does Enforce2FA apply to this user: switched on, the user in EnforcedGroups[] (or none listed), and not
     * taken out by ExemptGroups[] or ExemptEmailDomains[] (sevenxAuthentication2faPolicy::applies())?
     * @param int|eZUser $user
     * @return bool
     */
    public function isEnforcedFor( $user )
    {
        if ( !$this->isEnforced() )
            return false;
        if ( !$user instanceof eZUser )
            $user = eZUser::fetch( (int)$user );
        if ( !$user instanceof eZUser )
            return false;

        $enforcedNames = $this->arraySetting( 'General', 'EnforcedGroups' );
        return sevenxAuthentication2faPolicy::applies(
            true,
            $enforcedNames ? self::groupObjectIDs( $enforcedNames ) : array(),
            self::groupObjectIDs( $this->arraySetting( 'General', 'ExemptGroups' ) ),
            $this->arraySetting( 'General', 'ExemptEmailDomains' ),
            (array)$user->groups(),
            (string)$user->attribute( 'email' ),
            (bool)$enforcedNames
        );
    }

    /**
     * GracePeriodDays: days a user whom Enforce2FA asks for a second step may still sign in without one.
     * @return int
     */
    public function graceDays()
    {
        return max( 0, min( 365, $this->intSetting( 'General', 'GracePeriodDays', 0 ) ) );
    }

    /**
     * Seconds left of a user's grace period (0: none, the setup comes first).
     * @param int $userID
     * @return int
     */
    public function graceLeft( $userID )
    {
        $days = $this->graceDays();
        if ( $days <= 0 )
            return 0;
        $data = $this->userData( $userID );
        // Without a 2FA field nothing can be remembered, so there is no grace period either
        if ( !$data && !$this->canStore( $userID ) )
            return 0;
        return sevenxAuthentication2faPolicy::graceLeft( $days, $data ? $data->setupAskedAt() : null, time() );
    }

    /**
     * Remember when a sign-in first asked a user to set up a second step (the start of the grace period).
     * @param int $userID
     */
    public function markSetupAsked( $userID )
    {
        if ( $this->graceDays() <= 0 || !$this->canStore( $userID ) )
            return;
        $data = $this->userData( $userID );
        if ( !$data )
            $data = new sevenxAuthentication2fa();
        if ( $data->setupAskedAt() !== null )
            return;
        $data->setSetupAskedAt( time() );
        $this->saveUserData( $userID, $data );
    }

    /**
     * The settings of the sign-in paths outside the web login form ([OtherSignInPaths]): whether a path is guarded,
     * whether accounts that chose a second step themselves are guarded too, on which paths an authenticator code
     * counts, and how many wrong codes lock it for how long. Missing settings mean the defaults: no path guarded
     * (Guard[] empty: every path signs in as before), codes only on the expservices login once it is guarded.
     * @return array guard (any path guarded), guarded (channels), opted_in, code_channels, code_max_attempts,
     *               code_lock_seconds
     */
    public function otherPathSettings()
    {
        $has = function ( $variable ) {
            return $this->ini && $this->ini->hasVariable( 'OtherSignInPaths', $variable );
        };
        $guarded = $has( 'Guard' )
            ? sevenxAuthentication2faPolicy::channelList( $this->arraySetting( 'OtherSignInPaths', 'Guard' ) )
            : sevenxAuthentication2faPolicy::DEFAULT_GUARDED_CHANNELS;
        $guard = (bool)$guarded;
        $codes =$has( 'CodeChannels' )
            ? sevenxAuthentication2faPolicy::channelList( $this->arraySetting( 'OtherSignInPaths', 'CodeChannels' ) )
            : array( 'expservices' );
        return array(
            'guard' => $guard,
            'guarded' => $guarded,
            'opted_in' => strtolower( $this->stringSetting( 'OtherSignInPaths', 'GuardOptedIn' ) ) === 'enabled',
            'code_channels' => $codes,
            'code_max_attempts' => max( 1, $this->intSetting( 'Security', 'MaxAttempts', 5 ) ),
            'code_lock_seconds' => max( 60, $this->intSetting( 'OtherSignInPaths', 'CodeLockSeconds', 900 ) ),
        );
    }

    /**
     * The settings of app passwords ([AppPasswords]): on or off, the paths they may be made for (those of Channels[]
     * that are guarded in [OtherSignInPaths] Guard[], never more than rest, webdav and soap; by default none, so the
     * setup page shows no app passwords until a site guards one of them), how many a user may have, for how many days a new one works (0: until revoked) and how
     * often "last used" is written.
     * @return array enabled, channels, max, expiry_days, last_used_interval
     */
    public function appPasswordSettings()
    {
        $hasChannels = $this->ini && $this->ini->hasVariable( 'AppPasswords', 'Channels' );
        $enabled = $this->ini && $this->ini->hasVariable( 'AppPasswords', 'AppPasswords' )
            ? strtolower( $this->stringSetting( 'AppPasswords', 'AppPasswords' ) ) !== 'disabled' : true;
        $paths = $this->otherPathSettings();
        $configured = $hasChannels ? $this->arraySetting( 'AppPasswords', 'Channels' ) : sevenxAuthentication2faPolicy::APP_PASSWORD_CHANNELS;
        return array(
            'enabled' => $enabled,
            // only where the password alone is refused: SOAP, not guarded by default, has no app passwords then
            'channels' => sevenxAuthentication2faPolicy::appPasswordChoices( $configured, $paths['guard'] ? $paths['guarded'] : array() ),
            'max' => max( 0, min( 50, $this->intSetting( 'AppPasswords', 'MaxPerUser', 10 ) ) ),
            'expiry_days' => max( 0, min( 3650, $this->intSetting( 'AppPasswords', 'ExpiryDays', 365 ) ) ),
            'last_used_interval' => max( 0, $this->intSetting( 'AppPasswords', 'LastUsedInterval', 900 ) ),
        );
    }

    /**
     * Record an app password event in the kernel's audit trail (expSignInSecondFactor::audit()) and in auth.log.
     * @param string $event create, revoke, use
     * @param eZUser $user
     * @param array $entry the stored entry (only its description is recorded, never its salt or hash)
     * @param array $extra
     */
    public static function auditAppPassword( $event, eZUser $user, array $entry, array $extra = array() )
    {
        $described = sevenxAuthentication2faPolicy::describeAppPassword( $entry, time() );
        unset( $described['expired'] );
        self::authLog( '2fa_app_password_' . $event, 'id=' . $described['id'] . ' channels=' . implode( ',', $described['channels'] ),
                       (int)$user->attribute( 'contentobject_id' ) );
        if ( !class_exists( 'expSignInSecondFactor' ) )
            return;
        $names = array( 'create' => expSignInSecondFactor::AUDIT_APP_PASSWORD_CREATE,
                        'revoke' => expSignInSecondFactor::AUDIT_APP_PASSWORD_REVOKE,
                        'use' => expSignInSecondFactor::AUDIT_APP_PASSWORD_USE );
        if ( isset( $names[$event] ) )
            expSignInSecondFactor::audit( $names[$event], $user, array_merge( $described, $extra ) );
    }

    /**
     * Drop every app password of a configuration (the second step was removed or reset: they belonged to it) and
     * record each as revoked.
     * @param int $userID
     * @param sevenxAuthentication2fa|null $old the configuration they were in
     * @param string $reason
     */
    public function auditDroppedAppPasswords( $userID, $old, $reason )
    {
        if ( !$old instanceof sevenxAuthentication2fa || !$old->appPasswords() )
            return;
        $user = eZUser::fetch( (int)$userID );
        if ( !$user instanceof eZUser )
            return;
        foreach ( $old->appPasswords() as $entry )
            self::auditAppPassword( 'revoke', $user, $entry, array( 'reason' => (string)$reason ) );
    }

    /**
     * [Security] RecoveryCodes: how many recovery codes an authenticator gets (0: none).
     * @return int
     */
    public function recoveryCodeCount()
    {
        return max( 0, min( 20, $this->intSetting( 'Security', 'RecoveryCodes', 10 ) ) );
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
     * Whether a passkey can answer the second step of this account: the kernel's passkeys are on and in use here
     * (expPasskeyService) and the account has at least one. A passkey is a strong second factor (something one has,
     * unlocked by a PIN, a fingerprint or a face), so it counts like the app's code; an account that must have a
     * second step (Enforce2FA) and has a passkey needs no app. Only the web sign-in form asks: the other paths
     * (REST, WebDAV, SOAP, expservices) never take a passkey.
     * @param eZUser $user
     * @return bool
     */
    public static function hasPasskey( eZUser $user )
    {
        if ( !class_exists( 'expPasskeyService' ) || !class_exists( 'expPasskey' ) )
            return false;
        try
        {
            $service = new expPasskeyService();
            return $service->available() && expPasskey::countByUser( (int)$user->attribute( 'contentobject_id' ) ) > 0;
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'Passkeys could not be looked up: ' . $e->getMessage(), __METHOD__ );
            return false;
        }
    }

    /**
     * The options of the browser's passkey dialog for the second step of a pending sign-in (a new challenge, bound
     * to the account), JSON; '' when a passkey cannot answer it.
     * @param eZUser $user
     * @return string
     */
    public static function passkeyOptions( eZUser $user )
    {
        if ( !self::hasPasskey( $user ) )
            return '';
        $service = new expPasskeyService();
        $options = $service->reauthOptions( $user );
        return $options ? json_encode( $options, JSON_UNESCAPED_SLASHES ) : '';
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
        // Nothing set up, or an enrolment that was never finished (not a working second step)
        if ( $this->isEnforcedFor( $userID ) )
            return $this->defaultMethod();
        return self::METHOD_DISABLED;
    }

    /**
     * Give a user's authenticator a new set of recovery codes (the old ones stop working) and store their
     * hashes. The codes themselves are returned once, to be shown to the user, and never stored.
     * @param int $userID
     * @param sevenxAuthentication2fa|null $data the configuration to add them to (default: the stored one)
     * @return string[] the codes; empty when there are none to give (RecoveryCodes=0, no authenticator, not stored)
     */
    public function issueRecoveryCodes( $userID, $data = null )
    {
        $count = $this->recoveryCodeCount();
        if ( $data === null )
            $data = $this->userData( $userID );
        if ( $count <= 0 || !$data instanceof sevenxAuthentication2fa || !$data->isActive() || $data->method() !== self::METHOD_TOTP )
            return array();
        $codes = sevenxAuthentication2faPolicy::newRecoveryCodes( $count );
        $data->setRecovery( sevenxAuthentication2faPolicy::hashRecoveryCodes( $codes ) );
        if ( !$this->saveUserData( $userID, $data ) )
            return array();
        self::authLog( '2fa_recovery_codes_issued', 'count=' . count( $codes ), $userID );
        return $codes;
    }

    /**
     * Sign in with a recovery code in place of the authenticator's code: true when it was one of the user's
     * codes, which is then used up.
     * @param int $userID
     * @param string $code as typed
     * @return bool
     */
    public function useRecoveryCode( $userID, $code )
    {
        $data = $this->userData( $userID );
        if ( !$data || !$data->isActive() || $data->method() !== self::METHOD_TOTP )
            return false;
        $left = sevenxAuthentication2faPolicy::useRecoveryCode( $data->recovery(), $code );
        if ( $left === false )
            return false;
        $data->setRecovery( $left );
        if ( !$this->saveUserData( $userID, $data ) )
            return false;
        self::authLog( '2fa_recovery_code_used', 'left=' . count( $left['hashes'] ), $userID );
        return true;
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
     * Write a security audit entry to var/log/auth.log (and to the debug output).
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
        // auth.log is the audit trail; the debug output only shows it (a notice copied every login into notice.log)
        eZDebug::writeDebug( $message, 'sevenx_authentication_2fa:auth' );

        self::kernelAudit( $action, $details, (int)$userID );
    }

    /**
     * The actions of auth.log that are also events of the kernel's audit (expSignInSecondFactor::audit()): the
     * changes of an account's second step, which reach the user audit log, the account's security activity
     * (user/security) and the security alerts by e-mail. action => array( kernel constant, after keys taken from the
     * details, result, fixed after values ).
     */
    const KERNEL_EVENTS = array(
        '2fa_setup_totp_ok' => array( 'AUDIT_2FA_ENROLL', array(), 'success', array( 'method' => 'totp' ) ),
        '2fa_setup_email' => array( 'AUDIT_2FA_ENROLL', array(), 'success', array( 'method' => 'email' ) ),
        '2fa_setup_disabled' => array( 'AUDIT_2FA_REMOVE', array(), 'success', array( 'how' => 'disabled' ) ),
        '2fa_setup_reset' => array( 'AUDIT_2FA_REMOVE', array(), 'success', array( 'how' => 'reset' ) ),
        '2fa_recovery_codes_issued' => array( 'AUDIT_2FA_RECOVERY_ISSUED', array( 'count' ), 'success', array() ),
        '2fa_recovery_code_used' => array( 'AUDIT_2FA_RECOVERY_USED', array( 'left' ), 'success', array() ),
        '2fa_verify_failed' => array( 'AUDIT_2FA_VERIFY_FAILED', array( 'method' ), 'failed', array() ),
    );

    /**
     * The kernel event of an auth.log action, or null (no such event, or a kernel without these events).
     *
     * @param string $action
     * @param string $details "key=value key=value"
     * @return array|null name, after, result
     */
    public static function kernelEvent( $action, $details )
    {
        if ( !isset( self::KERNEL_EVENTS[$action] ) || !class_exists( 'expSignInSecondFactor' ) )
            return null;
        list( $constant, $keys, $result, $fixed ) = self::KERNEL_EVENTS[$action];
        if ( !defined( 'expSignInSecondFactor::' . $constant ) )
            return null;
        $after = $fixed;
        if ( $keys && preg_match_all( '/\b([a-z_]+)=([^\s]+)/', (string)$details, $m, PREG_SET_ORDER ) )
            foreach ( $m as $pair )
                if ( in_array( $pair[1], $keys, true ) )
                    $after[$pair[1]] = ctype_digit( $pair[2] ) ? (int)$pair[2] : $pair[2];
        return array( 'name' => constant( 'expSignInSecondFactor::' . $constant ), 'after' => $after, 'result' => $result );
    }

    /**
     * Records an auth.log action as a kernel audit event when it is one (kernelEvent()); never a code or a secret.
     *
     * @param string $action
     * @param string $details
     * @param int $userID
     */
    protected static function kernelAudit( $action, $details, $userID )
    {
        if ( $userID <= 0 )
            return;
        $event = self::kernelEvent( $action, $details );
        if ( $event === null || !method_exists( 'expSignInSecondFactor', 'audit' ) )
            return;
        $user = eZUser::fetch( $userID );
        if ( !$user instanceof eZUser )
            return;
        try
        {
            expSignInSecondFactor::audit( $event['name'], $user, $event['after'], $event['result'] );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'Could not record the audit event: ' . $e->getMessage(), __METHOD__ );
        }
    }

    /**
     * Names the second step of a sign-in for the kernel's list of sessions ("Your devices"), before the sign-in is
     * completed: totp, email, recovery or oauth. Without the kernel's session tracking nothing happens.
     *
     * @param string $method expUserSession::METHODS (two_factor, single_sign_on)
     * @param string $detail
     */
    public static function nameSignIn( $method, $detail )
    {
        if ( class_exists( 'expUserSessionTracker' ) && method_exists( 'expUserSessionTracker', 'setMethodHint' ) )
            expUserSessionTracker::setMethodHint( $method, $detail );
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
