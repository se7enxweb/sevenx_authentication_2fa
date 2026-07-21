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
  \class eZOAuthUser ezoauthuser.php
  \ingroup sevenx_authentication_2fa
  \brief Base class for OAuth 2.0 social-login handlers.

  Subclasses define the provider name and can override any step of the OAuth
  flow.  The default implementation follows the standard authorization-code
  grant and expects JSON userinfo responses.
*/
abstract class eZOAuthUser extends eZUser
{
    /**
     * Provider key, e.g. 'google', 'facebook'.
     * @var string
     */
    protected $provider = 'oauth';

    /**
     * @return string
     */
    public function provider()
    {
        return $this->provider;
    }

    /**
     * Build the full callback URI for this provider.
     * @return string
     */
    public function callbackUri()
    {
        $redirectUri = trim( (string)$this->setting( 'RedirectURI' ) );
        if ( $redirectUri )
            return $redirectUri;

        $url = 'user2fa/callback/' . $this->provider;
        eZURI::transformURI( $url, false, 'full' );
        return $url;
    }

    /**
     * Provider INI block name.  Defaults to the provider key with first
     * character uppercased.
     * @return string
     */
    public function iniBlock()
    {
        return ucfirst( $this->provider );
    }

    /**
     * Read a provider setting with fallback.
     * @param string $variable
     * @param string $default
     * @return string
     */
    protected function setting( $variable, $default = '' )
    {
        $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
        $block = $this->iniBlock();
        if ( $ini->hasVariable( $block, $variable ) )
        {
            $value = $ini->variable( $block, $variable );
            if ( is_array( $value ) )
                $value = implode( ' ', $value );
            return trim( $value );
        }
        return $default;
    }

    /**
     * Is this provider enabled in configuration?
     * This controls whether UI elements (buttons, links) are shown.
     * @return bool
     */
    public function isEnabled( $useCache = true )
    {
        $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
        if ( strtolower( $ini->variable( 'SocialLogin', 'Enabled' ) ) !== 'enabled' )
            return false;
        if ( strtolower( $this->setting( 'Enabled' ) ) !== 'enabled' )
            return false;
        return true;
    }

    /**
     * Is this provider fully configured with credentials and endpoints?
     * This is checked before performing OAuth requests.
     * @return bool
     */
    public function isConfigured()
    {
        if ( !$this->isEnabled() )
            return false;
        if ( !$this->setting( 'ClientID' ) || !$this->setting( 'ClientSecret' ) )
            return false;
        if ( !$this->setting( 'AuthorizationURL' ) || !$this->setting( 'TokenURL' ) || !$this->setting( 'UserInfoURL' ) )
            return false;
        return true;
    }

    /**
     * Called when the login handler is asked to collect user info. Redirects
     * the browser to the OAuth authorization endpoint.
     * @return array
     */
    public function preCollectUserInfo()
    {
        if ( !$this->isConfigured() )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_pre_collect_not_configured', 'provider=' . $this->provider );
            return array( 'module' => 'user', 'function' => 'login' );
        }

        sevenxAuthentication2faHelper::authLog( 'oauth_authorization_redirect', 'provider=' . $this->provider . ' url=' . $this->setting( 'AuthorizationURL' ) );

        $state = $this->generateState();
        $http = eZHTTPTool::instance();
        $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE, $state );
        $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_PROVIDER, $this->provider );

        $codeVerifier = '';
        if ( $this->usePKCE() )
        {
            $codeVerifier = $this->generateCodeVerifier();
            $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER, $codeVerifier );
        }

        $url = $this->authorizationUrl( $state, $codeVerifier );
        $http->redirect( $url );
        eZExecution::cleanExit();
        return array();
    }

    /**
     * Build the authorization URL.
     * @param string $state
     * @param string $codeVerifier
     * @return string
     */
    protected function authorizationUrl( $state, $codeVerifier = '' )
    {
        $params = array(
            'client_id'     => $this->setting( 'ClientID' ),
            'redirect_uri'  => $this->callbackUri(),
            'response_type' => 'code',
            'scope'         => $this->setting( 'Scope' ),
            'state'         => $state,
        );

        if ( $codeVerifier !== '' )
        {
            $params['code_challenge'] = $this->generateCodeChallenge( $codeVerifier );
            $params['code_challenge_method'] = 'S256';
        }

        $params = array_merge( $params, $this->extraAuthorizationParams() );

        return $this->setting( 'AuthorizationURL' ) . '?' . $this->buildQuery( $params );
    }

    /**
     * Extra authorization parameters for providers that need them (op, eid, nonce, etc.).
     * @return array
     */
    protected function extraAuthorizationParams()
    {
        return array();
    }

    /**
     * Handle the OAuth callback and return an authenticated eZUser.
     * @return eZUser|false
     */
    public function handleCallback()
    {
        if ( !$this->isConfigured() )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_handler_not_configured', 'provider=' . $this->provider );
            return false;
        }

        $http = eZHTTPTool::instance();

        if ( !$http->hasGetVariable( 'code' ) || !$http->hasGetVariable( 'state' ) )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_callback_missing_params', 'provider=' . $this->provider );
            return false;
        }

        $code = $http->getVariable( 'code' );
        $state = $http->getVariable( 'state' );
        $storedState = $http->sessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE );

        if ( !$storedState || $state !== $storedState )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_callback_state_mismatch', 'provider=' . $this->provider );
            return false;
        }

        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE );
        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_PROVIDER );

        $token = $this->fetchToken( $code );
        if ( !$token )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_token_fetch_failed', 'provider=' . $this->provider );
            return false;
        }

        $info = $this->fetchUserInfo( $token );
        if ( !$info )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_userinfo_fetch_failed', 'provider=' . $this->provider );
            return false;
        }

        return $this->findOrCreateUser( $info );
    }

    /**
     * Exchange the authorization code for an access token.
     * @param string $code
     * @return string|false
     */
    protected function fetchToken( $code )
    {
        $params = array(
            'client_id'     => $this->setting( 'ClientID' ),
            'client_secret' => $this->setting( 'ClientSecret' ),
            'redirect_uri'  => $this->callbackUri(),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
        );

        $http = eZHTTPTool::instance();
        $codeVerifier = $http->sessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER );
        if ( $codeVerifier )
        {
            $params['code_verifier'] = $codeVerifier;
            $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER );
        }

        $result = $this->httpPost( $this->setting( 'TokenURL' ), $params );
        if ( !$result )
            return false;

        $data = json_decode( $result, true );
        if ( isset( $data['access_token'] ) )
            return $data['access_token'];

        // Some providers return the token in the query string of the response body.
        parse_str( $result, $query );
        if ( isset( $query['access_token'] ) )
            return $query['access_token'];

        return false;
    }

    /**
     * Fetch the user profile from the provider.
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
        return is_array( $data ) ? $data : false;
    }

    /**
     * Find an existing user by e-mail or create a new one.
     * @param array $info
     * @return eZUser|false
     */
    protected function findOrCreateUser( $info )
    {
        $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
        $matchField = $ini->variable( 'SocialLogin', 'AuthenticationMatch' );
        if ( !$matchField )
            $matchField = 'email';

        $value = isset( $info[$matchField] ) ? $info[$matchField] : '';
        if ( !$value )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_no_match_value', 'provider=' . $this->provider . ' match_field=' . $matchField );
            return false;
        }

        $user = eZUser::fetchByEmail( $value );
        if ( $user instanceof eZUser )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_user_found', 'provider=' . $this->provider . ' login=' . $user->attribute( 'login' ), $user->attribute( 'contentobject_id' ) );
            return $user;
        }

        if ( strtolower( $ini->variable( 'SocialLogin', 'AutoCreateUser' ) ) === 'enabled' )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_user_create', 'provider=' . $this->provider . ' ' . $matchField . '=' . $value );
            return $this->createUser( $info );
        }

        sevenxAuthentication2faHelper::authLog( 'oauth_user_not_found_no_autocreate', 'provider=' . $this->provider . ' ' . $matchField . '=' . $value );
        return false;
    }

    /**
     * Create a new user account from the OAuth profile.
     * @param array $info
     * @return eZUser|false
     */
    protected function createUser( $info )
    {
        $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
        $parentNodeID = (int)$ini->variable( 'SocialLogin', 'DefaultUserGroupNodeID' );
        if ( !$parentNodeID )
            $parentNodeID = 12;

        $name = isset( $info['name'] ) ? $info['name'] : $info['email'];
        $email = isset( $info['email'] ) ? $info['email'] : '';
        $login = isset( $info['email'] ) ? $info['email'] : ( isset( $info['id'] ) ? $info['id'] : '' );

        if ( !$login )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_no_login', 'provider=' . $this->provider );
            return false;
        }

        $parentNode = eZContentObjectTreeNode::fetch( $parentNodeID );
        if ( !$parentNode )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_parent_missing', 'provider=' . $this->provider . ' parent_node_id=' . $parentNodeID );
            return false;
        }

        $class = eZContentClass::fetchByIdentifier( 'user' );
        if ( !$class )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_class_missing', 'provider=' . $this->provider );
            return false;
        }

        $password = sevenxAuthentication2faHelper::randomCode( 32 );
        $hashType = eZINI::instance()->variable( 'UserSettings', 'HashType' );
        $hash = eZUser::createHash( $login, $password, eZUser::site(), eZUser::hashType() );

        $attributes = array(
            'user_account' => $login . '|' . $email . '|' . $hash . '|' . $hashType . '|1',
            'first_name'   => $name,
        );

        $params = array();
        $params['class_identifier'] = 'user';
        $params['parent_node_id']   = $parentNodeID;
        $params['attributes']       = $attributes;

        $contentObject = eZContentFunctions::createAndPublishObject( $params );
        if ( !$contentObject )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_failed', 'provider=' . $this->provider . ' login=' . $login );
            return false;
        }

        $newUser = eZUser::fetch( $contentObject->attribute( 'id' ) );
        sevenxAuthentication2faHelper::authLog( 'oauth_user_created', 'provider=' . $this->provider . ' login=' . $login, $newUser ? $newUser->attribute( 'contentobject_id' ) : 0 );
        return $newUser;
    }

    /**
     * Generate a random state parameter for CSRF protection.
     * @return string
     */
    protected function generateState()
    {
        return bin2hex( function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 ) );
    }

    /**
     * Return true when PKCE should be used for this provider.
     * @return bool
     */
    protected function usePKCE()
    {
        return strtolower( (string)$this->setting( 'UsePKCE' ) ) === 'enabled';
    }

    /**
     * Generate a PKCE code verifier.
     * @return string
     */
    protected function generateCodeVerifier()
    {
        $bytes = function_exists( 'random_bytes' ) ? random_bytes( 32 ) : openssl_random_pseudo_bytes( 32 );
        return $this->base64UrlEncode( $bytes );
    }

    /**
     * Generate a PKCE code challenge (S256) from a verifier.
     * @param string $verifier
     * @return string
     */
    protected function generateCodeChallenge( $verifier )
    {
        return $this->base64UrlEncode( hash( 'sha256', $verifier, true ) );
    }

    /**
     * Base64-url encode (RFC 4648 §5) without trailing padding.
     * @param string $data
     * @return string
     */
    protected function base64UrlEncode( $data )
    {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Build an application/x-www-form-urlencoded query string.
     * @param array $params
     * @return string
     */
    protected function buildQuery( $params )
    {
        $parts = array();
        foreach ( $params as $key => $value )
        {
            $parts[] = urlencode( $key ) . '=' . urlencode( $value );
        }
        return implode( '&', $parts );
    }

    /**
     * Perform a GET request using cURL or file_get_contents fallback.
     * @param string $url
     * @param array $headers
     * @return string|false
     */
    protected function httpGet( $url, $headers = array() )
    {
        return $this->httpRequest( 'GET', $url, null, $headers );
    }

    /**
     * Perform a POST request using cURL or file_get_contents fallback.
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return string|false
     */
    protected function httpPost( $url, $params, $headers = array() )
    {
        return $this->httpRequest( 'POST', $url, $params, $headers );
    }

    /**
     * HTTP request helper.
     * @param string $method
     * @param string $url
     * @param array|null $postParams
     * @param array $headers
     * @return string|false
     */
    protected function httpRequest( $method, $url, $postParams = null, $headers = array() )
    {
        if ( function_exists( 'curl_init' ) )
        {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

            $headerList = array();
            if ( $method === 'POST' )
            {
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $this->buildQuery( (array)$postParams ) );
                if ( !isset( $headers['Content-Type'] ) )
                    $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
            foreach ( $headers as $k => $v )
                $headerList[] = $k . ': ' . $v;
            if ( $headerList )
                curl_setopt( $ch, CURLOPT_HTTPHEADER, $headerList );

            $result = curl_exec( $ch );
            curl_close( $ch );
            return $result;
        }

        $opts = array(
            'http' => array(
                'method' => $method,
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postParams ? $this->buildQuery( $postParams ) : ''
            )
        );
        foreach ( $headers as $k => $v )
            $opts['http']['header'] .= $k . ': ' . $v . "\r\n";

        $context = stream_context_create( $opts );
        return @file_get_contents( $url, false, $context );
    }
}
