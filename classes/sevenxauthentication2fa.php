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
  \class sevenxAuthentication2fa sevenxauthentication2fa.php
  \ingroup sevenx_authentication_2fa
  \brief Value object stored in the sevenxauthentication2fa datatype.

  Stored as JSON in the attribute's data_text:
  method, secret (encrypted when a key is configured, see
  sevenxAuthentication2faCrypto), verified, created_at, and last_step: the
  last TOTP time step that signed in, so its code cannot be used again.
  recovery: the salt and hashes of the recovery codes left (never the codes);
  setup_asked_at: when a sign-in first asked this user to set up a second step
  (Enforce2FA), the start of the grace period (sevenxAuthentication2faPolicy).
  app_passwords: the user's app passwords for the sign-in paths outside the web
  login form (id, name, channels, salt, hash, created, expires, last_used,
  last_ip; never the password itself); code_failures: wrong authenticator codes
  sent on those paths (count, since), which lock the code for a while
  (sevenxAuthentication2faSignIn).
*/
class sevenxAuthentication2fa
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var string the plain Base32 secret
     */
    private $secret;

    /**
     * @var bool
     */
    private $verified;

    /**
     * @var int|null
     */
    private $createdAt;

    /**
     * @var int|null
     */
    private $lastStep;

    /**
     * @var array|null salt, hashes
     */
    private $recovery = null;

    /**
     * @var int|null
     */
    private $setupAskedAt = null;

    /**
     * @var array[] app passwords, see sevenxAuthentication2faPolicy::newAppPassword()
     */
    private $appPasswords = array();

    /**
     * @var array|null count, since: wrong codes sent outside the web login form
     */
    private $codeFailures = null;

    public function __construct( $method = 'disabled', $secret = '', $verified = false, $createdAt = null, $lastStep = null )
    {
        $this->method = in_array( $method, array( 'disabled', 'totp', 'email' ), true ) ? $method : 'disabled';
        $this->secret = (string)$secret;
        $this->verified = (bool)$verified;
        $this->createdAt = $createdAt ? (int)$createdAt : time();
        $this->lastStep = $lastStep === null ? null : (int)$lastStep;
    }

    /**
     * Template access. A template sees the secret of an enrolment that is not
     * confirmed yet only ('secret', 'enrolment_secret'); once confirmed it is
     * never shown again.
     * @param string $name
     * @return mixed
     */
    public function attribute( $name )
    {
        switch ( $name )
        {
            case 'method':
                return $this->method;
            case 'verified':
                return $this->verified;
            case 'created_at':
                return $this->createdAt;
            case 'is_active':
                return $this->isActive();
            case 'is_enforced':
                // Whether Enforce2FA applies to the user of this field (set by the datatype)
                return $this->enforced;
            case 'recovery_codes_left':
                return sevenxAuthentication2faPolicy::recoveryCodesLeft( $this->recovery );
            case 'has_secret':
                return $this->secret !== '';
            case 'is_enrolling':
                return $this->method === 'totp' && !$this->verified && $this->secret !== '';
            case 'secret':
                // Older templates: the secret of an enrolment only, never a confirmed one
            case 'enrolment_secret':
                // The secret of an enrolment that is not confirmed yet: the user has to type it into the app
                return ( $this->method === 'totp' && !$this->verified ) ? $this->secret : '';
            case 'enrolment_secret_grouped':
                return sevenxAuthentication2faTOTP::groupSecret( $this->attribute( 'enrolment_secret' ) );
            case 'enrolment_qr':
                // Drawn on the server into a data: URI; the secret goes to no other service
                return $this->attribute( 'is_enrolling' ) ? sevenxAuthentication2faQR::pngDataUri( $this->provisioningUri() ) : '';
        }
        return null;
    }

    /**
     * The account name an authenticator app shows (set by the datatype).
     * @var string
     */
    private $account = '';

    public function setAccount( $account )
    {
        $this->account = (string)$account;
    }

    /**
     * Whether Enforce2FA applies to the user of this field (set by the datatype, not stored).
     * @var bool
     */
    private $enforced = false;

    public function setEnforced( $enforced )
    {
        $this->enforced = (bool)$enforced;
    }

    /**
     * The otpauth:// URI of an enrolment, with the site's TOTP settings.
     * @return string
     */
    public function provisioningUri()
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $s = $helper->totpSettings();
        return sevenxAuthentication2faTOTP::provisioningUri( $this->account !== '' ? $this->account : 'user', $this->secret, $helper->issuer(), $s['digits'], $s['period'], $s['algorithm'] );
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute( $name )
    {
        return in_array( $name, array( 'method', 'secret', 'verified', 'created_at', 'is_active', 'is_enforced', 'recovery_codes_left', 'has_secret', 'is_enrolling',
                                       'enrolment_secret', 'enrolment_secret_grouped', 'enrolment_qr' ), true );
    }

    /**
     * Does this configuration ask for a second step at sign-in?
     * @return bool
     */
    public function isActive()
    {
        if ( $this->method === 'email' )
            return true;
        return $this->method === 'totp' && $this->verified && $this->secret !== '';
    }

    public function method()
    {
        return $this->method;
    }

    public function setMethod( $method )
    {
        $this->method = in_array( $method, array( 'disabled', 'totp', 'email' ), true ) ? $method : 'disabled';
    }

    public function secret()
    {
        return $this->secret;
    }

    public function setSecret( $secret )
    {
        $this->secret = (string)$secret;
    }

    public function verified()
    {
        return $this->verified;
    }

    public function setVerified( $verified )
    {
        $this->verified = (bool)$verified;
    }

    public function createdAt()
    {
        return $this->createdAt;
    }

    public function lastStep()
    {
        return $this->lastStep;
    }

    public function setLastStep( $step )
    {
        $this->lastStep = $step === null ? null : (int)$step;
    }

    /**
     * The stored recovery codes (salt, hashes), or null.
     * @return array|null
     */
    public function recovery()
    {
        return $this->recovery;
    }

    /**
     * @param array|null $recovery salt, hashes (sevenxAuthentication2faPolicy::hashRecoveryCodes())
     */
    public function setRecovery( $recovery )
    {
        $this->recovery = is_array( $recovery ) && isset( $recovery['salt'], $recovery['hashes'] ) && is_array( $recovery['hashes'] )
            ? array( 'salt' => (string)$recovery['salt'], 'hashes' => array_values( array_filter( $recovery['hashes'], 'is_string' ) ) )
            : null;
    }

    /**
     * @return int|null when a sign-in first asked for a second step
     */
    public function setupAskedAt()
    {
        return $this->setupAskedAt;
    }

    /**
     * @param int|null $time
     */
    public function setSetupAskedAt( $time )
    {
        $this->setupAskedAt = $time === null ? null : (int)$time;
    }

    /**
     * The stored app passwords (never the passwords themselves).
     * @return array[]
     */
    public function appPasswords()
    {
        return $this->appPasswords;
    }

    /**
     * @param array $entries see sevenxAuthentication2faPolicy::newAppPassword(); malformed entries are left out
     */
    public function setAppPasswords( $entries )
    {
        $this->appPasswords = sevenxAuthentication2faPolicy::cleanAppPasswordEntries( is_array( $entries ) ? $entries : array() );
    }

    /**
     * @return array|null count, since
     */
    public function codeFailures()
    {
        return $this->codeFailures;
    }

    /**
     * @param array|null $failures count, since
     */
    public function setCodeFailures( $failures )
    {
        $this->codeFailures = is_array( $failures ) && isset( $failures['count'], $failures['since'] ) && (int)$failures['count'] > 0
            ? array( 'count' => (int)$failures['count'], 'since' => (int)$failures['since'] )
            : null;
    }

    /**
     * @return string JSON representation, the secret encrypted when a key is configured
     */
    public function toJson()
    {
        $data = array(
            'method'     => $this->method,
            'secret'     => sevenxAuthentication2faCrypto::encrypt( $this->secret ),
            'verified'   => $this->verified,
            'created_at' => $this->createdAt,
        );
        if ( $this->lastStep !== null )
            $data['last_step'] = $this->lastStep;
        if ( $this->recovery !== null )
            $data['recovery'] = $this->recovery;
        if ( $this->setupAskedAt !== null )
            $data['setup_asked_at'] = $this->setupAskedAt;
        if ( $this->appPasswords )
            $data['app_passwords'] = $this->appPasswords;
        if ( $this->codeFailures !== null )
            $data['code_failures'] = $this->codeFailures;
        return json_encode( $data );
    }

    /**
     * @param string $json
     * @return sevenxAuthentication2fa
     */
    public static function fromJson( $json )
    {
        if ( !$json )
            return new self();

        $data = json_decode( $json, true );
        if ( !is_array( $data ) )
            return new self();

        $value = new self(
            isset( $data['method'] ) ? $data['method'] : 'disabled',
            isset( $data['secret'] ) ? sevenxAuthentication2faCrypto::decrypt( $data['secret'] ) : '',
            isset( $data['verified'] ) ? $data['verified'] : false,
            isset( $data['created_at'] ) ? $data['created_at'] : null,
            isset( $data['last_step'] ) ? $data['last_step'] : null
        );
        if ( isset( $data['recovery'] ) )
            $value->setRecovery( $data['recovery'] );
        if ( isset( $data['setup_asked_at'] ) )
            $value->setSetupAskedAt( $data['setup_asked_at'] );
        if ( isset( $data['app_passwords'] ) )
            $value->setAppPasswords( $data['app_passwords'] );
        if ( isset( $data['code_failures'] ) )
            $value->setCodeFailures( $data['code_failures'] );
        return $value;
    }
}
