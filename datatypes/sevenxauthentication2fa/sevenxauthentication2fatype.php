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
  \brief eZ Publish datatype that stores 2FA method, TOTP secret and verification state.
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
        return eZInputValidator::STATE_ACCEPTED;
    }

    function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $variableName = $base . '_sevenxauthentication2fa_method_' . $contentObjectAttribute->attribute( 'id' );
        $secretName = $base . '_sevenxauthentication2fa_secret_' . $contentObjectAttribute->attribute( 'id' );
        $codeName = $base . '_sevenxauthentication2fa_code_' . $contentObjectAttribute->attribute( 'id' );

        if ( !$http->hasPostVariable( $variableName ) )
            return false;

        $method = trim( $http->postVariable( $variableName ) );
        $data = sevenxAuthentication2fa::fromJson( $contentObjectAttribute->attribute( 'data_text' ) );

        if ( $method === 'totp' )
        {
            $secret = $http->hasPostVariable( $secretName ) ? trim( $http->postVariable( $secretName ) ) : '';
            if ( !$secret )
            {
                $secret = sevenxAuthentication2faTOTP::generateSecret();
            }

            $data->setMethod( 'totp' );
            $data->setSecret( $secret );

            // Verify the first code before marking as enabled.
            if ( $http->hasPostVariable( $codeName ) )
            {
                $code = trim( $http->postVariable( $codeName ) );
                if ( sevenxAuthentication2faTOTP::verify( $secret, $code ) )
                {
                    $data->setVerified( true );
                }
            }
        }
        elseif ( $method === 'email' )
        {
            $data->setMethod( 'email' );
            $data->setSecret( '' );
            $data->setVerified( true );
        }
        else
        {
            $data = new sevenxAuthentication2fa();
        }

        $contentObjectAttribute->setAttribute( 'data_text', $data->toJson() );
        return true;
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
