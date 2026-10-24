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

    // ------------------------------------------------------------------------------------------------------------
    // Sign-in paths outside the web login form ([OtherSignInPaths], [AppPasswords]; sevenxAuthentication2faSignIn)
    // ------------------------------------------------------------------------------------------------------------

    /** The channels the kernel asks about (expSignInSecondFactor::CHANNEL_*) */
    const CHANNELS = array( 'rest', 'webdav', 'soap', 'expservices' );

    /**
     * The channels an app password may ever be made for: those that sign in one request at a time and never make a
     * session the web interface would accept. The expservices login makes such a session, so it takes the code of
     * the authenticator app instead.
     */
    const APP_PASSWORD_CHANNELS = array( 'rest', 'webdav', 'soap' );

    /**
     * The paths guarded when [OtherSignInPaths] Guard[] is not set: none. By default only the web login form asks for
     * the second step; REST, SOAP, WebDAV and the expservices login sign an enforced account in with its password as
     * before, until a site lists the path in Guard[].
     */
    const DEFAULT_GUARDED_CHANNELS = array();

    /**
     * The paths an app password can be made for and used on: those [AppPasswords] Channels[] allows that are also
     * guarded (on a path that is not guarded the password alone signs in, and the handler is never asked there).
     *
     * @param string[] $appChannels [AppPasswords] Channels[]
     * @param string[] $guarded the guarded paths
     * @return string[]
     */
    public static function appPasswordChoices( array $appChannels, array $guarded )
    {
        return array_values( array_intersect( self::channelList( $appChannels, self::APP_PASSWORD_CHANNELS ), $guarded ) );
    }

    const REASON_REQUIRED = 'second_factor_required';
    const REASON_INVALID = 'second_factor_invalid';
    const REASON_SETUP = 'second_factor_setup_required';

    /** Characters of an app password: lower case, no 0/o, 1/i/l (read from a screen and typed into a client) */
    const APP_PASSWORD_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Length of an app password without its dashes (24 characters of 31: about 119 bits) */
    const APP_PASSWORD_LENGTH = 24;

    /** Names are cut to this length */
    const APP_PASSWORD_NAME_LENGTH = 60;

    /**
     * Whether a password that was right is enough on a path outside the web login form.
     *
     * @param bool $guarded the path is guarded ([OtherSignInPaths] Guard[])
     * @param bool $enforced Enforce2FA applies to the account (EnforcedGroups[], ExemptGroups[], ExemptEmailDomains[])
     * @param bool $optedIn the account set up a second step of its own and GuardOptedIn is enabled
     * @param string $method the account's working second step: totp, email or disabled
     * @param bool $codeAccepted the path takes the code of an authenticator app (CodeChannels[])
     * @param string|null $code the code the request carries
     * @param bool|null $codeMatches whether that code was right (null: not checked)
     * @return string|null null: allowed; else the reason of the refusal
     */
    public static function otherPathDecision( $guarded, $enforced, $optedIn, $method, $codeAccepted, $code, $codeMatches )
    {
        if ( !$guarded || ( !$enforced && !$optedIn ) )
            return null;
        if ( !in_array( $method, array( 'totp', 'email' ), true ) )
            return self::REASON_SETUP;
        if ( $code === null || trim( (string)$code ) === '' )
            return self::REASON_REQUIRED;
        if ( !$codeAccepted || $method !== 'totp' )
            return self::REASON_INVALID;
        return $codeMatches === true ? null : self::REASON_INVALID;
    }

    /**
     * Whether wrong codes sent on the paths outside the web login form keep the code locked now: $max wrong codes
     * within $lockSeconds of the first one lock it until $lockSeconds after that first one. (The account's own count
     * of failed sign-ins cannot do this here: each right password before a code resets it.)
     *
     * @param array|null $failures count, since
     * @param int $max
     * @param int $lockSeconds
     * @param int $now
     * @return bool
     */
    public static function codeLocked( $failures, $max, $lockSeconds, $now )
    {
        if ( !is_array( $failures ) || !isset( $failures['count'], $failures['since'] ) )
            return false;
        if ( (int)$now - (int)$failures['since'] >= (int)$lockSeconds )
            return false;
        return (int)$failures['count'] >= max( 1, (int)$max );
    }

    /**
     * The failures after one more wrong code: a new count when the last series is over.
     *
     * @param array|null $failures
     * @param int $lockSeconds
     * @param int $now
     * @return array count, since
     */
    public static function codeFailed( $failures, $lockSeconds, $now )
    {
        if ( !is_array( $failures ) || !isset( $failures['count'], $failures['since'] ) || (int)$now - (int)$failures['since'] >= (int)$lockSeconds )
            return array( 'count' => 1, 'since' => (int)$now );
        return array( 'count' => (int)$failures['count'] + 1, 'since' => (int)$failures['since'] );
    }

    /**
     * The channels of a setting, known ones only, in a fixed order.
     *
     * @param array $values
     * @param array $known
     * @return string[]
     */
    public static function channelList( array $values, array $known = self::CHANNELS )
    {
        $values = array_map( function ( $v ) { return strtolower( trim( (string)$v ) ); }, $values );
        return array_values( array_intersect( $known, $values ) );
    }

    /**
     * A new app password: the password to show once (groups of four, e.g. abcd-efgh-jkmn-pqrs-tuvw-xyz2) and the
     * entry to store, which keeps only its salted hash.
     *
     * @param string $name
     * @param string[] $channels
     * @param int $now
     * @param int $expires 0: until revoked
     * @return array password, entry (id, name, channels, salt, hash, created, expires, last_used, last_ip)
     */
    public static function newAppPassword( $name, array $channels, $now, $expires = 0 )
    {
        $alphabet = self::APP_PASSWORD_ALPHABET;
        $max = strlen( $alphabet ) - 1;
        $plain = '';
        for ( $i = 0; $i < self::APP_PASSWORD_LENGTH; $i++ )
            $plain .= $alphabet[random_int( 0, $max )];
        $salt = bin2hex( random_bytes( 16 ) );
        return array(
            'password' => implode( '-', str_split( $plain, 4 ) ),
            'entry' => array(
                'id' => bin2hex( random_bytes( 6 ) ),
                'name' => self::cleanAppPasswordName( $name ),
                'channels' => self::channelList( $channels, self::APP_PASSWORD_CHANNELS ),
                'salt' => $salt,
                'hash' => self::hashAppPassword( $plain, $salt ),
                'created' => (int)$now,
                'expires' => max( 0, (int)$expires ),
                'last_used' => 0,
                'last_ip' => '',
            ),
        );
    }

    /**
     * A name for an app password: one line, at most APP_PASSWORD_NAME_LENGTH characters, never empty.
     *
     * @param string $name
     * @return string
     */
    public static function cleanAppPasswordName( $name )
    {
        $name = trim( preg_replace( '/\s+/u', ' ', (string)$name ) );
        if ( function_exists( 'mb_substr' ) )
            $name = mb_substr( $name, 0, self::APP_PASSWORD_NAME_LENGTH, 'UTF-8' );
        else
            $name = substr( $name, 0, self::APP_PASSWORD_NAME_LENGTH );
        return $name === '' ? 'App password' : $name;
    }

    /**
     * What was typed, in the stored form: lower case without spaces and dashes; '' when it cannot be an app password.
     *
     * @param string $secret
     * @return string
     */
    public static function normalizeAppPassword( $secret )
    {
        if ( !is_string( $secret ) || strlen( $secret ) > 64 )
            return '';
        $plain = strtolower( preg_replace( '/[\s\-]+/', '', $secret ) );
        if ( strlen( $plain ) !== self::APP_PASSWORD_LENGTH || strspn( $plain, self::APP_PASSWORD_ALPHABET ) !== self::APP_PASSWORD_LENGTH )
            return '';
        return $plain;
    }

    /**
     * @param string $plain normalized
     * @param string $salt
     * @return string HMAC-SHA-256 as hex (the password is 119 random bits: a fast hash is right, as for API keys)
     */
    public static function hashAppPassword( $plain, $salt )
    {
        return hash_hmac( 'sha256', (string)$plain, (string)$salt );
    }

    /**
     * Whether an app password may be used now: not expired.
     *
     * @param array $entry
     * @param int $now
     * @return bool
     */
    public static function appPasswordActive( array $entry, $now )
    {
        return empty( $entry['expires'] ) || (int)$entry['expires'] > (int)$now;
    }

    /**
     * The index of the stored app password a secret is, when it may be used on the channel now; false otherwise.
     * Every entry's hash is computed and compared (hash_equals()), so the time does not tell which one matched.
     *
     * @param array[] $entries
     * @param string $secret as sent
     * @param string $channel
     * @param int $now
     * @return int|false
     */
    public static function findAppPassword( array $entries, $secret, $channel, $now )
    {
        $plain = self::normalizeAppPassword( $secret );
        if ( $plain === '' )
            return false;
        $found = false;
        foreach ( array_values( $entries ) as $i => $entry )
        {
            if ( !isset( $entry['salt'], $entry['hash'] ) || !is_string( $entry['hash'] ) )
                continue;
            $matches = hash_equals( $entry['hash'], self::hashAppPassword( $plain, $entry['salt'] ) );
            if ( $matches && $found === false && in_array( $channel, (array)$entry['channels'], true )
                 && in_array( $channel, self::APP_PASSWORD_CHANNELS, true ) && self::appPasswordActive( $entry, $now ) )
                $found = $i;
        }
        return $found;
    }

    /**
     * The entries without the one of an id.
     *
     * @param array[] $entries
     * @param string $id
     * @return array[]|false false when no entry has the id
     */
    public static function removeAppPassword( array $entries, $id )
    {
        $left = array();
        $removed = false;
        foreach ( $entries as $entry )
        {
            if ( isset( $entry['id'] ) && is_string( $id ) && $id !== '' && hash_equals( (string)$entry['id'], $id ) )
            {
                $removed = true;
                continue;
            }
            $left[] = $entry;
        }
        return $removed ? $left : false;
    }

    /**
     * Stored entries as they may be kept: each with an id, a salt, a 64 character hash and the known fields.
     *
     * @param array $entries
     * @return array[]
     */
    public static function cleanAppPasswordEntries( array $entries )
    {
        $clean = array();
        foreach ( $entries as $entry )
        {
            if ( !is_array( $entry ) || !isset( $entry['id'], $entry['salt'], $entry['hash'] ) || !is_string( $entry['hash'] ) || strlen( $entry['hash'] ) !== 64 )
                continue;
            $clean[] = array(
                'id' => (string)$entry['id'],
                'name' => self::cleanAppPasswordName( isset( $entry['name'] ) ? $entry['name'] : '' ),
                'channels' => self::channelList( isset( $entry['channels'] ) ? (array)$entry['channels'] : array(), self::APP_PASSWORD_CHANNELS ),
                'salt' => (string)$entry['salt'],
                'hash' => $entry['hash'],
                'created' => isset( $entry['created'] ) ? (int)$entry['created'] : 0,
                'expires' => isset( $entry['expires'] ) ? max( 0, (int)$entry['expires'] ) : 0,
                'last_used' => isset( $entry['last_used'] ) ? (int)$entry['last_used'] : 0,
                'last_ip' => isset( $entry['last_ip'] ) ? substr( (string)$entry['last_ip'], 0, 64 ) : '',
            );
        }
        return $clean;
    }

    /**
     * What a template and the audit may see of an entry: never the salt or the hash.
     *
     * @param array $entry
     * @param int $now
     * @return array
     */
    public static function describeAppPassword( array $entry, $now )
    {
        return array(
            'id' => (string)$entry['id'],
            'name' => (string)$entry['name'],
            'channels' => array_values( (array)$entry['channels'] ),
            'created' => (int)$entry['created'],
            'expires' => (int)$entry['expires'],
            'last_used' => (int)$entry['last_used'],
            'expired' => !self::appPasswordActive( $entry, $now ),
        );
    }
}
