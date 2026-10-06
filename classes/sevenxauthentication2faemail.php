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

  The code is kept in the visitor's session as a salted hash only. The link
  in the e-mail (user2fa/verify/code/<code>) works in the browser that typed
  the password; opened anywhere else it only says so.
*/
class sevenxAuthentication2faEmail
{
    /**
     * Generate, store and send an email OTP to the given user, starting (or
     * renewing) the pending challenge of this session.
     * @param eZUser $user
     * @param string $redirectUri a safe target
     * @param bool $resend true when the visitor asked for a new code
     * @return bool whether the mail was handed to the transport
     */
    public static function sendCode( eZUser $user, $redirectUri = '', $resend = false )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        $ini = $helper->ini();
        $limits = $helper->limits();

        $length = max( 6, min( 8, $helper->intSetting( 'CodeSettings', 'Length', 6 ) ) );
        $ttl = $limits['email_ttl'];
        $userID = (int)$user->attribute( 'contentobject_id' );
        $code = sevenxAuthentication2faHelper::randomCode( $length );
        $salt = bin2hex( random_bytes( 16 ) );

        $extra = array(
            'code_salt' => $salt,
            'code_hash' => sevenxAuthentication2faAttempts::codeHash( $code, $salt ),
            'sent_at'   => time(),
        );
        $pending = $resend ? $helper->pending() : null;
        if ( $pending && (int)$pending['user_id'] === $userID )
        {
            // A new code for the same challenge: the wrong codes so far still count
            $pending = sevenxAuthentication2faAttempts::resent( $pending, time() );
            $pending = array_merge( $pending, $extra, array( 'expires' => time() + $ttl ) );
            $helper->updatePending( $pending );
        }
        else
        {
            $helper->startPending( $userID, sevenxAuthentication2faHelper::METHOD_EMAIL, $ttl, $redirectUri, $extra );
        }

        $email = $user->attribute( 'email' );
        $subjectTemplate = $ini->hasVariable( 'EmailSettings', 'Subject' ) ? $ini->variable( 'EmailSettings', 'Subject' ) : 'Your login verification code is {code}';
        $subject = str_replace( '{code}', $code, $subjectTemplate );

        $siteUrl = eZSys::serverURL() . eZSys::indexDir();

        $verifyPath = 'user2fa/verify/code/' . rawurlencode( $code );
        eZURI::transformURI( $verifyPath, false, 'full' );
        $verifyUrl = $verifyPath;

        // Render the e-mail body from an overridable template so it can contain newlines and formatting.
        $tpl = eZTemplate::factory();
        $tpl->setVariable( 'code', $code );
        $tpl->setVariable( 'expires', (int)( $ttl / 60 ) );
        $tpl->setVariable( 'site_url', $siteUrl );
        $tpl->setVariable( 'verify_url', $verifyUrl );
        $body = $tpl->fetch( 'design:mail/2fa_code.tpl' );

        if ( $ini->hasVariable( 'EmailSettings', 'Body' ) && trim( (string)$ini->variable( 'EmailSettings', 'Body' ) ) !== '' )
        {
            $bodyTemplate = $ini->variable( 'EmailSettings', 'Body' );
            $body = str_replace( array( '{code}', '{expires}', '{site_url}', '{verify_url}' ), array( $code, (int)( $ttl / 60 ), $siteUrl, $verifyUrl ), $bodyTemplate );
        }

        $mail = new eZMail();
        $mail->setReceiver( $email );

        $sender = $ini->hasVariable( 'EmailSettings', 'Sender' ) ? trim( (string)$ini->variable( 'EmailSettings', 'Sender' ) ) : '';
        if ( $sender && eZMail::validate( $sender ) )
            $mail->setSender( $sender );
        else
            $mail->setSender( eZINI::instance()->variable( 'MailSettings', 'AdminEmail' ) );

        $mail->setSubject( $subject );
        $mail->setBody( $body );
        $mail->setContentType( 'text/plain' );

        $sent = (bool)eZMailTransport::send( $mail );

        sevenxAuthentication2faHelper::authLog( $resend ? '2fa_email_code_resent' : '2fa_email_code_sent', 'sent=' . ( $sent ? 'yes' : 'no' ), $userID );

        return $sent;
    }

    /**
     * Verify an email OTP against the pending challenge of this session.
     * @param string $code
     * @return bool
     */
    public static function verifyCode( $code )
    {
        $pending = sevenxAuthentication2faHelper::instance()->pending();
        if ( !$pending || $pending['method'] !== sevenxAuthentication2faHelper::METHOD_EMAIL )
            return false;
        return sevenxAuthentication2faAttempts::emailCodeMatches( $pending, $code );
    }
}
