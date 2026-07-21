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
  \class eZIdmeUser ezidmeuser.php
  \ingroup sevenx_authentication_2fa
  \brief ID.me OAuth 2.0 / OpenID Connect login handler.

  ID.me uses the authorization-code flow with PKCE. Community verification is
  requested through the configured scope (for example "military", "student",
  "teacher", "responder", "government"). The handler can parse both the
  ID.me "attributes.json" response and the OIDC /userinfo JSON response.
*/
class eZIdmeUser extends eZOAuthUser
{
    protected $provider = 'idme';

    /**
     * Require ClientID, ClientSecret and a non-empty community scope.
     * @return bool
     */
    public function isConfigured()
    {
        if ( !parent::isConfigured() )
            return false;

        $scope = trim( (string)$this->setting( 'Scope' ) );
        if ( !$scope )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_idme_scope_missing', 'provider=idme' );
            return false;
        }

        return true;
    }

    /**
     * Add ID.me specific authorization parameters: op, eid and nonce.
     * @return array
     */
    protected function extraAuthorizationParams()
    {
        $params = array();

        $op = trim( (string)$this->setting( 'Op' ) );
        if ( $op )
            $params['op'] = $op;

        $eid = trim( (string)$this->setting( 'EID' ) );
        if ( $eid )
            $params['eid'] = $eid;

        $nonce = trim( (string)$this->setting( 'Nonce' ) );
        if ( $nonce )
            $params['nonce'] = $nonce;

        return $params;
    }

    /**
     * Normalize the ID.me userinfo response to the common keys:
     *   id, email, name
     *
     * Supports both the OAuth 2.0 attributes.json format and the OIDC userinfo
     * JSON format.
     *
     * @param array $data
     * @return array
     */
    protected function normalizeUserInfo( $data )
    {
        $info = array(
            'id'    => '',
            'email' => '',
            'name'  => '',
        );

        // OIDC /userinfo format
        if ( isset( $data['sub'] ) )
        {
            $info['id']    = $data['sub'];
            if ( isset( $data['email'] ) )
                $info['email'] = $data['email'];
            $first = isset( $data['given_name'] ) ? $data['given_name'] : '';
            $last  = isset( $data['family_name'] ) ? $data['family_name'] : '';
            $info['name'] = trim( $first . ' ' . $last );
            return $info;
        }

        // attributes.json format: { attributes: [ { handle, value }, ... ], status: [ ... ] }
        if ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) )
        {
            $attributes = array();
            foreach ( $data['attributes'] as $attribute )
            {
                if ( isset( $attribute['handle'] ) )
                    $attributes[$attribute['handle']] = isset( $attribute['value'] ) ? $attribute['value'] : '';
            }

            if ( isset( $attributes['uuid'] ) )
                $info['id'] = $attributes['uuid'];
            if ( isset( $attributes['email'] ) )
                $info['email'] = $attributes['email'];

            $first = isset( $attributes['fname'] ) ? $attributes['fname'] : '';
            $last  = isset( $attributes['lname'] ) ? $attributes['lname'] : '';
            $info['name'] = trim( $first . ' ' . $last );
            return $info;
        }

        // Fallback: try the common top-level keys used by OIDC
        if ( isset( $data['uuid'] ) )
            $info['id'] = $data['uuid'];
        if ( isset( $data['email'] ) )
            $info['email'] = $data['email'];
        if ( isset( $data['fname'] ) || isset( $data['lname'] ) )
        {
            $first = isset( $data['fname'] ) ? $data['fname'] : '';
            $last  = isset( $data['lname'] ) ? $data['lname'] : '';
            $info['name'] = trim( $first . ' ' . $last );
        }

        return $info;
    }

    /**
     * Override fetchUserInfo so we can normalize the ID.me payload.
     * @param string $token
     * @return array|false
     */
    protected function fetchUserInfo( $token )
    {
        $url = $this->setting( 'UserInfoURL' );
        $result = $this->httpGet( $url, array( 'Authorization' => 'Bearer ' . $token ) );
        if ( !$result )
            return false;

        $data = json_decode( $result, true );
        if ( !is_array( $data ) )
            return false;

        return $this->normalizeUserInfo( $data );
    }
}
