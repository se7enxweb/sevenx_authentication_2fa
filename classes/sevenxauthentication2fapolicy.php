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
  \class sevenxAuthentication2faPolicy sevenxauthentication2fapolicy.php
  \ingroup sevenx_authentication_2fa
  \brief Who has to have a second step, for how long a first setup may wait,
         and the recovery codes of an authenticator.

  Plain functions over plain values (group ids, an e-mail address, times), so
  the rules are tested without a database. sevenxAuthentication2faHelper
  reads the settings and the user and calls these.

  Enforcement ([General] in sevenxauthentication2fa.ini):
  - Enforce2FA=enabled switches it on. Without EnforcedGroups[] it applies to
    every user, as before; with EnforcedGroups[] only to members of those
    user groups (directly or through a group below them).
  - ExemptGroups[] and ExemptEmailDomains[] take accounts out of it again.
    They are still free to set up a second step themselves.

  Grace period ([General] GracePeriodDays): a user who has to set up a second
  step and has none may sign in without one, saying "later", for that many
  days from the first sign-in that asked. 0 means the setup comes first.

  Recovery codes ([Security] RecoveryCodes): one-time codes that sign in in
  place of the authenticator's code when the phone is lost. Only their salted
  hashes are stored; each works once.
*/
class sevenxAuthentication2faPolicy
{
    /**
     * Characters of a recovery code: no 0/O, 1/I/L, so a code read from paper is typed right.
     */
    const RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Length of a recovery code without its dash (10 characters of 31: about 49 bits).
     */
    const RECOVERY_LENGTH = 10;

    /**
     * Does enforcement apply to a user?
     * @param bool $enforced Enforce2FA
     * @param int[] $enforcedGroupIDs object ids of EnforcedGroups[] (empty: every user)
     * @param int[] $exemptGroupIDs object ids of ExemptGroups[]
     * @param string[] $exemptDomains ExemptEmailDomains[]
     * @param int[] $userGroupIDs object ids of every group the user is in, with the groups above them
     * @param string $email the user's e-mail address
     * @param bool $groupsConfigured whether EnforcedGroups[] lists anything at all (a list that names no
     *        existing group must not turn into "every user", nor into "nobody" silently: it means nobody,
     *        and the helper logs it)
     * @return bool
     */
    public static function applies( $enforced, array $enforcedGroupIDs, array $exemptGroupIDs, array $exemptDomains,
                                    array $userGroupIDs, $email, $groupsConfigured = null )
    {
        if ( !$enforced )
            return false;

        $userGroupIDs = array_map( 'intval', $userGroupIDs );
        if ( array_intersect( array_map( 'intval', $exemptGroupIDs ), $userGroupIDs ) )
            return false;
        if ( self::emailInDomains( $email, $exemptDomains ) )
            return false;

        if ( $groupsConfigured === null )
            $groupsConfigured = (bool)$enforcedGroupIDs;
        if ( !$groupsConfigured )
            return true;

        return (bool)array_intersect( array_map( 'intval', $enforcedGroupIDs ), $userGroupIDs );
    }

    /**
     * Is the address in one of the domains (exactly that domain, not a subdomain of it)?
     * @param string $email
     * @param string[] $domains
     * @return bool
     */
    public static function emailInDomains( $email, array $domains )
    {
        $email = strtolower( trim( (string)$email ) );
        $at = strrpos( $email, '@' );
        if ( $at === false || $at === 0 )
            return false;
        $domain = rtrim( substr( $email, $at + 1 ), '.' );
        if ( $domain === '' )
            return false;
        foreach ( $domains as $exempt )
        {
            $exempt = ltrim( rtrim( strtolower( trim( (string)$exempt ) ), '.' ), '@' );
            if ( $exempt !== '' && $domain === $exempt )
                return true;
        }
        return false;
    }

    /**
     * Seconds left of a grace period, 0 when there is none (any more).
     * @param int $graceDays GracePeriodDays
     * @param int|null $startedAt when a sign-in first asked this user for a second step, null: not yet
     * @param int $now
     * @return int
     */
    public static function graceLeft( $graceDays, $startedAt, $now )
    {
        $graceDays = (int)$graceDays;
        if ( $graceDays <= 0 )
            return 0;
        $startedAt = $startedAt === null ? (int)$now : (int)$startedAt;
        return max( 0, $startedAt + $graceDays * 86400 - (int)$now );
    }

    /**
     * New recovery codes, in the form ABCDE-FGHJK.
     * @param int $count
     * @return string[]
     */
    public static function newRecoveryCodes( $count )
    {
        $count = max( 0, min( 20, (int)$count ) );
        $alphabet = self::RECOVERY_ALPHABET;
        $max = strlen( $alphabet ) - 1;
        $codes = array();
        while ( count( $codes ) < $count )
        {
            $code = '';
            for ( $i = 0; $i < self::RECOVERY_LENGTH; $i++ )
                $code .= $alphabet[random_int( 0, $max )];
            $code = substr( $code, 0, 5 ) . '-' . substr( $code, 5 );
            $codes[$code] = $code;
        }
        return array_values( $codes );
    }

    /**
     * A typed recovery code in its stored form: upper case, without spaces and dashes; '' when it cannot be one.
     * @param string $code
     * @return string
     */
    public static function normalizeRecoveryCode( $code )
    {
        $code = strtoupper( preg_replace( '/[\s\-]+/', '', (string)$code ) );
        if ( strlen( $code ) !== self::RECOVERY_LENGTH )
            return '';
        if ( strspn( $code, self::RECOVERY_ALPHABET ) !== self::RECOVERY_LENGTH )
            return '';
        return $code;
    }

    /**
     * Does a typed code look like a recovery code rather than an app's digits?
     * @param string $code
     * @return bool
     */
    public static function looksLikeRecoveryCode( $code )
    {
        return self::normalizeRecoveryCode( $code ) !== '';
    }

    /**
     * The stored form of a set of recovery codes: a salt and the hash of each code.
     * @param string[] $codes
     * @return array salt, hashes
     */
    public static function hashRecoveryCodes( array $codes )
    {
        $salt = bin2hex( random_bytes( 16 ) );
        $hashes = array();
        foreach ( $codes as $code )
        {
            $normalized = self::normalizeRecoveryCode( $code );
            if ( $normalized !== '' )
                $hashes[] = hash_hmac( 'sha256', $normalized, $salt );
        }
        return array( 'salt' => $salt, 'hashes' => $hashes );
    }

    /**
     * Use a recovery code: the stored set without it, or false when it is not one of them.
     * @param array|null $stored salt, hashes
     * @param string $code as typed
     * @return array|false
     */
    public static function useRecoveryCode( $stored, $code )
    {
        if ( !is_array( $stored ) || empty( $stored['salt'] ) || empty( $stored['hashes'] ) || !is_array( $stored['hashes'] ) )
            return false;
        $normalized = self::normalizeRecoveryCode( $code );
        if ( $normalized === '' )
            return false;
        $hash = hash_hmac( 'sha256', $normalized, (string)$stored['salt'] );
        $found = false;
        $left = array();
        foreach ( $stored['hashes'] as $candidate )
        {
            if ( !$found && is_string( $candidate ) && hash_equals( $candidate, $hash ) )
            {
                $found = true;
                continue;
            }
            $left[] = $candidate;
        }
        if ( !$found )
            return false;
        return array( 'salt' => (string)$stored['salt'], 'hashes' => $left );
    }

    /**
     * How many recovery codes are left.
     * @param array|null $stored
     * @return int
     */
    public static function recoveryCodesLeft( $stored )
    {
        return is_array( $stored ) && isset( $stored['hashes'] ) && is_array( $stored['hashes'] ) ? count( $stored['hashes'] ) : 0;
    }
}
