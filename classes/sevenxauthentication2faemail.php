<?php
//
// sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension
// Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
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

        $mail = self::buildMail( $user, $code, (int)( $ttl / 60 ) );
        $sent = (bool)eZMailTransport::send( $mail );

        sevenxAuthentication2faHelper::authLog( $resend ? '2fa_email_code_resent' : '2fa_email_code_sent', 'sent=' . ( $sent ? 'yes' : 'no' ), $userID );

        return $sent;
    }

    /**
     * The sign-in code mail, not sent: an HTML mail with a plain-text part in the kernel's mail layout
     * (design:mail/2fa_code.tpl), or plain text (design:mail/2fa_code_plain.tpl) with a kernel without that
     * layout or with [EmailSettings] Body set. [EmailSettings] Subject, when set, replaces the subject of the template.
     *
     * @param eZUser $user
     * @param string $code
     * @param int $minutes how long the code works
     * @return eZMail
     */
    public static function buildMail( eZUser $user, $code, $minutes )
    {
        $ini = sevenxAuthentication2faHelper::instance()->ini();
        $siteINI = eZINI::instance();
        $siteUrl = eZSys::serverURL() . eZSys::indexDir();
        $siteName = (string)$siteINI->variable( 'SiteSettings', 'SiteName' );

        $verifyUrl = 'user2fa/verify/code/' . rawurlencode( $code );
        eZURI::transformURI( $verifyUrl, false, 'full' );
        $loginUrl = 'user/login';
        eZURI::transformURI( $loginUrl, false, 'full' );

        // the kernel's mail layout came with expMailTemplateBody::applyRendered()
        $layout = class_exists( 'expMailTemplateBody' ) && method_exists( 'expMailTemplateBody', 'applyRendered' );
        $tpl = eZTemplate::factory();
        $vars = array( 'code' => $code, 'expires' => (int)$minutes, 'site_url' => $siteUrl, 'site_name' => $siteName,
                       'verify_url' => $verifyUrl, 'login_url' => $loginUrl, 'user' => $user );
        foreach ( $vars as $name => $value )
            $tpl->setVariable( $name, $value );
        $body = (string)$tpl->fetch( $layout ? 'design:mail/2fa_code.tpl' : 'design:mail/2fa_code_plain.tpl' );
        $subject = $tpl->hasVariable( 'subject' ) ? trim( (string)$tpl->variable( 'subject' ) ) : '';

        $mail = new eZMail();
        $mail->setReceiver( $user->attribute( 'email' ) );
        $sender = $ini->hasVariable( 'EmailSettings', 'Sender' ) ? trim( (string)$ini->variable( 'EmailSettings', 'Sender' ) ) : '';
        if ( $sender && eZMail::validate( $sender ) )
            $mail->setSender( $sender );
        else
            $mail->setSender( $siteINI->variable( 'MailSettings', 'AdminEmail' ) );

        $subjectSetting = $ini->hasVariable( 'EmailSettings', 'Subject' ) ? trim( (string)$ini->variable( 'EmailSettings', 'Subject' ) ) : '';
        if ( $subjectSetting !== '' )
            $subject = str_replace( '{code}', $code, $subjectSetting );
        if ( $subject === '' )
            $subject = ezpI18n::tr( 'extension/sevenx_authentication_2fa', 'Your sign-in code for %site: %code', null,
                                    array( '%site' => $siteName, '%code' => $code ) );
        $mail->setSubject( $subject );

        $bodySetting = $ini->hasVariable( 'EmailSettings', 'Body' ) ? trim( (string)$ini->variable( 'EmailSettings', 'Body' ) ) : '';
        if ( $bodySetting !== '' )
        {
            $mail->setContentType( 'text/plain' );
            $mail->setBody( str_replace( array( '{code}', '{expires}', '{site_url}', '{verify_url}' ),
                                         array( $code, (int)$minutes, $siteUrl, $verifyUrl ), $bodySetting ) );
        }
        elseif ( $layout )
            expMailTemplateBody::apply( $mail, $tpl, $body );
        else
        {
            $mail->setContentType( 'text/plain' );
            $mail->setBody( $body );
        }
        if ( method_exists( $mail, 'setCategory' ) )
            $mail->setCategory( 'security' );
        // the request's template object renders the page next: the mail's variables are not left in it
        foreach ( array_merge( array_keys( $vars ), array( 'subject', 'content_type', 'body_text' ) ) as $name )
        {
            if ( $tpl->hasVariable( $name ) )
                $tpl->unsetVariable( $name );
        }
        return $mail;
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
