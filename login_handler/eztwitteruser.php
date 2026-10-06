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
  \class eZTwitterUser eztwitteruser.php
  \ingroup sevenx_authentication_2fa
  \brief Twitter / X OAuth 2.0 login handler.
*/
class eZTwitterUser extends eZOAuthUser
{
    protected $provider = 'twitter';

    /**
     * The settings block is [TwitterX] (sevenxauthentication2fa.ini); [Twitter] is read when a site has one.
     * @return string
     */
    public function iniBlock()
    {
        $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
        return $ini->hasGroup( 'Twitter' ) ? 'Twitter' : 'TwitterX';
    }

    public function displayName()
    {
        $name = $this->setting( 'DisplayName' );
        return $name !== '' ? $name : 'X';
    }

    /**
     * The Twitter / X v2 API returns user data nested under a 'data' key.
     * @param array $info
     * @return array
     */
    protected function normalizeUserInfo( $info )
    {
        if ( isset( $info['data'] ) && is_array( $info['data'] ) )
            $info = $info['data'];

        return array(
            'id'    => isset( $info['id'] ) ? $info['id'] : '',
            'email' => isset( $info['email'] ) ? $info['email'] : '',
            'name'  => isset( $info['name'] ) ? $info['name'] : ( isset( $info['username'] ) ? $info['username'] : '' ),
        );
    }

    protected function fetchUserInfo( $token )
    {
        $info = parent::fetchUserInfo( $token );
        if ( !$info )
            return false;
        return $this->normalizeUserInfo( $info );
    }
}
