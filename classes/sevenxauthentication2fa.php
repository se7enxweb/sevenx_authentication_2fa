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
*/
class sevenxAuthentication2fa
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var string
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

    public function __construct( $method = 'disabled', $secret = '', $verified = false, $createdAt = null )
    {
        $this->method = $method;
        $this->secret = $secret;
        $this->verified = (bool)$verified;
        $this->createdAt = $createdAt ? (int)$createdAt : time();
    }

    /**
     * eZ template / attribute access helper.
     * @param string $name
     * @return mixed
     */
    public function attribute( $name )
    {
        switch ( $name )
        {
            case 'method':
                return $this->method;
            case 'secret':
                return $this->secret;
            case 'verified':
                return $this->verified;
            case 'created_at':
                return $this->createdAt;
        }
        return null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute( $name )
    {
        return in_array( $name, array( 'method', 'secret', 'verified', 'created_at' ) );
    }

    public function method()
    {
        return $this->method;
    }

    public function setMethod( $method )
    {
        $this->method = $method;
    }

    public function secret()
    {
        return $this->secret;
    }

    public function setSecret( $secret )
    {
        $this->secret = $secret;
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

    /**
     * @return string JSON representation
     */
    public function toJson()
    {
        return json_encode( array(
            'method'    => $this->method,
            'secret'    => $this->secret,
            'verified'  => $this->verified,
            'created_at'=> $this->createdAt,
        ) );
    }

    /**
     * @param string $json
     * @return sevenxAuthentication2fa|null
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
            isset( $data['secret'] ) ? $data['secret'] : '',
            isset( $data['verified'] ) ? $data['verified'] : false,
            isset( $data['created_at'] ) ? $data['created_at'] : null
        );
    }
}
