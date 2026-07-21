#!/usr/bin/env php
<?php
/**
 * sevenx_authentication_2fa - interactive OAuth provider configuration tool.
 * Copyright (C) 1998 - 2026 7x. All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Usage:
 *   php extension/sevenx_authentication_2fa/bin/php/sevenx2faconfig.php \
 *       --provider=Google \
 *       --siteaccess=sevenx_site_user \
 *       --client-id=YOUR_CLIENT_ID \
 *       --client-secret=YOUR_SECRET
 *
 * The script reads/writes the matching sevenxauthentication2fa.ini.append.php
 * in settings/override (default) or settings/siteaccess/<name> and clears the
 * INI cache so the change is active on the next web request.
 *
 * @description Configure OAuth provider credentials in sevenxauthentication2fa.ini.
 */

// @description Configure OAuth provider credentials in sevenxauthentication2fa.ini.

require_once 'autoload.php';

$script = eZScript::instance(
    array(
        'description' => "sevenx_authentication_2fa OAuth provider configuration\n" .
                         "Updates sevenxauthentication2fa.ini with provider keys and endpoints.\n",
        'use-session' => false,
        'use-modules' => false,
        'use-extensions' => true
    )
);

$script->startup();

$options = $script->getOptions(
    "[provider:][siteaccess:][client-id:][client-secret:][enabled:][auto-create-user:][default-user-group-node-id:]" .
    "[authentication-match:][scope:][authorization-url:][token-url:][userinfo-url:][op:][eid:][nonce:][use-pkce:][no-prompt]",
    "",
    array(
        'provider'                  => 'Provider key: Google, Facebook, Meta, Instagram, TwitterX, IDme or a custom name',
        'siteaccess'                => 'Optional siteaccess name; writes to settings/siteaccess/<name>/sevenxauthentication2fa.ini.append.php',
        'client-id'                 => 'OAuth application client id / app id',
        'client-secret'             => 'OAuth application client secret / app secret (or set SEVENX_2FA_CLIENT_SECRET)',
        'enabled'                   => 'Enable the provider: enabled or disabled (default: enabled)',
        'auto-create-user'          => 'Auto-create local users for new social logins: enabled or disabled (default: disabled)',
        'default-user-group-node-id' => 'Parent node id for auto-created users (default: 12)',
        'authentication-match'      => 'Provider field used to match existing accounts: email or id (default: email)',
        'scope'                     => 'OAuth scopes, space separated',
        'authorization-url'         => 'OAuth authorization endpoint URL',
        'token-url'                 => 'OAuth token endpoint URL',
        'userinfo-url'              => 'Userinfo endpoint URL',
        'op'                        => 'ID.me op parameter: signin or signup (default: signin)',
        'eid'                       => 'ID.me external identifier (eid)',
        'nonce'                     => 'OIDC nonce for replay protection',
        'use-pkce'                  => 'Use PKCE: enabled or disabled (default: enabled for ID.me, disabled for others)',
        'no-prompt'                 => 'Do not prompt for missing values; fail instead',
    )
);

$script->initialize();
$cli = eZCLI::instance();

// ---------------------------------------------------------------------------
// Built-in provider defaults. Endpoints are read from the extension base INI
// when possible; these hard-coded fallbacks match the shipped settings file.
// ---------------------------------------------------------------------------
$builtInDefaults = array(
    'Google' => array(
        'scope'            => 'openid email profile',
        'AuthorizationURL' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'TokenURL'         => 'https://oauth2.googleapis.com/token',
        'UserInfoURL'      => 'https://openidconnect.googleapis.com/v1/userinfo',
    ),
    'Facebook' => array(
        'scope'            => 'email public_profile',
        'AuthorizationURL' => 'https://www.facebook.com/v18.0/dialog/oauth',
        'TokenURL'         => 'https://graph.facebook.com/v18.0/oauth/access_token',
        'UserInfoURL'      => 'https://graph.facebook.com/v18.0/me?fields=id,name,email',
    ),
    'Meta' => array(
        'scope'            => 'email public_profile',
        'AuthorizationURL' => 'https://www.facebook.com/v18.0/dialog/oauth',
        'TokenURL'         => 'https://graph.facebook.com/v18.0/oauth/access_token',
        'UserInfoURL'      => 'https://graph.facebook.com/v18.0/me?fields=id,name,email',
    ),
    'Instagram' => array(
        'scope'            => 'user_profile user_media',
        'AuthorizationURL' => 'https://api.instagram.com/oauth/authorize',
        'TokenURL'         => 'https://api.instagram.com/oauth/access_token',
        'UserInfoURL'      => 'https://graph.instagram.com/me?fields=id,username,email',
    ),
    'TwitterX' => array(
        'scope'            => 'users.read tweet.read',
        'AuthorizationURL' => 'https://twitter.com/i/oauth2/authorize',
        'TokenURL'         => 'https://api.twitter.com/2/oauth2/token',
        'UserInfoURL'      => 'https://api.twitter.com/2/users/me?user.fields=email',
    ),
    'Idme' => array(
        'scope'            => 'military',
        'AuthorizationURL' => 'https://api.id.me/oauth/authorize',
        'TokenURL'         => 'https://api.id.me/oauth/token',
        'UserInfoURL'      => 'https://api.id.me/api/public/v3/attributes.json',
        'Op'               => 'signin',
        'UsePKCE'          => 'enabled',
    ),
);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function prompt( $text, $default = '' )
{
    $cli = eZCLI::instance();
    $cli->output( $text . ( $default !== '' ? " [$default]" : '' ) . ': ', false );
    $line = fgets( STDIN );
    if ( $line === false )
        return $default;
    $line = trim( $line );
    return $line !== '' ? $line : $default;
}

function promptSecret( $text )
{
    $cli = eZCLI::instance();
    $cli->output( $text . ': ', false );
    if ( function_exists( 'posix_isatty' ) && posix_isatty( STDIN ) )
    {
        system( 'stty -echo' );
        $secret = fgets( STDIN );
        system( 'stty echo' );
        $cli->output( '' );
    }
    else
    {
        $secret = fgets( STDIN );
    }
    return $secret === false ? '' : trim( $secret );
}

function readIniFile( $file )
{
    if ( !file_exists( $file ) )
        return array();

    $content = file_get_contents( $file );
    if ( $content === false )
        return array();

    // Strip the PHP wrapper and #?ini header.
    if ( preg_match( '/\/\*(.*?)\*\//s', $content, $matches ) )
    {
        $iniPart = $matches[1];
    }
    else
    {
        $iniPart = $content;
    }

    $iniPart = preg_replace( '/^\s*#\?ini[^\n]*\n/', '', $iniPart );
    $data = @parse_ini_string( $iniPart, true, INI_SCANNER_RAW );
    return is_array( $data ) ? $data : array();
}

function writeIniFile( $file, $data )
{
    $lines = array( '<?php /* #?ini charset="utf-8"?' );

    foreach ( $data as $section => $values )
    {
        if ( !is_array( $values ) )
            continue;

        $lines[] = '';
        $lines[] = '[' . $section . ']';
        foreach ( $values as $key => $value )
        {
            if ( is_array( $value ) )
            {
                // Support both associative and indexed arrays.
                foreach ( $value as $subKey => $subValue )
                {
                    if ( is_int( $subKey ) )
                    {
                        $lines[] = $key . '[]=' . $subValue;
                    }
                    else
                    {
                        $lines[] = $key . '[' . $subKey . ']=' . $subValue;
                    }
                }
            }
            else
            {
                $lines[] = $key . '=' . $value;
            }
        }
    }

    $lines[] = '';
    $lines[] = '*/ ?>';
    $lines[] = '';

    $dir = dirname( $file );
    if ( !file_exists( $dir ) )
    {
        eZDir::mkdir( $dir, false, true );
    }

    file_put_contents( $file, implode( "\n", $lines ) );
}

function iniValue( $data, $section, $key, $default = '' )
{
    if ( isset( $data[$section][$key] ) )
    {
        $value = $data[$section][$key];
        if ( is_array( $value ) )
            $value = reset( $value );
        return $value;
    }
    return $default;
}

// ---------------------------------------------------------------------------
// Validate / sanitize provider
// ---------------------------------------------------------------------------
$provider = trim( (string)$options['provider'] );
if ( !$provider )
{
    if ( $options['no-prompt'] )
    {
        $cli->error( 'The --provider argument is required.' );
        $script->shutdown( 1 );
    }
    $provider = prompt( 'Provider (Google, Facebook, Meta, Instagram, TwitterX or custom)' );
}
if ( !$provider )
{
    $cli->error( 'No provider specified.' );
    $script->shutdown( 1 );
}

$providerUc = ucfirst( strtolower( $provider ) );

// ---------------------------------------------------------------------------
// Determine target file
// ---------------------------------------------------------------------------
$extensionDir = __DIR__ . '/../..';
if ( $options['siteaccess'] )
{
    $siteaccess = preg_replace( '/[^a-zA-Z0-9_-]/', '', $options['siteaccess'] );
    $iniFile = "settings/siteaccess/$siteaccess/sevenxauthentication2fa.ini.append.php";
}
else
{
    $iniFile = 'settings/override/sevenxauthentication2fa.ini.append.php';
}
$iniFile = realpath( '.' ) . '/' . $iniFile;

// ---------------------------------------------------------------------------
// Load existing INI
// ---------------------------------------------------------------------------
$existing = readIniFile( $iniFile );
$providerBlock = isset( $existing[$providerUc] ) ? $existing[$providerUc] : array();

// ---------------------------------------------------------------------------
// Determine client id
// ---------------------------------------------------------------------------
$clientId = trim( (string)$options['client-id'] );
if ( !$clientId )
{
    if ( $options['no-prompt'] )
    {
        $cli->error( 'The --client-id argument is required.' );
        $script->shutdown( 1 );
    }
    $clientId = prompt( 'Client ID / App ID', iniValue( $existing, $providerUc, 'ClientID' ) );
}

$clientSecret = trim( (string)$options['client-secret'] );
if ( !$clientSecret && getenv( 'SEVENX_2FA_CLIENT_SECRET' ) )
{
    $clientSecret = getenv( 'SEVENX_2FA_CLIENT_SECRET' );
}
if ( !$clientSecret )
{
    if ( $options['no-prompt'] )
    {
        $cli->error( 'The --client-secret argument (or SEVENX_2FA_CLIENT_SECRET environment variable) is required.' );
        $script->shutdown( 1 );
    }
    $clientSecret = promptSecret( 'Client Secret / App Secret' );
}

// ---------------------------------------------------------------------------
// Ask for / derive remaining values
// ---------------------------------------------------------------------------
$enabled = strtolower( trim( (string)$options['enabled'] ) );
if ( !$enabled )
{
    $enabled = $options['no-prompt'] ? 'enabled' : strtolower( prompt( 'Enable this provider?', 'enabled' ) );
}
$enabled = ( $enabled === 'enabled' || $enabled === '1' || $enabled === 'true' || $enabled === 'yes' ) ? 'enabled' : 'disabled';

$autoCreate = strtolower( trim( (string)$options['auto-create-user'] ) );
if ( !$autoCreate )
{
    $autoCreate = $options['no-prompt'] ? 'disabled' : strtolower( prompt( 'Auto-create local users for new social logins?', 'disabled' ) );
}
$autoCreate = ( $autoCreate === 'enabled' || $autoCreate === '1' || $autoCreate === 'true' || $autoCreate === 'yes' ) ? 'enabled' : 'disabled';

$defaultGroup = trim( (string)$options['default-user-group-node-id'] );
if ( !$defaultGroup )
{
    $defaultGroup = $options['no-prompt'] ? '12' : prompt( 'Default user group node ID for auto-created users', '12' );
}
$defaultGroup = (int)$defaultGroup;
if ( !$defaultGroup )
    $defaultGroup = 12;

$authMatch = strtolower( trim( (string)$options['authentication-match'] ) );
if ( !$authMatch )
{
    $authMatch = $options['no-prompt'] ? 'email' : strtolower( prompt( 'Authentication match field (email or id)', 'email' ) );
}
$authMatch = ( $authMatch === 'id' ) ? 'id' : 'email';

$defaults = isset( $builtInDefaults[$providerUc] ) ? $builtInDefaults[$providerUc] : array();

$defaultScope   = iniValue( $existing, $providerUc, 'Scope',            isset( $defaults['scope'] )            ? $defaults['scope']            : 'email profile' );
$defaultAuthUrl = iniValue( $existing, $providerUc, 'AuthorizationURL', isset( $defaults['AuthorizationURL'] ) ? $defaults['AuthorizationURL'] : '' );
$defaultTokenUrl= iniValue( $existing, $providerUc, 'TokenURL',         isset( $defaults['TokenURL'] )         ? $defaults['TokenURL']         : '' );
$defaultInfoUrl = iniValue( $existing, $providerUc, 'UserInfoURL',      isset( $defaults['UserInfoURL'] )      ? $defaults['UserInfoURL']      : '' );
$defaultOp      = iniValue( $existing, $providerUc, 'Op',               isset( $defaults['Op'] )               ? $defaults['Op']               : 'signin' );
$defaultUsePKCE = iniValue( $existing, $providerUc, 'UsePKCE',          isset( $defaults['UsePKCE'] )          ? $defaults['UsePKCE']          : 'disabled' );
$defaultEid     = iniValue( $existing, $providerUc, 'EID',              '' );
$defaultNonce   = iniValue( $existing, $providerUc, 'Nonce',            '' );

$scope = trim( (string)$options['scope'] );
if ( !$scope )
{
    $scope = $options['no-prompt'] ? $defaultScope : prompt( 'OAuth scope', $defaultScope );
}

$authUrl = trim( (string)$options['authorization-url'] );
if ( !$authUrl )
{
    $authUrl = $options['no-prompt'] ? $defaultAuthUrl : prompt( 'Authorization URL', $defaultAuthUrl );
}

$tokenUrl = trim( (string)$options['token-url'] );
if ( !$tokenUrl )
{
    $tokenUrl = $options['no-prompt'] ? $defaultTokenUrl : prompt( 'Token URL', $defaultTokenUrl );
}

$userinfoUrl = trim( (string)$options['userinfo-url'] );
if ( !$userinfoUrl )
{
    $userinfoUrl = $options['no-prompt'] ? $defaultInfoUrl : prompt( 'Userinfo URL', $defaultInfoUrl );
}

$op = strtolower( trim( (string)$options['op'] ) );
if ( !$op )
{
    $op = $options['no-prompt'] ? $defaultOp : strtolower( prompt( 'ID.me op parameter (signin/signup)', $defaultOp ) );
}
$op = ( $op === 'signup' ) ? 'signup' : 'signin';

$eid = trim( (string)$options['eid'] );
if ( !$eid && !$options['no-prompt'] )
{
    $eid = prompt( 'ID.me external identifier (eid) - leave empty if not needed', $defaultEid );
}
if ( !$eid )
    $eid = $defaultEid;

$nonce = trim( (string)$options['nonce'] );
if ( !$nonce && !$options['no-prompt'] )
{
    $nonce = prompt( 'OIDC nonce - leave empty if not needed', $defaultNonce );
}
if ( !$nonce )
    $nonce = $defaultNonce;

$usePKCE = strtolower( trim( (string)$options['use-pkce'] ) );
if ( !$usePKCE )
{
    $usePKCE = $options['no-prompt'] ? $defaultUsePKCE : strtolower( prompt( 'Use PKCE (enabled/disabled)', $defaultUsePKCE ) );
}
$usePKCE = ( $usePKCE === 'enabled' || $usePKCE === '1' || $usePKCE === 'true' || $usePKCE === 'yes' ) ? 'enabled' : 'disabled';

// ---------------------------------------------------------------------------
// Update INI array
// ---------------------------------------------------------------------------
if ( !isset( $existing['SocialLogin'] ) )
{
    $existing['SocialLogin'] = array();
}
$existing['SocialLogin']['Enabled'] = 'enabled';
$existing['SocialLogin']['AutoCreateUser'] = $autoCreate;
$existing['SocialLogin']['DefaultUserGroupNodeID'] = (string)$defaultGroup;
$existing['SocialLogin']['AuthenticationMatch'] = $authMatch;

$existing[$providerUc] = array(
    'Enabled'          => $enabled,
    'ClientID'         => $clientId,
    'ClientSecret'     => $clientSecret,
    'Scope'            => $scope,
    'AuthorizationURL' => $authUrl,
    'TokenURL'         => $tokenUrl,
    'UserInfoURL'      => $userinfoUrl,
    'Op'               => $op,
    'EID'              => $eid,
    'Nonce'            => $nonce,
    'UsePKCE'          => $usePKCE,
);

// ---------------------------------------------------------------------------
// Write file and clear cache
// ---------------------------------------------------------------------------
writeIniFile( $iniFile, $existing );

if ( file_exists( $iniFile ) )
{
    $cli->output( "Wrote $providerUc configuration to: $iniFile" );
}
else
{
    $cli->error( "Failed to write $iniFile" );
    $script->shutdown( 1 );
}

// Make sure eZINI reloads the file on the next web request.
eZINI::resetGlobals( 'sevenxauthentication2fa.ini' );
eZCache::clearByID( array( 'global_ini', 'ini' ) );

// Build the callback URI for the operator's convenience.
if ( isset( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST'] )
{
    $scheme = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $callback = "$scheme://$host/user2fa/callback/" . strtolower( $providerUc );
}
else
{
    $callback = 'https://<your-domain>/user2fa/callback/' . strtolower( $providerUc );
}

$cli->output( '' );
$cli->output( "Provider: $providerUc" );
$cli->output( "Status: $enabled" );
$cli->output( "Callback URI (register this in the provider console): $callback" );
$cli->output( '' );
$cli->output( 'INI cache cleared. The provider is active on the next request.' );

$script->shutdown( 0 );
