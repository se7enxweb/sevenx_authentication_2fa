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
  \class eZInstagramUser ezinstagramuser.php
  \ingroup sevenx_authentication_2fa
  \brief Instagram OAuth 2.0 login handler.
*/
class eZInstagramUser extends eZOAuthUser
{
    protected $provider = 'instagram';

    /**
     * Instagram Graph API returns a username and account id.
     * @param array $info
     * @return array
     */
    protected function normalizeUserInfo( $info )
    {
        return array(
            'id'    => isset( $info['id'] ) ? $info['id'] : '',
            'email' => '',
            'name'  => isset( $info['username'] ) ? $info['username'] : '',
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
