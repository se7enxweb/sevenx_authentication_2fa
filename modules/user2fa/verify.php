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

/* The second step of signing in (user2fa/verify).

   Only the session that typed the right password has a pending challenge
   (sevenxAuthentication2faHelper::pending()). A right code signs the user in;
   a wrong one counts, both in the challenge (MaxAttempts, then the password
   again) and as a failed login of the account. A TOTP code is accepted once:
   the time step it signed in with is stored and older steps are refused.

   Template variables: method, redirect_uri, user, error, status ('verify',
   'failed', 'locked', 'expired', 'elsewhere'), and attempts_left,
   max_attempts, masked_email, resend_wait, can_resend, digits, expires_in,
   login_url, notice. */

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$helper = sevenxAuthentication2faHelper::instance();
$limits = $helper->limits();
$settings = $helper->totpSettings();
sevenxAuthentication2faHelper::noStore();

$urlCode = '';
if ( isset( $Params['Code'] ) && is_string( $Params['Code'] ) )
    $urlCode = trim( $Params['Code'] );
elseif ( $http->hasGetVariable( 'Code' ) && is_string( $http->getVariable( 'Code' ) ) )
    $urlCode = trim( $http->getVariable( 'Code' ) );

$status = 'verify';
$error = '';
$notice = '';
$user = null;
$pending = $helper->pending();

if ( $pending )
{
    $user = eZUser::fetch( (int)$pending['user_id'] );
    if ( !$user instanceof eZUser || !$user->isEnabled() )
    {
        sevenxAuthentication2faHelper::authLog( '2fa_verify_user_not_found', 'user_id=' . (int)$pending['user_id'] );
        $helper->removePendingChallenge();
        $pending = null;
        $user = null;
    }
}

if ( !$pending )
{
    // No second step in this browser: it ended, was used up, or the e-mail link was opened elsewhere

    $hadOne = $http->hasSessionVariable( sevenxAuthentication2faHelper::SESSION_PENDING );
    $helper->removePendingChallenge();
    $status = $urlCode !== '' && !$hadOne ? 'elsewhere' : 'expired';
    sevenxAuthentication2faHelper::authLog( '2fa_verify_no_pending', 'status=' . $status );
}
else
{
    $userID = (int)$user->attribute( 'contentobject_id' );

    $submittedCode = '';
    if ( $http->hasPostVariable( 'VerifyButton' ) && is_string( $http->postVariable( 'Code' ) ) )
        $submittedCode = trim( $http->postVariable( 'Code' ) );
    elseif ( $urlCode !== '' && $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL )
        $submittedCode = $urlCode;

    $maxFailed = eZUser::maxNumberOfFailedLogin();
    $accountLocked = $maxFailed && eZUser::failedLoginAttemptsByUserID( $userID ) > $maxFailed;

    if ( $accountLocked )
    {
        $helper->removePendingChallenge();
        $status = 'locked';
        sevenxAuthentication2faHelper::authLog( '2fa_verify_account_locked', '', $userID );
    }
    elseif ( $http->hasPostVariable( 'ResendButton' ) && $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL )
    {
        $wait = sevenxAuthentication2faAttempts::resendWait( $pending, $limits['resend_interval'], $limits['max_resends'], time() );
        if ( $wait === 0 )
        {
            sevenxAuthentication2faEmail::sendCode( $user, $pending['redirect'], true );
            $pending = $helper->pending();
            $notice = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'A new code is on its way. Codes sent before it no longer work.' );
        }
        elseif ( $wait > 0 )
        {
            $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Please wait %seconds seconds before asking for another code.', null, array( '%seconds' => $wait ) );
        }
        else
        {
            $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'No more new codes can be sent for this sign-in. Use the last code you received, or sign in again.' );
        }
    }
    elseif ( $submittedCode !== '' )
    {
        $valid = false;
        $data = null;
        $step = false;

        if ( $pending['method'] === sevenxAuthentication2faHelper::METHOD_TOTP )
        {
            $data = $helper->userData( $userID );
            if ( $data && $data->isActive() && $data->method() === sevenxAuthentication2faHelper::METHOD_TOTP )
            {
                $step = $helper->matchTotp( $data->secret(), $submittedCode, $data->lastStep() );
                $valid = $step !== false;
                if ( !$valid && $helper->matchTotp( $data->secret(), $submittedCode ) !== false )
                    sevenxAuthentication2faHelper::authLog( '2fa_verify_replay', 'a code that was already used', $userID );
            }
        }
        elseif ( $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL )
        {
            $valid = sevenxAuthentication2faAttempts::emailCodeMatches( $pending, $submittedCode );
        }

        if ( $valid )
        {
            if ( $data && $step !== false )
            {
                $data->setLastStep( $step );
                $helper->saveUserData( $userID, $data );
            }
            $redirect = sevenxAuthentication2faHelper::normalizeRedirect( $pending['redirect'] );
            $helper->removePendingChallenge();
            sevenxAuthentication2faHelper::authLog( '2fa_verify_succeeded', 'method=' . $pending['method'], $userID );
            eZsevenxUser2faUser::completeLogin( $user );
            return $Module->redirectTo( $redirect );
        }

        sevenxAuthentication2faHelper::authLog( '2fa_verify_failed', 'method=' . $pending['method'], $userID );
        eZsevenxUser2faUser::countFailedCode( $userID );
        $pending = sevenxAuthentication2faAttempts::fail( $pending );
        if ( sevenxAuthentication2faAttempts::remaining( $pending, $limits['max_attempts'] ) <= 0 )
        {
            $helper->removePendingChallenge();
            $status = 'locked';
            sevenxAuthentication2faHelper::authLog( '2fa_verify_too_many_attempts', '', $userID );
        }
        else
        {
            $helper->updatePending( $pending );
            $status = 'failed';
            $error = $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL
                ? ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'That code is not right, or it has expired. Check the newest e-mail and try again.' )
                : ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'That code is not right. Codes change every %seconds seconds, and each one works once: enter the code your app shows now.', null, array( '%seconds' => $settings['period'] ) );
        }
    }
    else
    {
        sevenxAuthentication2faHelper::authLog( '2fa_verify_view', 'method=' . $pending['method'], $userID );
    }
}

$loginURL = 'user/login';
$tpl = eZTemplate::factory();
$tpl->setVariable( 'method', $pending ? $pending['method'] : '' );
$tpl->setVariable( 'redirect_uri', $pending ? $pending['redirect'] : '/' );
$tpl->setVariable( 'user', $status === 'verify' || $status === 'failed' ? $user : null );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'notice', $notice );
$tpl->setVariable( 'status', $status );
$tpl->setVariable( 'login_url', $loginURL );
$tpl->setVariable( 'digits', $settings['digits'] );
$tpl->setVariable( 'max_attempts', $limits['max_attempts'] );
$tpl->setVariable( 'attempts_left', $pending ? sevenxAuthentication2faAttempts::remaining( $pending, $limits['max_attempts'] ) : 0 );
$tpl->setVariable( 'expires_in', $pending ? max( 0, (int)$pending['expires'] - time() ) : 0 );
$tpl->setVariable( 'masked_email', $user instanceof eZUser ? sevenxAuthentication2faHelper::maskEmail( $user->attribute( 'email' ) ) : '' );
$resendWait = $pending && $pending['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL
    ? sevenxAuthentication2faAttempts::resendWait( $pending, $limits['resend_interval'], $limits['max_resends'], time() ) : -1;
$tpl->setVariable( 'resend_wait', $resendWait );
$tpl->setVariable( 'can_resend', $resendWait >= 0 );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:user2fa/verify.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-Factor Authentication' ),
                                'url' => false ) );
$pageLayout = sevenxAuthentication2faHelper::pageLayout();
if ( $pageLayout )
    $Result['pagelayout'] = $pageLayout;
