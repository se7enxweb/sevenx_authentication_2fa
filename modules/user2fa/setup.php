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
$currentUser = eZUser::currentUser();
$setupUser = $currentUser;
$isPendingSetup = false;

if ( !$currentUser->isRegistered() )
{
    if ( $http->hasSessionVariable( 'Sevenx2FA_SetupUserID' ) )
    {
        $pendingUserID = (int) $http->sessionVariable( 'Sevenx2FA_SetupUserID' );
        $setupUser = eZUser::fetch( $pendingUserID );
        if ( $setupUser instanceof eZUser )
        {
            $isPendingSetup = true;
            sevenxAuthentication2faHelper::authLog( '2fa_setup_pending', 'pending user id=' . $pendingUserID, $pendingUserID );
        }
    }

    if ( !$setupUser instanceof eZUser || !$setupUser->isRegistered() )
    {
        sevenxAuthentication2faHelper::authLog( '2fa_setup_unauthenticated', 'setup page requested by anonymous user' );
        eZHTTPTool::redirect( '/user/login' );
        eZExecution::cleanExit();
    }
}

$helper = sevenxAuthentication2faHelper::instance();
$userID = $setupUser->attribute( 'contentobject_id' );
$data = $helper->userData( $userID );
if ( !$data )
    $data = new sevenxAuthentication2fa();

$error = '';
$success = '';

if ( $http->hasPostVariable( 'SetupButton' ) )
{
    $method = trim( $http->postVariable( 'Method' ) );

    if ( $method === 'disabled' && $helper->isEnforced() )
    {
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication is required. You must choose an authentication method.' );
    }
    elseif ( $method === 'totp' )
    {
        $previousSecret  = $data->secret();
        $previousVerified = $data->verified();

        $secret = $http->hasPostVariable( 'Secret' ) ? trim( $http->postVariable( 'Secret' ) ) : '';
        if ( !$secret )
            $secret = $previousSecret;
        if ( !$secret )
            $secret = sevenxAuthentication2faTOTP::generateSecret();

        $data->setMethod( 'totp' );
        $data->setSecret( $secret );

        $code = $http->hasPostVariable( 'Code' ) ? trim( $http->postVariable( 'Code' ) ) : '';
        if ( $code !== '' )
        {
            if ( sevenxAuthentication2faTOTP::verify( $secret, $code ) )
            {
                $data->setVerified( true );
                sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_ok', 'totp configured and verified', $userID );
                $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'TOTP configured successfully.' );
            }
            else
            {
                $data->setVerified( false );
                sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_failed', 'totp verification code incorrect', $userID );
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'The verification code is incorrect.' );
            }
        }
        elseif ( $secret === $previousSecret && $previousVerified )
        {
            // Secret unchanged and was previously verified; keep TOTP enabled.
            $data->setVerified( true );
            sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_kept', 'totp secret unchanged and already verified', $userID );
            $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'TOTP remains configured.' );
        }
        else
        {
            $data->setVerified( false );
            sevenxAuthentication2faHelper::authLog( '2fa_setup_totp_pending', 'totp secret generated, awaiting verification', $userID );
        }
    }
    elseif ( $method === 'email' )
    {
        $data->setMethod( 'email' );
        $data->setVerified( true );
        sevenxAuthentication2faHelper::authLog( '2fa_setup_email', 'email 2fa enabled', $userID );
        $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'E-mail authentication configured successfully.' );
    }
    else
    {
        $data->setMethod( 'disabled' );
        $data->setVerified( false );
        sevenxAuthentication2faHelper::authLog( '2fa_setup_disabled', '2fa disabled', $userID );
        $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication disabled.' );
    }
}
elseif ( $http->hasPostVariable( 'ResetButton' ) )
{
    if ( $helper->isEnforced() )
    {
        $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication is required. Resetting is not allowed while enforcement is enabled.' );
    }
    else
    {
        $data = new sevenxAuthentication2fa();
        sevenxAuthentication2faHelper::authLog( '2fa_setup_reset', '2fa configuration reset by user', $userID );
        $success = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication configuration has been reset.' );
    }
}
else
{
    sevenxAuthentication2faHelper::authLog( '2fa_setup_view', 'setup page viewed', $userID );
}

// Persist back to the first sevenxauthentication2fa attribute on the user object when the form was submitted.
if ( $http->hasPostVariable( 'SetupButton' ) || $http->hasPostVariable( 'ResetButton' ) )
{
    $object = $setupUser->contentObject();
    $language = $object->attribute( 'initial_language_code' );
    if ( !$language )
        $language = 'eng-US';
    foreach ( $object->fetchDataMap( false, $language ) as $attribute )
    {
        if ( $attribute->attribute( 'data_type_string' ) === 'sevenxauthentication2fa' )
        {
            $attribute->setAttribute( 'data_text', $data->toJson() );
            $attribute->store();
            break;
        }
    }

    // After a successful setup, finish the login (if this was a pending setup) and redirect.
    if ( $http->hasPostVariable( 'SetupButton' ) && $error === '' && $data->method() !== 'disabled' )
    {
        if ( $isPendingSetup )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_setup_completed_login', 'login=' . $setupUser->attribute( 'login' ), $userID );
            eZUser::updateLastVisit( $userID, true );
            eZUser::setCurrentlyLoggedInUser( $setupUser, $userID );
            eZUser::setFailedLoginAttempts( $userID, 0 );
        }

        $redirect = $http->hasSessionVariable( 'Sevenx2FA_SetupRedirect' ) ? $http->sessionVariable( 'Sevenx2FA_SetupRedirect' ) : '/';
        $redirect = sevenxAuthentication2faHelper::normalizeRedirect( $redirect );
        $http->removeSessionVariable( 'Sevenx2FA_SetupUserID' );
        $http->removeSessionVariable( 'Sevenx2FA_SetupRedirect' );
        eZSession::stop();
        eZHTTPTool::redirect( $redirect );
        eZExecution::cleanExit();
    }
}

$secret = $data->secret() ? $data->secret() : sevenxAuthentication2faTOTP::generateSecret();
$issuer = $helper->issuer();
$account = $setupUser->attribute( 'email' ) ? $setupUser->attribute( 'email' ) : $setupUser->attribute( 'login' );
$provisioningUri = sevenxAuthentication2faTOTP::provisioningUri( $account, $secret, $issuer );
$qrCode = sevenxAuthentication2faQR::pngDataUri( $provisioningUri );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'data', $data );
$tpl->setVariable( 'secret', $secret );
$tpl->setVariable( 'provisioning_uri', $provisioningUri );
$tpl->setVariable( 'qr_code', $qrCode );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'success', $success );
$tpl->setVariable( 'is_enforced', $helper->isEnforced() );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:user2fa/setup.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-Factor Authentication Setup' ),
                                'url' => false ) );
