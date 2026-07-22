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
     * If $resend is false and a valid pending challenge already exists for the user,
     * the existing code is reused and no new e-mail is sent.
     * @param eZUser $user
     * @param string $redirectUri
     * @param bool $resend If true, always generate and send a new code.
     * @return string the code
     */
    public static function sendCode( eZUser $user, $redirectUri = '', $resend = false )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $ini = $helper->ini();

        $length = $helper->intSetting( 'CodeSettings', 'Length', 6 );
        $ttl = $helper->intSetting( 'CodeSettings', 'EmailTTL', 600 );
        $userID = $user->attribute( 'contentobject_id' );

        $existing = $helper->getPendingChallenge( false, $userID );
        if ( !$resend && $existing && $existing['method'] === sevenxAuthentication2faHelper::METHOD_EMAIL && $existing['expires'] >= time() )
        {
            $code = $existing['code'];
            sevenxAuthentication2faHelper::authLog( '2fa_email_code_reused', 'email=' . $user->attribute( 'email' ), $userID );
        }
        else
        {
            $code = sevenxAuthentication2faHelper::randomCode( $length );
        }

        $email = $user->attribute( 'email' );
        $subjectTemplate = $ini->hasVariable( 'EmailSettings', 'Subject' ) ? $ini->variable( 'EmailSettings', 'Subject' ) : 'Your login verification code is {code}';
        $subject = str_replace( '{code}', $code, $subjectTemplate );

        $siteUrl = eZSys::serverURL() . eZSys::indexDir();

        $verifyPath = 'user2fa/verify/code/' . urlencode( $code );
        eZURI::transformURI( $verifyPath, false, null, false );
        $verifyUrl = eZSys::serverURL() . $verifyPath;

        // Render the e-mail body from an overridable template so it can contain newlines and formatting.
        $tpl = eZTemplate::factory();
        $tpl->setVariable( 'code', $code );
        $tpl->setVariable( 'expires', (int)( $ttl / 60 ) );
        $tpl->setVariable( 'site_url', $siteUrl );
        $tpl->setVariable( 'verify_url', $verifyUrl );
        $body = $tpl->fetch( 'design:mail/2fa_code.tpl' );

        if ( $ini->hasVariable( 'EmailSettings', 'Body' ) && trim( $ini->variable( 'EmailSettings', 'Body' ) ) !== '' )
        {
            $bodyTemplate = $ini->variable( 'EmailSettings', 'Body' );
            $body = str_replace( array( '{code}', '{expires}', '{site_url}', '{verify_url}' ), array( $code, (int)( $ttl / 60 ), $siteUrl, $verifyUrl ), $bodyTemplate );
        }

        $sent = 'no';
        if ( $resend || !$existing || $existing['code'] !== $code )
        {
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

            $sent = eZMailTransport::send( $mail ) ? 'yes' : 'no';
        }

        $helper->setPendingChallenge( $userID, sevenxAuthentication2faHelper::METHOD_EMAIL, $code, $ttl, $redirectUri );

        sevenxAuthentication2faHelper::authLog( '2fa_email_code_sent', 'email=' . $email . ' sent=' . $sent, $userID );

        return $code;
    }

    /**
     * Verify an email OTP against the pending challenge.
     * @param string $code
     * @return bool
     */
    public static function verifyCode( $code )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $code = preg_replace( '/[^0-9]/', '', $code );
        $pending = $helper->getPendingChallenge( $code );
        if ( !$pending )
            return false;

        if ( time() > $pending['expires'] )
            return false;

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
