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

$http = eZHTTPTool::instance();
$helper = sevenxAuthentication2faHelper::instance();

$urlCode = '';
if ( isset( $Code ) && $Code !== '' )
    $urlCode = trim( $Code );
elseif ( $http->hasGetVariable( 'Code' ) )
    $urlCode = trim( $http->getVariable( 'Code' ) );

$pending = $helper->getPendingChallenge( $urlCode );

$error = '';
$status = 'verify';

if ( !$pending || time() > $pending['expires'] )
{
    sevenxAuthentication2faHelper::authLog( '2fa_verify_no_pending', 'pending missing or expired' );
    $helper->removePendingChallenge();
    eZHTTPTool::redirect( '/' );
    eZExecution::cleanExit();
}

$user = eZUser::fetch( $pending['user_id'] );
if ( !$user )
{
    sevenxAuthentication2faHelper::authLog( '2fa_verify_user_not_found', 'user_id=' . (int)$pending['user_id'] );
    $helper->removePendingChallenge();
    eZHTTPTool::redirect( '/' );
    eZExecution::cleanExit();
}

$userID = $user->attribute( 'contentobject_id' );
sevenxAuthentication2faHelper::authLog( '2fa_verify_view', 'method=' . $pending['method'], $userID );

$submittedCode = '';
if ( $http->hasPostVariable( 'VerifyButton' ) )
{
    $submittedCode = trim( $http->postVariable( 'Code' ) );
}
elseif ( $urlCode !== '' )
{
    $submittedCode = $urlCode;
}

if ( $submittedCode !== '' )
{
    $valid = false;

    if ( $pending['method'] === sevenxAuthentication2faHelper::METHOD_TOTP )
    {
        $valid = sevenxAuthentication2faTOTP::verify( $pending['code'], $submittedCode );
    }
    elseif ( $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL )
    {
        $valid = sevenxAuthentication2faEmail::verifyCode( $submittedCode );
    }

    if ( $valid )
    {
        $helper->removePendingChallenge();

        sevenxAuthentication2faHelper::authLog( '2fa_verify_succeeded', 'method=' . $pending['method'], $userID );
        eZAudit::writeAudit( 'user-login', array( 'User id' => $userID, 'User login' => $user->attribute( 'login' ) ) );
        eZUser::updateLastVisit( $userID, true );
        eZUser::setCurrentlyLoggedInUser( $user, $userID );
        eZUser::setFailedLoginAttempts( $userID, 0 );

        $redirect = $pending['redirect'] ? $pending['redirect'] : '/';
        $redirect = sevenxAuthentication2faHelper::normalizeRedirect( $redirect );
        eZSession::stop();
        eZHTTPTool::redirect( $redirect );
        eZExecution::cleanExit();
    }
    else
    {
        sevenxAuthentication2faHelper::authLog( '2fa_verify_failed', 'method=' . $pending['method'], $userID );
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'The code is incorrect or has expired.' );
        $status = 'failed';
    }
}

if ( $http->hasPostVariable( 'ResendButton' ) && $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL )
{
    sevenxAuthentication2faHelper::authLog( '2fa_email_resend', 'resending email code', $userID );
    sevenxAuthentication2faEmail::sendCode( $user, $pending['redirect'], true );
    $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'A new code has been sent to your e-mail address.' );
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'method', $pending['method'] );
$tpl->setVariable( 'redirect_uri', $pending['redirect'] ? $pending['redirect'] : '/' );
$tpl->setVariable( 'user', $user );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'status', $status );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:user2fa/verify.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-Factor Authentication' ),
                                'url' => false ) );
