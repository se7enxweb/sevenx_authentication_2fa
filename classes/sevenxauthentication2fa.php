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
  \class sevenxAuthentication2fa sevenxauthentication2fa.php
  \ingroup sevenx_authentication_2fa
  \brief Value object stored in the sevenxauthentication2fa datatype.

  Stored as JSON in the attribute's data_text:
  method, secret (encrypted when a key is configured, see
  sevenxAuthentication2faCrypto), verified, created_at, and last_step: the
  last TOTP time step that signed in, so its code cannot be used again.
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
        return in_array( $name, array( 'method', 'secret', 'verified', 'created_at', 'is_active', 'has_secret', 'is_enrolling',
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

        return new self(
            isset( $data['method'] ) ? $data['method'] : 'disabled',
            isset( $data['secret'] ) ? sevenxAuthentication2faCrypto::decrypt( $data['secret'] ) : '',
            isset( $data['verified'] ) ? $data['verified'] : false,
            isset( $data['created_at'] ) ? $data['created_at'] : null,
            isset( $data['last_step'] ) ? $data['last_step'] : null
        );
    }
}
