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

  The state sent to the provider is random, kept in the session together
  with the provider it was sent to and the time, and accepted once, for that
  provider, within STATE_TTL seconds. A provider that says an e-mail address
  is not verified (email_verified false) is not trusted with it.
*/
abstract class eZOAuthUser extends eZUser
{
    const STATE_TTL = 600;

    /**
     * Provider key, e.g. 'google', 'facebook'.
     * @var string
     */
    protected $provider = 'oauth';

    /**
     * Why the last callback did not sign anyone in: cancelled, state,
     * not_configured, no_account, unverified_email or failed.
     * @var string
     */
    protected $lastError = '';

    /**
     * A provider key as it may appear in a URL: lower case letters and digits.
     * @param mixed $key
     * @return string '' when it is not one
     */
    public static function providerKey( $key )
    {
        $key = is_string( $key ) ? strtolower( trim( $key ) ) : '';
        return preg_match( '/^[a-z][a-z0-9]{0,31}$/', $key ) ? $key : '';
    }

    /**
     * The handler of a provider: the class eZ<Provider>User, when it is an
     * eZOAuthUser. Nothing else is instantiated.
     * @param string $key
     * @return eZOAuthUser|null
     */
    public static function handlerFor( $key )
    {
        $key = self::providerKey( $key );
        if ( $key === '' )
            return null;
        $className = 'eZ' . ucfirst( $key ) . 'User';
        if ( !class_exists( $className ) || !is_subclass_of( $className, 'eZOAuthUser' ) )
            return null;
        $reflection = new ReflectionClass( $className );
        if ( $reflection->isAbstract() )
            return null;
        return new $className();
    }

    /**
     * @return string
     */
    public function provider()
    {
        return $this->provider;
    }

    /**
     * The provider's name for people (DisplayName of its settings, else its block name).
     * @return string
     */
    public function displayName()
    {
        $name = $this->setting( 'DisplayName' );
        return $name !== '' ? $name : $this->iniBlock();
    }

    /**
     * @return string
     */
    public function lastError()
    {
        return $this->lastError;
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
        eZURI::transformURI( $url, false, 'full', false );
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
            return trim( (string)$value );
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
        if ( !$ini->hasVariable( 'SocialLogin', 'Enabled' ) || strtolower( $ini->variable( 'SocialLogin', 'Enabled' ) ) !== 'enabled' )
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
        foreach ( array( 'AuthorizationURL', 'TokenURL', 'UserInfoURL' ) as $endpoint )
        {
            // Every endpoint is https: the code, the client secret and the token travel over them
            if ( stripos( $this->setting( $endpoint ), 'https://' ) !== 0 )
                return false;
        }
        return true;
    }

    /**
     * Remember where to go after this social login (checked by the safe
     * redirect rules; '/' when it is not safe or not given).
     * @param string $target
     */
    public function setReturnTarget( $target )
    {
        eZHTTPTool::instance()->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_REDIRECT,
            sevenxAuthentication2faHelper::normalizeRedirect( $target ) );
    }

    /**
     * The target remembered by setReturnTarget(), once.
     * @return string
     */
    public function takeReturnTarget()
    {
        $http = eZHTTPTool::instance();
        $name = sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_REDIRECT;
        $target = $http->hasSessionVariable( $name ) ? $http->sessionVariable( $name ) : '/';
        $http->removeSessionVariable( $name );
        return sevenxAuthentication2faHelper::normalizeRedirect( $target );
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

        sevenxAuthentication2faHelper::authLog( 'oauth_authorization_redirect', 'provider=' . $this->provider );

        $state = $this->generateState();
        $http = eZHTTPTool::instance();
        $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE, $state );
        $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_PROVIDER, $this->provider );
        $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_EXPIRES, time() + self::STATE_TTL );

        $codeVerifier = '';
        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER );
        if ( $this->usePKCE() )
        {
            $codeVerifier = $this->generateCodeVerifier();
            $http->setSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER, $codeVerifier );
        }

        $url = $this->authorizationUrl( $state, $codeVerifier );
        eZSession::stop();
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

        $base = $this->setting( 'AuthorizationURL' );
        return $base . ( strpos( $base, '?' ) === false ? '?' : '&' ) . $this->buildQuery( $params );
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
     * Check the state of a callback against the one this session sent, and
     * forget it (a state is used once).
     * @param mixed $state
     * @return bool
     */
    protected function consumeState( $state )
    {
        $http = eZHTTPTool::instance();
        $storedState = $http->hasSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE )
            ? $http->sessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE ) : '';
        $storedProvider = $http->hasSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_PROVIDER )
            ? $http->sessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_PROVIDER ) : '';
        $expires = $http->hasSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_EXPIRES )
            ? (int)$http->sessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_EXPIRES ) : 0;

        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_STATE );
        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_PROVIDER );
        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_EXPIRES );

        return is_string( $state ) && is_string( $storedState ) && $storedState !== ''
            && hash_equals( $storedState, $state )
            && $storedProvider === $this->provider
            && $expires >= time();
    }

    /**
     * Handle the OAuth callback and return an authenticated eZUser.
     * @return eZUser|false
     */
    public function handleCallback()
    {
        $this->lastError = '';
        if ( !$this->isConfigured() )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_handler_not_configured', 'provider=' . $this->provider );
            $this->lastError = 'not_configured';
            return false;
        }

        $http = eZHTTPTool::instance();
        $state = $http->hasGetVariable( 'state' ) ? $http->getVariable( 'state' ) : '';
        $stateOk = $this->consumeState( $state );

        if ( $http->hasGetVariable( 'error' ) )
        {
            // The visitor said no at the provider, or the provider refused
            sevenxAuthentication2faHelper::authLog( 'oauth_callback_error', 'provider=' . $this->provider );
            $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER );
            $this->lastError = $stateOk ? 'cancelled' : 'state';
            return false;
        }

        if ( !$http->hasGetVariable( 'code' ) || !is_string( $http->getVariable( 'code' ) ) )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_callback_missing_params', 'provider=' . $this->provider );
            $this->lastError = $stateOk ? 'failed' : 'state';
            return false;
        }

        if ( !$stateOk )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_callback_state_mismatch', 'provider=' . $this->provider );
            $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER );
            $this->lastError = 'state';
            return false;
        }

        $token = $this->fetchToken( $http->getVariable( 'code' ) );
        if ( !$token )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_token_fetch_failed', 'provider=' . $this->provider );
            $this->lastError = 'failed';
            return false;
        }

        $info = $this->fetchUserInfo( $token );
        if ( !$info )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_userinfo_fetch_failed', 'provider=' . $this->provider );
            $this->lastError = 'failed';
            return false;
        }

        if ( array_key_exists( 'email_verified', $info ) && !in_array( $info['email_verified'], array( true, 'true', 1, '1' ), true ) )
        {
            // An address the provider did not verify proves nothing about who owns it
            sevenxAuthentication2faHelper::authLog( 'oauth_unverified_email', 'provider=' . $this->provider );
            $this->lastError = 'unverified_email';
            return false;
        }

        $user = $this->findOrCreateUser( $info );
        if ( !$user && $this->lastError === '' )
            $this->lastError = 'no_account';
        return $user;
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
        $codeVerifier = $http->hasSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER )
            ? $http->sessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER ) : '';
        $http->removeSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING_OAUTH_CODE_VERIFIER );
        if ( $codeVerifier )
            $params['code_verifier'] = $codeVerifier;

        $result = $this->httpPost( $this->setting( 'TokenURL' ), $params, array( 'Accept' => 'application/json' ) );
        if ( !$result )
            return false;

        $data = json_decode( $result, true );
        if ( is_array( $data ) && isset( $data['access_token'] ) && is_string( $data['access_token'] ) )
            return $data['access_token'];

        // Some providers return the token in the query string of the response body.
        parse_str( $result, $query );
        if ( isset( $query['access_token'] ) && is_string( $query['access_token'] ) )
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
        $result = $this->httpGet( $url, array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ) );
        if ( !$result )
            return false;

        $data = json_decode( $result, true );
        return is_array( $data ) ? $data : false;
    }

    /**
     * The login of an account made for, or matched by, a provider's user id.
     * @param string $id
     * @return string
     */
    protected function providerLogin( $id )
    {
        return $this->provider . '_' . preg_replace( '/[^A-Za-z0-9_.\-]/', '', (string)$id );
    }

    /**
     * Find an existing user by e-mail (AuthenticationMatch=email) or by the
     * provider's user id (AuthenticationMatch=id: the account whose login is
     * <provider>_<id>), or create a new one.
     * @param array $info
     * @return eZUser|false
     */
    protected function findOrCreateUser( $info )
    {
        $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
        $matchField = $ini->hasVariable( 'SocialLogin', 'AuthenticationMatch' ) ? $ini->variable( 'SocialLogin', 'AuthenticationMatch' ) : 'email';
        if ( $matchField !== 'id' )
            $matchField = 'email';

        $value = isset( $info[$matchField] ) && is_scalar( $info[$matchField] ) ? trim( (string)$info[$matchField] ) : '';
        if ( $value === '' )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_no_match_value', 'provider=' . $this->provider . ' match_field=' . $matchField );
            return false;
        }

        $user = $matchField === 'id' ? eZUser::fetchByName( $this->providerLogin( $value ) ) : eZUser::fetchByEmail( $value );
        if ( $user instanceof eZUser )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_user_found', 'provider=' . $this->provider, $user->attribute( 'contentobject_id' ) );
            return $user;
        }

        if ( $ini->hasVariable( 'SocialLogin', 'AutoCreateUser' ) && strtolower( $ini->variable( 'SocialLogin', 'AutoCreateUser' ) ) === 'enabled' )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_user_create', 'provider=' . $this->provider . ' match_field=' . $matchField );
            return $this->createUser( $info );
        }

        sevenxAuthentication2faHelper::authLog( 'oauth_user_not_found_no_autocreate', 'provider=' . $this->provider . ' match_field=' . $matchField );
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

        $email = isset( $info['email'] ) && is_string( $info['email'] ) ? trim( $info['email'] ) : '';
        $name = isset( $info['name'] ) && is_string( $info['name'] ) && trim( $info['name'] ) !== '' ? trim( $info['name'] ) : $email;
        $id = isset( $info['id'] ) && is_scalar( $info['id'] ) ? (string)$info['id'] : '';
        $login = $id !== '' ? $this->providerLogin( $id ) : $email;

        if ( $login === '' || $email === '' || !eZMail::validate( $email ) )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_no_login', 'provider=' . $this->provider );
            return false;
        }
        if ( eZUser::fetchByName( $login ) || eZUser::fetchByEmail( $email ) )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_exists', 'provider=' . $this->provider );
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

        // A password nobody knows: the account signs in through the provider (or after a password reset)
        $password = bin2hex( random_bytes( 24 ) );
        $hash = eZUser::createHash( $login, $password, eZUser::site(), eZUser::hashType() );

        $attributes = array(
            'user_account' => $login . '|' . $email . '|' . $hash . '|' . eZUser::passwordHashTypeName( eZUser::hashType() ) . '|1',
            'first_name'   => $name,
        );

        $params = array();
        $params['class_identifier'] = 'user';
        $params['parent_node_id']   = $parentNodeID;
        $params['attributes']       = $attributes;

        $contentObject = eZContentFunctions::createAndPublishObject( $params );
        if ( !$contentObject )
        {
            sevenxAuthentication2faHelper::authLog( 'oauth_create_user_failed', 'provider=' . $this->provider );
            return false;
        }

        $newUser = eZUser::fetch( $contentObject->attribute( 'id' ) );
        sevenxAuthentication2faHelper::authLog( 'oauth_user_created', 'provider=' . $this->provider, $newUser ? $newUser->attribute( 'contentobject_id' ) : 0 );
        return $newUser;
    }

    /**
     * Generate a random state parameter for CSRF protection.
     * @return string
     */
    protected function generateState()
    {
        return bin2hex( random_bytes( 32 ) );
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
        return $this->base64UrlEncode( random_bytes( 32 ) );
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
            $parts[] = urlencode( $key ) . '=' . urlencode( (string)$value );
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
     * HTTP request helper: https only, no redirects (the client secret and
     * the token are not sent on to another address), certificates checked,
     * 15 seconds at most.
     * @param string $method
     * @param string $url
     * @param array|null $postParams
     * @param array $headers
     * @return string|false
     */
    protected function httpRequest( $method, $url, $postParams = null, $headers = array() )
    {
        if ( stripos( (string)$url, 'https://' ) !== 0 )
            return false;

        if ( function_exists( 'curl_init' ) )
        {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
            curl_setopt( $ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS );

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
            $status = (int)curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
            if ( PHP_VERSION_ID < 80000 )
                if ( PHP_VERSION_ID < 80000 ) curl_close( $ch ); // no effect since PHP 8.0, deprecated in 8.5
            return ( $result !== false && $status >= 200 && $status < 300 ) ? $result : false;
        }

        $opts = array(
            'http' => array(
                'method' => $method,
                'header' => $method === 'POST' ? "Content-type: application/x-www-form-urlencoded\r\n" : '',
                'content' => $postParams ? $this->buildQuery( $postParams ) : '',
                'follow_location' => 0,
                'timeout' => 15,
                'ignore_errors' => false,
            ),
            'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true ),
        );
        foreach ( $headers as $k => $v )
            $opts['http']['header'] .= $k . ': ' . $v . "\r\n";

        $context = stream_context_create( $opts );
        return @file_get_contents( $url, false, $context );
    }
}
