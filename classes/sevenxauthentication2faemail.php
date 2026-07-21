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
  \class sevenxAuthentication2faEmail sevenxauthentication2faemail.php
  \ingroup sevenx_authentication_2fa
  \brief Email-based one-time password delivery.
*/
class sevenxAuthentication2faEmail
{
    /**
     * Generate, store and send an email OTP to the given user.
     * @param eZUser $user
     * @param string $redirectUri
     * @return string the generated code
     */
    public static function sendCode( eZUser $user, $redirectUri = '' )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $ini = $helper->ini();

        $length = $helper->intSetting( 'CodeSettings', 'Length', 6 );
        $ttl = $helper->intSetting( 'CodeSettings', 'EmailTTL', 600 );

        $code = sevenxAuthentication2faHelper::randomCode( $length );

        $email = $user->attribute( 'email' );
        $subjectTemplate = $ini->hasVariable( 'EmailSettings', 'Subject' ) ? $ini->variable( 'EmailSettings', 'Subject' ) : 'Your login verification code is {code}';
        $bodyTemplate = $ini->hasVariable( 'EmailSettings', 'Body' ) ? $ini->variable( 'EmailSettings', 'Body' ) : 'Your code is {code}.';

        $subject = str_replace( '{code}', $code, $subjectTemplate );
        $body = str_replace( array( '{code}', '{expires}' ), array( $code, (int)( $ttl / 60 ) ), $bodyTemplate );

        $mail = new eZMail();
        $mail->setReceiver( $email );

        $sender = $ini->variable( 'EmailSettings', 'Sender' );
        if ( $sender && eZMail::validate( $sender ) )
            $mail->setSender( $sender );
        else
            $mail->setSenderText( eZINI::instance()->variable( 'MailSettings', 'AdminEmail' ) );

        $mail->setSubject( $subject );
        $mail->setBody( $body );
        $mail->setContentType( 'text/plain' );

        $result = eZMailTransport::send( $mail );

        $helper->setPendingChallenge( $user->attribute( 'contentobject_id' ), sevenxAuthentication2faHelper::METHOD_EMAIL, $code, $ttl, $redirectUri );

        sevenxAuthentication2faHelper::authLog( '2fa_email_code_sent', 'email=' . $email . ' sent=' . ( $result ? 'yes' : 'no' ), $user->attribute( 'contentobject_id' ) );

        return $code;
    }

    /**
     * Verify an email OTP against the session pending challenge.
     * @param string $code
     * @return bool
     */
    public static function verifyCode( $code )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $pending = $helper->getPendingChallenge();
        if ( !$pending )
            return false;

        if ( time() > $pending['expires'] )
            return false;

        $code = preg_replace( '/[^0-9]/', '', $code );
        if ( !function_exists( 'hash_equals' ) )
        {
            if ( strlen( $pending['code'] ) !== strlen( $code ) )
                return false;
            $result = 0;
            for ( $i = 0; $i < strlen( $pending['code'] ); $i++ )
                $result |= ord( $pending['code'][$i] ) ^ ord( $code[$i] );
            return $result === 0;
        }
        return hash_equals( $pending['code'], $code );
    }
}
