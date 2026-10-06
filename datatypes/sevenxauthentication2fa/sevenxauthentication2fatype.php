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
  \class sevenxAuthentication2faType sevenxauthentication2fatype.php
  \ingroup sevenx_authentication_2fa
  \brief Exponential datatype that stores 2FA method, TOTP secret and verification state.
*/
class sevenxAuthentication2faType extends eZDataType
{
    const DATA_TYPE_STRING = 'sevenxauthentication2fa';

    public function __construct()
    {
        parent::__construct( self::DATA_TYPE_STRING, '7x 2FA Configuration' );
    }

    function initializeObjectAttribute( $contentObjectAttribute, $currentVersion, $originalContentObjectAttribute )
    {
        if ( $currentVersion != false )
        {
            $dataText = $originalContentObjectAttribute->attribute( 'data_text' );
            $contentObjectAttribute->setAttribute( 'data_text', $dataText );
        }
        else
        {
            $contentObjectAttribute->setAttribute( 'data_text', '' );
        }
    }

    function validateObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $error = null;
        $data = $this->processObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute, $error );
        if ( $data === null )
            return eZInputValidator::STATE_ACCEPTED;

        $contentObjectAttribute->setAttribute( 'data_text', $data->toJson() );

        if ( $error !== null )
        {
            $contentObjectAttribute->setValidationError( $error );
            $contentObjectAttribute->setHasValidationError( true );
            return eZInputValidator::STATE_INVALID;
        }

        return eZInputValidator::STATE_ACCEPTED;
    }

    function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $error = null;
        $data = $this->processObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute, $error );
        if ( $data === null )
            return false;

        $contentObjectAttribute->setAttribute( 'data_text', $data->toJson() );
        return true;
    }

    private function processObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute, &$error )
    {
        $error = null;
        $variableName = $base . '_sevenxauthentication2fa_method_' . $contentObjectAttribute->attribute( 'id' );
        $codeName = $base . '_sevenxauthentication2fa_code_' . $contentObjectAttribute->attribute( 'id' );

        if ( !$http->hasPostVariable( $variableName ) || !is_string( $http->postVariable( $variableName ) ) )
            return null;

        $method = trim( $http->postVariable( $variableName ) );
        $helper = sevenxAuthentication2faHelper::instance();
        // What the attribute holds now (the draft): a confirmed configuration, an enrolment, or nothing
        $current = sevenxAuthentication2fa::fromJson( $contentObjectAttribute->attribute( 'data_text' ) );

        if ( $method === 'disabled' && $helper->isEnforced() )
        {
            $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication is required. Please choose an authentication method.' );
            return $current;
        }

        if ( $method === 'totp' )
        {
            // An authenticator that is confirmed stays as it is: saving the form again needs no code
            if ( $current->isActive() && $current->method() === 'totp' )
                return $current;

            // The secret is the one of this enrolment, made here and kept in the draft; it never comes from the form
            $secret = $current->method() === 'totp' && !$current->verified() && sevenxAuthentication2faTOTP::isValidSecret( $current->secret() )
                ? $current->secret() : '';
            if ( $secret === '' )
            {
                $settings = $helper->totpSettings();
                $secret = sevenxAuthentication2faTOTP::generateSecret( $settings['bytes'] );
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Add the new key to your authenticator app, then enter the code it shows.' );
                return new sevenxAuthentication2fa( 'totp', $secret, false );
            }

            $code = $http->hasPostVariable( $codeName ) && is_string( $http->postVariable( $codeName ) ) ? trim( $http->postVariable( $codeName ) ) : '';
            if ( $code === '' )
            {
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Please enter the verification code from your authenticator app.' );
                return new sevenxAuthentication2fa( 'totp', $secret, false );
            }

            $step = $helper->matchTotp( $secret, $code );
            if ( $step === false )
            {
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'The verification code is incorrect.' );
                return new sevenxAuthentication2fa( 'totp', $secret, false );
            }

            return new sevenxAuthentication2fa( 'totp', $secret, true, null, $step );
        }
        elseif ( $method === 'email' )
        {
            return new sevenxAuthentication2fa( 'email', '', true );
        }
        else
        {
            return new sevenxAuthentication2fa();
        }
    }

    function objectAttributeContent( $contentObjectAttribute )
    {
        $data = sevenxAuthentication2fa::fromJson( $contentObjectAttribute->attribute( 'data_text' ) );
        // The account name an authenticator app shows for an enrolment: the user's e-mail address or login
        $user = eZUser::fetch( (int)$contentObjectAttribute->attribute( 'contentobject_id' ) );
        if ( $user instanceof eZUser )
            $data->setAccount( $user->attribute( 'email' ) ? $user->attribute( 'email' ) : $user->attribute( 'login' ) );
        return $data;
    }

    function isIndexable()
    {
        return false;
    }

    function isInformationCollector()
    {
        return false;
    }

    function sortKeyType()
    {
        return 'string';
    }

    function sortKey( $contentObjectAttribute )
    {
        return $contentObjectAttribute->attribute( 'data_text' );
    }

    function title( $contentObjectAttribute, $name = null )
    {
        $data = $contentObjectAttribute->content();
        if ( $data instanceof sevenxAuthentication2fa )
        {
            return 'Method: ' . $data->method();
        }
        return 'Not configured';
    }

    function hasObjectAttributeContent( $contentObjectAttribute )
    {
        $data = $contentObjectAttribute->content();
        return $data instanceof sevenxAuthentication2fa && $data->method() !== 'disabled';
    }
}

eZDataType::register( sevenxAuthentication2faType::DATA_TYPE_STRING, 'sevenxAuthentication2faType' );
