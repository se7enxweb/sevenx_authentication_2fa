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
  \class sevenxAuthentication2faSignIn sevenxauthentication2fasignin.php
  \ingroup sevenx_authentication_2fa
  \brief The second step on the sign-in paths outside the web login form.

  The login handler (sevenxUser2fa) asks for the second step in the web login
  form only. The kernel's other sign-in paths check a password themselves:
  the REST interface with basic authentication (rest), WebDAV (webdav), SOAP
  (soap) and the expservices login (expservices). Registered in site.ini
  [SignInSecondFactorSettings] Handlers[] (settings/site.ini.append.php), this
  class is what the kernel (expSignInSecondFactor) asks there:

  - checkSignIn(): for an account Enforce2FA applies to (and, with
    [OtherSignInPaths] GuardOptedIn=enabled, one that chose a second step
    itself), a password alone is refused on the guarded paths
    ([OtherSignInPaths] Guard[]: empty by default, so every path signs in
    with the password alone as before until a site lists rest, webdav, soap
    or expservices there). On the paths of CodeChannels[] (by default only the
    expservices login, which makes a session) the current code of the
    authenticator app counts; each code works once, and MaxAttempts wrong
    codes lock it for CodeLockSeconds.
  - appPasswordUser(): an app password made on user2fa/setup signs its
    account in on the paths it was made for that are guarded (rest, webdav,
    soap only: they never make a session the web interface would accept).
    On a path that is not guarded it is never even looked at.

  Accounts Enforce2FA does not apply to (and exempt ones: ExemptGroups[],
  ExemptEmailDomains[]) are not touched. The decisions themselves are
  sevenxAuthentication2faPolicy's, tested without a database.
*/
class sevenxAuthentication2faSignIn implements expSignInSecondFactorHandler
{
    /**
     * @param eZUser $user
     * @param string $channel
     * @param string|null $code
     * @return array|null
     */
    public function checkSignIn( eZUser $user, $channel, $code )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        if ( !$helper->isEnabled() )
            return null;
        $settings = $helper->otherPathSettings();
        $guarded = $settings['guard'] && in_array( $channel, $settings['guarded'], true );
        if ( !$guarded )
            return null;

        $userID = (int)$user->attribute( 'contentobject_id' );
        $enforced = $helper->isEnforcedFor( $user );
        $data = $helper->userData( $userID );
        $active = $data instanceof sevenxAuthentication2fa && $data->isActive();
        $method = $active ? $data->method() : sevenxAuthentication2faHelper::METHOD_DISABLED;
        $optedIn = $active && $settings['opted_in'];
        if ( !$enforced && !$optedIn )
            return null;

        $codeAccepted = in_array( $channel, $settings['code_channels'], true );
        $code = is_string( $code ) && trim( $code ) !== '' ? trim( $code ) : null;
        $matches = null;
        if ( $code !== null && $codeAccepted && $method === sevenxAuthentication2faHelper::METHOD_TOTP )
            $matches = $this->checkCode( $helper, $userID, $data, $code, $settings );

        $reason = sevenxAuthentication2faPolicy::otherPathDecision( $guarded, $enforced, $optedIn, $method, $codeAccepted, $code, $matches );
        if ( $reason === null )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_other_path_code_ok', 'channel=' . $channel, $userID );
            return null;
        }
        sevenxAuthentication2faHelper::authLog( '2fa_other_path_refused', 'channel=' . $channel . ' reason=' . $reason, $userID );
        return array( 'reason' => $reason, 'message' => self::message( $reason, $channel, $codeAccepted, $helper->appPasswordSettings() ) );
    }

    /**
     * Checks an authenticator code: once per time step, and locked after MaxAttempts wrong ones for CodeLockSeconds.
     * @return bool
     */
    protected function checkCode( sevenxAuthentication2faHelper $helper, $userID, sevenxAuthentication2fa $data, $code, array $settings )
    {
        $now = time();
        if ( sevenxAuthentication2faPolicy::codeLocked( $data->codeFailures(), $settings['code_max_attempts'], $settings['code_lock_seconds'], $now ) )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_other_path_code_locked', '', $userID );
            return false;
        }
        $step = $helper->matchTotp( $data->secret(), $code, $data->lastStep() );
        if ( $step === false )
        {
            $data->setCodeFailures( sevenxAuthentication2faPolicy::codeFailed( $data->codeFailures(), $settings['code_lock_seconds'], $now ) );
            $helper->saveUserData( $userID, $data );
            sevenxAuthentication2faHelper::authLog( '2fa_other_path_code_failed', '', $userID );
            return false;
        }
        // the code is used up: the same step never signs in again, here or in the web login form
        $data->setLastStep( $step );
        $data->setCodeFailures( null );
        $helper->saveUserData( $userID, $data );
        return true;
    }

    /**
     * The sentence a client gets with a refusal.
     * @param string $reason
     * @param string $channel
     * @param bool $codeAccepted
     * @param array $appPasswords sevenxAuthentication2faHelper::appPasswordSettings()
     * @return string
     */
    public static function message( $reason, $channel, $codeAccepted, array $appPasswords )
    {
        $appPasswordHere = $appPasswords['enabled'] && in_array( $channel, $appPasswords['channels'], true );
        if ( $reason === sevenxAuthentication2faPolicy::REASON_SETUP )
            return 'This account must set up its second sign-in step on the site first (user2fa/setup).'
                 . ( $appPasswordHere ? ' Then make an app password there for this client.' : '' );
        if ( $reason === sevenxAuthentication2faPolicy::REASON_INVALID )
            return $codeAccepted
                ? 'The authenticator code is not right, was used already, or too many wrong codes were sent. Send the code your app shows now.'
                : 'A one-time code is not accepted here.' . ( $appPasswordHere ? ' Use an app password made on the two-step sign-in page of the site (user2fa/setup).' : '' );
        $ways = array();
        if ( $appPasswordHere )
            $ways[] = 'use an app password made on the two-step sign-in page of the site (user2fa/setup)';
        if ( $codeAccepted )
            $ways[] = 'send the code of your authenticator app with the password (field otp, or the header X-Exp-OTP)';
        if ( $channel === 'rest' )
            $ways[] = 'use a personal API key or an OAuth token';
        return 'This account needs a second sign-in step; the password alone is not accepted here.'
             . ( $ways ? ' ' . ucfirst( implode( ', or ', $ways ) ) . '.' : ' Sign in on the site instead.' );
    }

    /**
     * @param string $login
     * @param string $secret
     * @param string $channel
     * @return eZUser|null
     */
    public function appPasswordUser( $login, $secret, $channel )
    {
        $helper = sevenxAuthentication2faHelper::instance();
        if ( !$helper->isEnabled() )
            return null;
        $settings = $helper->appPasswordSettings();
        if ( !$settings['enabled'] || !in_array( $channel, $settings['channels'], true ) )
            return null;
        if ( sevenxAuthentication2faPolicy::normalizeAppPassword( $secret ) === '' )
            return null;

        $user = eZUser::fetchByName( $login );
        if ( !$user instanceof eZUser && ( eZUser::authenticationMatch() & eZUser::AUTHENTICATE_EMAIL ) && eZMail::validate( $login ) )
            $user = eZUser::fetchByEmail( $login );
        if ( !$user instanceof eZUser || $user->isAnonymous() )
            return null;

        $userID = (int)$user->attribute( 'contentobject_id' );
        $data = $helper->userData( $userID );
        // app passwords belong to a working second step: none count without one
        if ( !$data instanceof sevenxAuthentication2fa || !$data->isActive() || !$data->appPasswords() )
            return null;
        $now = time();
        $entries = $data->appPasswords();
        $index = sevenxAuthentication2faPolicy::findAppPassword( $entries, $secret, $channel, $now );
        if ( $index === false )
        {
            sevenxAuthentication2faHelper::authLog( '2fa_app_password_failed', 'channel=' . $channel, $userID );
            return null;
        }

        $entry = $entries[$index];
        if ( $now - (int)$entry['last_used'] >= $settings['last_used_interval'] )
        {
            $entries[$index]['last_used'] = $now;
            $entries[$index]['last_ip'] = class_exists( 'eZSys' ) ? (string)eZSys::clientIP() : '';
            $data->setAppPasswords( $entries );
            $helper->saveUserData( $userID, $data );
            sevenxAuthentication2faHelper::auditAppPassword( 'use', $user, $entries[$index], array( 'channel' => $channel ) );
        }
        return $user;
    }
}
