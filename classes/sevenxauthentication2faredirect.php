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
  \class sevenxAuthentication2faRedirect sevenxauthentication2faredirect.php
  \ingroup sevenx_authentication_2fa
  \brief Decides where a visitor may be sent after the second step or a social login.

  Every redirect target that comes from outside the code (the login form's
  RedirectURI, the session's RedirectAfterLogin or LastAccessesURI, the social
  login's RedirectURI) passes through safe(). It answers a path of this site
  or '/' -- never another host, so the second step cannot be turned into an
  open redirect.

  On Exponential 6.0.15 and later the rules are the kernel's
  (eZRedirectManager::unsafeReason(), doc/features/6.0/safe-redirects.md);
  then an absolute URL of one of the site's own hosts is reduced to its path.
  Elsewhere, and in the unit tests, the same rules are applied here, for paths
  only.
*/
class sevenxAuthentication2faRedirect
{
    /**
     * Use the kernel's rules when they exist (false in the unit tests).
     * @var bool
     */
    public static $useKernelRules = true;

    /**
     * The views a visitor is never sent back to: the steps of signing in and
     * out themselves, and views that act on a POST.
     * @var array
     */
    private static $refusedViews = array(
        'user/login', 'user/logout', 'user/register', 'user2fa/verify', 'user2fa/setup',
        'user2fa/oauth', 'user2fa/callback', 'content/action', 'content/download',
    );

    /**
     * A safe, site-relative target for the redirect, or $default.
     * @param mixed $uri
     * @param string $default
     * @return string
     */
    public static function safe( $uri, $default = '/' )
    {
        $reason = self::unsafeReason( $uri );
        if ( $reason !== false )
            return $default;

        $uri = trim( (string)$uri, ' ' );
        // An absolute URL that passed is one of the site's own hosts: keep its path, query and fragment only
        if ( preg_match( '#^https?://#i', $uri ) )
        {
            $parts = parse_url( $uri );
            $path = isset( $parts['path'] ) && $parts['path'] !== '' ? $parts['path'] : '/';
            $uri = $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
            if ( self::unsafeReason( $uri ) !== false )
                return $default;
        }

        $uri = '/' . ltrim( $uri, '/' );
        return self::isRefusedView( $uri ) ? $default : $uri;
    }

    /**
     * Why a target is not safe, or false when it is.
     * @param mixed $uri
     * @return string|false 'type', 'empty', 'control', 'backslash', 'encoded', 'scheme', 'userinfo' or 'host'
     */
    public static function unsafeReason( $uri )
    {
        if ( self::$useKernelRules && class_exists( 'eZRedirectManager' ) && method_exists( 'eZRedirectManager', 'unsafeReason' ) )
            return eZRedirectManager::unsafeReason( $uri );
        return self::localReason( $uri );
    }

    /**
     * The rules for a path, without the kernel: no control character, no
     * backslash, nothing that decodes to a protocol-relative URL or a scheme,
     * and no absolute URL at all.
     * @param mixed $uri
     * @return string|false
     */
    public static function localReason( $uri )
    {
        if ( !is_string( $uri ) )
            return 'type';
        $uri = trim( $uri, ' ' );
        if ( $uri === '' )
            return 'empty';
        if ( preg_match( '/[\x00-\x1F\x7F]/', $uri ) )
            return 'control';
        $beforeQuery = strtok( $uri, '?#' );
        if ( $beforeQuery !== false && strpos( $beforeQuery, '\\' ) !== false )
            return 'backslash';

        $decoded = $uri;
        for ( $i = 0; $i < 3; $i++ )
        {
            $next = rawurldecode( $decoded );
            if ( $next === $decoded )
                break;
            $decoded = $next;
            $decodedPath = (string)strtok( $decoded, '?#' );
            if ( preg_match( '#^(//|\\\\|[a-z][a-z0-9+.\-]*:)#i', ltrim( $decoded, ' ' ) ) || preg_match( '/[\x00-\x1F\x7F]/', $decoded )
                 || strpos( $decodedPath, '\\' ) !== false )
                return 'encoded';
        }

        if ( strncmp( $uri, '//', 2 ) === 0 )
            return 'host';
        if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $uri ) )
            return 'scheme';
        return false;
    }

    /**
     * Is the target one of the views a visitor is never sent back to?
     * @param string $uri a path
     * @return bool
     */
    public static function isRefusedView( $uri )
    {
        $path = strtolower( trim( (string)strtok( $uri, '?#' ), '/' ) );
        foreach ( self::$refusedViews as $view )
        {
            // The view itself, or the view after a siteaccess prefix (/admin/user/logout)
            if ( $path === $view || strpos( $path, $view . '/' ) === 0
                 || substr( $path, -strlen( $view ) - 1 ) === '/' . $view || strpos( $path, '/' . $view . '/' ) !== false )
                return true;
        }
        return false;
    }
}
