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

/* Setting up, changing and removing the second step (user2fa/setup).

   For a signed in user, and for a user who passed the password while
   Enforce2FA asks for a method first (the pending setup of this session,
   which ends after SetupTTL seconds; finishing it signs the user in).

   - The authenticator secret of an enrolment is made on the server and kept
     in the session until a code from the app confirms it; it is never taken
     from the form, and once confirmed it is never shown again.
   - Moving away from a confirmed authenticator (to e-mail codes, off, or a
     reset) needs a current code from it, so a session left open is not
     enough to weaken the account.

   POST: SetupButton with Method (totp, email, disabled) and Code; ResetButton
   with Code. Template variables: data, secret, provisioning_uri, qr_code,
   error, success, is_enforced, and is_pending_setup, needs_confirmation,
   secret_grouped, issuer, account_name, can_store, has_email, masked_email,
   allowed_methods, selected_method, digits, period, setup_user. */

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$helper = sevenxAuthentication2faHelper::instance();
$settings = $helper->totpSettings();
sevenxAuthentication2faHelper::noStore();

$currentUser = eZUser::currentUser();
$setupUser = null;
$isPendingSetup = false;
$pendingSetup = null;

if ( $currentUser->isRegistered() )
{
    // The view is in [RoleSettings] PolicyOmitList (a first setup happens before signing in), so the policy
    // user2fa/setup of a signed in user is checked here
    $access = $currentUser->hasAccessTo( 'user2fa', 'setup' );
    if ( !is_array( $access ) || $access['accessWord'] === 'no' )
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    $setupUser = $currentUser;
}
else
{
    $pendingSetup = $helper->pendingSetup();
    if ( $pendingSetup )
    {
        $candidate = eZUser::fetch( (int)$pendingSetup['user_id'] );
        if ( $candidate instanceof eZUser && $candidate->isEnabled() )
        {
            $setupUser = $candidate;
            $isPendingSetup = true;
        }
        else
        {
            $helper->removeSetup();
        }
    }

    if ( !$setupUser )
    {
        sevenxAuthentication2faHelper::authLog( '2fa_setup_unauthenticated', 'setup page requested without a sign-in' );
        return $Module->redirectTo( '/user/login' );
    }
}

$userID = (int)$setupUser->attribute( 'contentobject_id' );
$data = $helper->userData( $userID );
if ( !$data )
    $data = new sevenxAuthentication2fa();
$canStore = $helper->canStore( $userID );
$isEnforced = $helper->isEnforced();
$hasEmail = (bool)$setupUser->attribute( 'email' );

$error = '';
$success = '';
$activeTotp = $data->isActive() && $data->method() === sevenxAuthentication2faHelper::METHOD_TOTP;
$selectedMethod = $data->isActive() ? $data->method() : ( $isEnforced ? $helper->defaultMethod() : 'totp' );
if ( $selectedMethod === sevenxAuthentication2faHelper::METHOD_DISABLED )
    $selectedMethod = 'totp';

$postedString = function ( $name ) use ( $http ) {
    if ( !$http->hasPostVariable( $name ) )
        return '';
    $value = $http->postVariable( $name );
    return is_string( $value ) ? trim( $value ) : '';
};

// A wrong confirmation code counts like a wrong code at sign-in
$confirmFailName = 'Sevenx2FA_ConfirmFails_' . $userID;
$confirm = function ( $code ) use ( $helper, $http, $data, $userID, $confirmFailName ) {
    $limits = $helper->limits();
    $fails = $http->hasSessionVariable( $confirmFailName ) ? (int)$http->sessionVariable( $confirmFailName ) : 0;
    if ( $fails >= $limits['max_attempts'] )
        return 'locked';
    if ( $code === '' )
        return 'missing';
    $step = $helper->matchTotp( $data->secret(), $code, $data->lastStep() );
    if ( $step === false )
    {
        $http->setSessionVariable( $confirmFailName, $fails + 1 );
        eZsevenxUser2faUser::countFailedCode( $userID );
        sevenxAuthentication2faHelper::authLog( '2fa_setup_confirm_failed', '', $userID );
        return 'wrong';
    }
    $http->removeSessionVariable( $confirmFailName );
    return true;
};
$confirmError = function ( $result ) {
    if ( $result === 'locked' )
        return ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Too many wrong codes. Sign out and sign in again before you change the second step.' );
    if ( $result === 'missing' )
        return ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Enter the code your authenticator app shows now to confirm this change.' );
    return ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'That code is not right. Enter the code your authenticator app shows now; each code works once.' );
};

$changed = false;

if ( $http->hasPostVariable( 'SetupButton' ) )
{
    $method = $postedString( 'Method' );
    if ( !in_array( $method, array( 'totp', 'email', 'disabled' ), true ) )
        $method = 'disabled';
    $selectedMethod = $method;
    $code = $postedString( 'Code' );

    if ( !$canStore )
    {
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'This account cannot keep a second step: its user class has no two-factor field. Ask the site administrator.' );
    }
    elseif ( $method === 'disabled' && $isEnforced )
    {
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication is required. You must choose an authentication method.' );
    }
    elseif ( $method === 'totp' )
    {
        if ( $activeTotp )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_kept', 'totp already confirmed', $userID );
            $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'TOTP remains configured.' );
        }
        else
        {
            $secret = $helper->enrolmentSecret( $userID );
            $postedSecret = strtoupper( str_replace( ' ', '', $postedString( 'Secret' ) ) );
            if ( $postedSecret !== '' && !hash_equals( $secret, $postedSecret ) )
            {
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'This page was out of date, so the code below is a new one. Scan it again, then enter the code your app shows.' );
            }
            elseif ( $code === '' )
            {
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Enter the 6-digit code your authenticator app shows, so we know it is set up right.' );
            }
            else
            {
                $step = $helper->matchTotp( $secret, $code );
                if ( $step !== false )
                {
                    $data = new sevenxAuthentication2fa( 'totp', $secret, true, null, $step );
                    $changed = $helper->saveUserData( $userID, $data );
                    $helper->removeEnrolmentSecret( $userID );
                    sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_ok', 'totp configured and verified', $userID );
                    $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'TOTP configured successfully.' );
                }
                else
                {
                    sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_failed', 'totp verification code incorrect', $userID );
                    $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'The verification code is incorrect.' );
                }
            }
        }
    }
    elseif ( $method === 'email' )
    {
        $result = $activeTotp ? $confirm( $code ) : true;
        if ( !$hasEmail )
            $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'This account has no e-mail address, so codes cannot be sent by e-mail.' );
        elseif ( $result !== true )
            $error = $confirmError( $result );
        elseif ( $data->isActive() && $data->method() === 'email' )
            $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'E-mail codes remain configured.' );
        else
        {
            $data = new sevenxAuthentication2fa( 'email', '', true );
            $changed = $helper->saveUserData( $userID, $data );
            $helper->removeEnrolmentSecret( $userID );
            sevenxAuthentication2faHelper::authLog( '2fa_setup_email', 'email 2fa enabled', $userID );
            $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'E-mail authentication configured successfully.' );
        }
    }
    else
    {
        $result = $activeTotp ? $confirm( $code ) : true;
        if ( $result !== true )
            $error = $confirmError( $result );
        else
        {
            $data = new sevenxAuthentication2fa();
            $changed = $helper->saveUserData( $userID, $data );
            sevenxAuthentication2faHelper::authLog( '2fa_setup_disabled', '2fa disabled', $userID );
            $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication disabled.' );
        }
    }
}
elseif ( $http->hasPostVariable( 'ResetButton' ) )
{
    $result = $activeTotp ? $confirm( $postedString( 'Code' ) ) : true;
    if ( $isEnforced )
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication is required. Resetting is not allowed while enforcement is enabled.' );
    elseif ( !$canStore )
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'This account cannot keep a second step: its user class has no two-factor field. Ask the site administrator.' );
    elseif ( $result !== true )
        $error = $confirmError( $result );
    else
    {
        $data = new sevenxAuthentication2fa();
        $changed = $helper->saveUserData( $userID, $data );
        $helper->removeEnrolmentSecret( $userID );
        sevenxAuthentication2faHelper::authLog( '2fa_setup_reset', '2fa configuration reset by user', $userID );
        $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication configuration has been reset.' );
    }
}
else
{
    sevenxAuthentication2faHelper::authLog( '2fa_setup_view', 'setup page viewed', $userID );
}

// After a change that was stored, the form shows the state as it is now
if ( $changed && $error === '' )
    $selectedMethod = $data->isActive() ? $data->method() : ( $isEnforced ? $helper->defaultMethod() : 'disabled' );

// A first setup asked for by Enforce2FA ends with signing in, once a working second step is stored
if ( $isPendingSetup && $changed && $data->isActive() )
{
    $redirect = sevenxAuthentication2faHelper::normalizeRedirect( $pendingSetup['redirect'] );
    $helper->removeSetup();
    sevenxAuthentication2faHelper::authLog( '2fa_setup_completed_login', '', $userID );
    eZsevenxUser2faUser::completeLogin( $setupUser );
    return $Module->redirectTo( $redirect );
}

$activeTotp = $data->isActive() && $data->method() === sevenxAuthentication2faHelper::METHOD_TOTP;
$secret = '';
$provisioningUri = '';
$qrCode = '';
$issuer = $helper->issuer();
$account = $setupUser->attribute( 'email' ) ? $setupUser->attribute( 'email' ) : $setupUser->attribute( 'login' );
if ( !$activeTotp && $canStore )
{
    // The QR code is drawn here, on the server, into a data: URI: the secret never goes to another service
    $secret = $helper->enrolmentSecret( $userID );
    $provisioningUri = sevenxAuthentication2faTOTP::provisioningUri( $account, $secret, $issuer, $settings['digits'], $settings['period'], $settings['algorithm'] );
    $qrCode = sevenxAuthentication2faQR::pngDataUri( $provisioningUri );
}

$allowedMethods = array( 'totp' );
if ( $hasEmail )
    $allowedMethods[] = 'email';
if ( !$isEnforced )
    $allowedMethods[] = 'disabled';

$tpl = eZTemplate::factory();
$tpl->setVariable( 'data', $data );
$tpl->setVariable( 'secret', $secret );
$tpl->setVariable( 'secret_grouped', sevenxAuthentication2faTOTP::groupSecret( $secret ) );
$tpl->setVariable( 'provisioning_uri', $provisioningUri );
$tpl->setVariable( 'qr_code', $qrCode );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'success', $success );
$tpl->setVariable( 'is_enforced', $isEnforced );
$tpl->setVariable( 'is_pending_setup', $isPendingSetup );
$tpl->setVariable( 'needs_confirmation', $activeTotp );
$tpl->setVariable( 'issuer', $issuer );
$tpl->setVariable( 'account_name', $account );
$tpl->setVariable( 'can_store', $canStore );
$tpl->setVariable( 'has_email', $hasEmail );
$tpl->setVariable( 'masked_email', sevenxAuthentication2faHelper::maskEmail( $setupUser->attribute( 'email' ) ) );
$tpl->setVariable( 'allowed_methods', $allowedMethods );
$tpl->setVariable( 'selected_method', $selectedMethod );
$tpl->setVariable( 'digits', $settings['digits'] );
$tpl->setVariable( 'period', $settings['period'] );
$tpl->setVariable( 'setup_user', $setupUser );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:user2fa/setup.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-Factor Authentication Setup' ),
                                'url' => false ) );
if ( $isPendingSetup )
{
    $pageLayout = sevenxAuthentication2faHelper::pageLayout();
    if ( $pageLayout )
        $Result['pagelayout'] = $pageLayout;
}
