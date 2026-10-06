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
  \class sevenxAuthentication2faAttempts sevenxauthentication2faattempts.php
  \ingroup sevenx_authentication_2fa
  \brief The limits of one pending second step: wrong codes and new e-mail codes.

  A pending challenge is an array kept in the session. These functions read
  and change only that array, so the policy is tested without a session:

  - every wrong code counts; after MaxAttempts the challenge ends and the
    visitor signs in with the password again (each wrong code also counts as
    a failed login of the account, so the account lock applies as well);
  - a new e-mail code can be asked for after ResendInterval seconds, at most
    MaxResends times per challenge;
  - a challenge ends after its lifetime.
*/
class sevenxAuthentication2faAttempts
{
    /**
     * Is the challenge still usable at $now?
     * @param array|null $pending
     * @param int $maxAttempts
     * @param int $now
     * @return bool
     */
    public static function isOpen( $pending, $maxAttempts, $now )
    {
        if ( !is_array( $pending ) || empty( $pending['user_id'] ) )
            return false;
        if ( !isset( $pending['expires'] ) || (int)$pending['expires'] < (int)$now )
            return false;
        return self::attempts( $pending ) < max( 1, (int)$maxAttempts );
    }

    /**
     * The number of wrong codes so far.
     * @param array $pending
     * @return int
     */
    public static function attempts( $pending )
    {
        return isset( $pending['attempts'] ) ? (int)$pending['attempts'] : 0;
    }

    /**
     * How many codes may still be tried.
     * @param array $pending
     * @param int $maxAttempts
     * @return int
     */
    public static function remaining( $pending, $maxAttempts )
    {
        return max( 0, max( 1, (int)$maxAttempts ) - self::attempts( $pending ) );
    }

    /**
     * Count one wrong code.
     * @param array $pending
     * @return array the changed challenge
     */
    public static function fail( $pending )
    {
        $pending['attempts'] = self::attempts( $pending ) + 1;
        return $pending;
    }

    /**
     * Seconds until a new e-mail code may be sent; 0 when it may be sent now,
     * -1 when no more may be sent for this challenge.
     * @param array $pending
     * @param int $interval
     * @param int $maxResends
     * @param int $now
     * @return int
     */
    public static function resendWait( $pending, $interval, $maxResends, $now )
    {
        $resends = isset( $pending['resends'] ) ? (int)$pending['resends'] : 0;
        if ( $resends >= max( 0, (int)$maxResends ) )
            return -1;
        $sentAt = isset( $pending['sent_at'] ) ? (int)$pending['sent_at'] : 0;
        return max( 0, $sentAt + max( 0, (int)$interval ) - (int)$now );
    }

    /**
     * Count one new e-mail code.
     * @param array $pending
     * @param int $now
     * @return array
     */
    public static function resent( $pending, $now )
    {
        $pending['resends'] = ( isset( $pending['resends'] ) ? (int)$pending['resends'] : 0 ) + 1;
        $pending['sent_at'] = (int)$now;
        return $pending;
    }

    /**
     * The hash an e-mail code is kept as in the session.
     * @param string $code
     * @param string $salt
     * @return string
     */
    public static function codeHash( $code, $salt )
    {
        return hash_hmac( 'sha256', preg_replace( '/[^0-9]/', '', (string)$code ), (string)$salt );
    }

    /**
     * Does a submitted e-mail code match the challenge?
     * @param array $pending
     * @param string $code
     * @return bool
     */
    public static function emailCodeMatches( $pending, $code )
    {
        if ( empty( $pending['code_hash'] ) || empty( $pending['code_salt'] ) || !is_scalar( $code ) )
            return false;
        $code = preg_replace( '/[^0-9]/', '', (string)$code );
        if ( $code === '' )
            return false;
        return hash_equals( (string)$pending['code_hash'], self::codeHash( $code, $pending['code_salt'] ) );
    }
}
