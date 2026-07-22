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
        parent::eZDataType( self::DATA_TYPE_STRING, '7x 2FA Configuration' );
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
        $secretName = $base . '_sevenxauthentication2fa_secret_' . $contentObjectAttribute->attribute( 'id' );
        $codeName = $base . '_sevenxauthentication2fa_code_' . $contentObjectAttribute->attribute( 'id' );

        if ( !$http->hasPostVariable( $variableName ) )
            return null;

        $method = trim( $http->postVariable( $variableName ) );

        if ( $method === 'disabled' && sevenxAuthentication2faHelper::instance()->isEnforced() )
        {
            $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Two-factor authentication is required. Please choose an authentication method.' );
            return new sevenxAuthentication2fa();
        }

        if ( $method === 'totp' )
        {
            $secret = $http->hasPostVariable( $secretName ) ? trim( $http->postVariable( $secretName ) ) : '';
            if ( !$secret )
                $secret = sevenxAuthentication2faTOTP::generateSecret();

            $code = $http->hasPostVariable( $codeName ) ? trim( $http->postVariable( $codeName ) ) : '';
            if ( $code === '' )
            {
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Please enter the verification code from your authenticator app.' );
                return new sevenxAuthentication2fa( 'totp', $secret, false );
            }

            if ( !sevenxAuthentication2faTOTP::verify( $secret, $code ) )
            {
                $error = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'The verification code is incorrect.' );
                return new sevenxAuthentication2fa( 'totp', $secret, false );
            }

            return new sevenxAuthentication2fa( 'totp', $secret, true );
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
        return sevenxAuthentication2fa::fromJson( $contentObjectAttribute->attribute( 'data_text' ) );
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
