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

/* The start of a social login (user2fa/oauth/<provider>): sends the browser to
   the provider with a fresh state (and PKCE where configured). An optional
   RedirectURI (GET) says where to go after signing in; it is checked by the
   safe redirect rules and kept in the session. A provider that is unknown or
   not configured gets the callback page's explanation instead. */

$Module = $Params['Module'];
$provider = eZOAuthUser::providerKey( isset( $Params['provider'] ) ? $Params['provider'] : '' );
$handler = $provider !== '' ? eZOAuthUser::handlerFor( $provider ) : null;
sevenxAuthentication2faHelper::noStore();

if ( !$handler || !$handler->isConfigured() )
{
    sevenxAuthentication2faHelper::authLog( $handler ? 'oauth_not_configured' : 'oauth_invalid_provider', 'provider=' . $provider );
    $tpl = eZTemplate::factory();
    $tpl->setVariable( 'status', $handler ? 'not_configured' : 'unknown_provider' );
    $tpl->setVariable( 'provider', $provider );
    $tpl->setVariable( 'provider_name', $handler ? $handler->displayName() : '' );
    $Result = array();
    $Result['content'] = $tpl->fetch( 'design:user2fa/callback.tpl' );
    $Result['path'] = array( array( 'text' => ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Social login' ), 'url' => false ) );
    $pageLayout = sevenxAuthentication2faHelper::pageLayout();
    if ( $pageLayout )
        $Result['pagelayout'] = $pageLayout;
    return $Result;
}

$http = eZHTTPTool::instance();
$redirect = $http->hasGetVariable( 'RedirectURI' ) ? $http->getVariable( 'RedirectURI' ) : '';
$handler->setReturnTarget( is_string( $redirect ) ? $redirect : '' );

sevenxAuthentication2faHelper::authLog( 'oauth_attempt', 'provider=' . $provider );
$handler->preCollectUserInfo();
