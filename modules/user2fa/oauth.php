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

$provider = isset( $Params['provider'] ) ? strtolower( $Params['provider'] ) : '';

if ( !$provider )
{
    sevenxAuthentication2faHelper::authLog( 'oauth_missing_provider', 'no provider given' );
    eZHTTPTool::redirect( '/user/login' );
    eZExecution::cleanExit();
}

sevenxAuthentication2faHelper::authLog( 'oauth_attempt', 'provider=' . $provider );

$className = 'eZ' . ucfirst( $provider ) . 'User';
if ( !class_exists( $className ) || !is_subclass_of( $className, 'eZOAuthUser' ) )
{
    sevenxAuthentication2faHelper::authLog( 'oauth_invalid_provider', 'class=' . $className );
    eZHTTPTool::redirect( '/user/login' );
    eZExecution::cleanExit();
}

$handler = new $className();
if ( !$handler->isConfigured() )
{
    sevenxAuthentication2faHelper::authLog( 'oauth_not_configured', 'provider=' . $provider );
    eZHTTPTool::redirect( '/user/login' );
    eZExecution::cleanExit();
}
$handler->preCollectUserInfo();
