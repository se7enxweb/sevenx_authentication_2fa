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
  \class eZGoogleUser ezgoogleuser.php
  \ingroup sevenx_authentication_2fa
  \brief Google OAuth 2.0 login handler.
*/
class eZGoogleUser extends eZOAuthUser
{
    protected $provider = 'google';

    /**
     * Google returns profile info under a slightly different shape.
     * @param array $info
     * @return array
     */
    protected function normalizeUserInfo( $info )
    {
        $normalized = array(
            'id'    => isset( $info['sub'] ) ? $info['sub'] : ( isset( $info['id'] ) ? $info['id'] : '' ),
            'email' => isset( $info['email'] ) ? $info['email'] : '',
            'name'  => isset( $info['name'] ) ? $info['name'] : '',
        );
        // Google says whether it verified the address; an unverified one is refused by eZOAuthUser::handleCallback()
        if ( array_key_exists( 'email_verified', $info ) )
            $normalized['email_verified'] = $info['email_verified'];
        return $normalized;
    }

    protected function fetchUserInfo( $token )
    {
        $info = parent::fetchUserInfo( $token );
        if ( !$info )
            return false;
        return $this->normalizeUserInfo( $info );
    }
}
