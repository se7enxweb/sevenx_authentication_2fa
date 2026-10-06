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
  \class sevenxAuthentication2faTOTP sevenxauthentication2fatotp.php
  \ingroup sevenx_authentication_2fa
  \brief TOTP (RFC 6238) implementation without external dependencies.

  Generates Base32 secrets and time-based codes compatible with Google
  Authenticator, Authy, Microsoft Authenticator and any other RFC 6238 /
  RFC 4226 compliant application.

  Plain PHP: nothing of Exponential is needed, so the unit tests run it
  without a database or settings.
*/
class sevenxAuthentication2faTOTP
{
    /**
     * RFC 4648 Base32 alphabet.
     * @var string
     */
    private static $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * The algorithms an authenticator app understands.
     * @var array
     */
    private static $algorithms = array( 'SHA1', 'SHA256', 'SHA512' );

    /**
     * Generate a random Base32 encoded secret from $bytes bytes of entropy.
     * The default, 20 bytes (160 bits), is what RFC 4226 recommends; it gives
     * a 32 character secret.
     * @param int $bytes number of random bytes
     * @return string
     */
    public static function generateSecret( $bytes = 20 )
    {
        $bytes = max( 16, min( 64, (int)$bytes ) );
        return self::base32Encode( random_bytes( $bytes ) );
    }

    /**
     * The algorithm name if an authenticator app understands it, else SHA1.
     * @param string $algorithm
     * @return string
     */
    public static function algorithm( $algorithm )
    {
        $algorithm = strtoupper( (string)$algorithm );
        return in_array( $algorithm, self::$algorithms, true ) ? $algorithm : 'SHA1';
    }

    /**
     * Build the otpauth:// URI used by authenticator apps.
     * @param string $account user account identifier (usually e-mail or login)
     * @param string $secret Base32 secret
     * @param string $issuer organisation name
     * @param int $digits
     * @param int $period
     * @param string $algorithm SHA1 | SHA256 | SHA512
     * @return string
     */
    public static function provisioningUri( $account, $secret, $issuer = 'Exponential', $digits = 6, $period = 30, $algorithm = 'SHA1' )
    {
        $label = rawurlencode( $issuer ) . ':' . rawurlencode( $account );
        $query = 'secret=' . rawurlencode( $secret )
               . '&issuer=' . rawurlencode( $issuer )
               . '&digits=' . (int)$digits
               . '&period=' . (int)$period
               . '&algorithm=' . self::algorithm( $algorithm );
        return 'otpauth://totp/' . $label . '?' . $query;
    }

    /**
     * The time step (counter) a moment falls in.
     * @param int $time Unix timestamp
     * @param int $period
     * @return int
     */
    public static function timeStep( $time, $period = 30 )
    {
        $period = max( 1, (int)$period );
        return intdiv( (int)$time, $period );
    }

    /**
     * Generate a TOTP code for the given secret and time.
     * @param string $secret Base32 encoded secret
     * @param int|null $time Unix timestamp (null = now)
     * @param int $digits
     * @param int $period
     * @param string $algorithm SHA1 | SHA256 | SHA512
     * @return string
     */
    public static function code( $secret, $time = null, $digits = 6, $period = 30, $algorithm = 'SHA1' )
    {
        if ( $time === null )
            $time = time();
        return self::codeForStep( $secret, self::timeStep( $time, $period ), $digits, $algorithm );
    }

    /**
     * Generate the code of one time step (RFC 4226 HOTP with the step as counter).
     * @param string $secret Base32 encoded secret
     * @param int $step
     * @param int $digits
     * @param string $algorithm
     * @return string
     */
    public static function codeForStep( $secret, $step, $digits = 6, $algorithm = 'SHA1' )
    {
        $digits = max( 6, min( 8, (int)$digits ) );
        $step = max( 0, (int)$step );
        $counterBytes = pack( 'N', ( $step >> 32 ) & 0xFFFFFFFF ) . pack( 'N', $step & 0xFFFFFFFF );
        $hash = hash_hmac( strtolower( self::algorithm( $algorithm ) ), $counterBytes, self::base32Decode( $secret ), true );

        // Dynamic truncation: the offset is the low nibble of the LAST byte (19 for SHA1, 31 for SHA256, 63 for SHA512)
        $offset = ord( $hash[strlen( $hash ) - 1] ) & 0x0F;
        $binary = ( ( ord( $hash[$offset] ) & 0x7F ) << 24 ) |
                  ( ( ord( $hash[$offset + 1] ) & 0xFF ) << 16 ) |
                  ( ( ord( $hash[$offset + 2] ) & 0xFF ) << 8 ) |
                  ( ord( $hash[$offset + 3] ) & 0xFF );

        $otp = $binary % ( 10 ** $digits );
        return str_pad( (string)$otp, $digits, '0', STR_PAD_LEFT );
    }

    /**
     * Find the time step a user supplied code belongs to.
     *
     * Accepts the current step and $window steps before and after it (clock
     * drift). A step at or before $lastStep is refused: that code, or an
     * older one, was already used, so a code that was seen once (over a
     * shoulder, in a proxy log) cannot be used again.
     *
     * @param string $secret Base32 encoded secret
     * @param string $code user supplied code; spaces and dashes are ignored
     * @param int $window number of steps before/after the current one to accept (0 to 3)
     * @param int|null $time Unix timestamp (null = now)
     * @param int $digits
     * @param int $period
     * @param string $algorithm
     * @param int|null $lastStep the last step that was accepted for this secret
     * @return int|false the matched step, or false
     */
    public static function matchStep( $secret, $code, $window = 1, $time = null, $digits = 6, $period = 30, $algorithm = 'SHA1', $lastStep = null )
    {
        if ( $time === null )
            $time = time();
        if ( !is_string( $secret ) || $secret === '' || !is_scalar( $code ) )
            return false;

        $digits = max( 6, min( 8, (int)$digits ) );
        $code = preg_replace( '/[\s\-]/', '', (string)$code );
        if ( !preg_match( '/^[0-9]{' . $digits . '}$/', $code ) )
            return false;

        $window = max( 0, min( 3, (int)$window ) );
        $current = self::timeStep( $time, $period );
        $match = false;
        // Every candidate is computed and compared, so the time taken does not tell which step matched
        for ( $i = -$window; $i <= $window; $i++ )
        {
            $step = $current + $i;
            if ( $step < 0 )
                continue;
            if ( hash_equals( self::codeForStep( $secret, $step, $digits, $algorithm ), $code ) && $match === false )
                $match = $step;
        }

        if ( $match !== false && $lastStep !== null && $match <= (int)$lastStep )
            return false;
        return $match;
    }

    /**
     * Verify a user supplied TOTP code against the secret, accepting a window
     * of adjacent time slots to compensate for clock drift.
     * @param string $secret Base32 encoded secret
     * @param string $code user supplied code
     * @param int $window number of time steps before/after current to accept
     * @param int|null $time Unix timestamp (null = now)
     * @param int $digits
     * @param int $period
     * @param string $algorithm
     * @return bool
     */
    public static function verify( $secret, $code, $window = 1, $time = null, $digits = 6, $period = 30, $algorithm = 'SHA1' )
    {
        return self::matchStep( $secret, $code, $window, $time, $digits, $period, $algorithm ) !== false;
    }

    /**
     * Is the string a Base32 secret of at least 16 characters (80 bits)?
     * @param string $secret
     * @return bool
     */
    public static function isValidSecret( $secret )
    {
        return is_string( $secret ) && (bool)preg_match( '/^[A-Z2-7]{16,128}$/', $secret );
    }

    /**
     * The secret in groups of four characters, for reading it aloud or typing it.
     * @param string $secret
     * @return string
     */
    public static function groupSecret( $secret )
    {
        return trim( chunk_split( (string)$secret, 4, ' ' ) );
    }

    /**
     * Decode a Base32 string into binary.
     * @param string $input
     * @return string
     */
    public static function base32Decode( $input )
    {
        $input = strtoupper( (string)$input );
        $input = str_replace( array( '=', ' ' ), '', $input );
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        $length = strlen( $input );
        for ( $i = 0; $i < $length; $i++ )
        {
            $value = strpos( self::$base32Alphabet, $input[$i] );
            if ( $value === false )
                continue;

            $buffer = ( ( $buffer << 5 ) | $value ) & 0xFFFFFF;
            $bufferSize += 5;

            if ( $bufferSize >= 8 )
            {
                $bufferSize -= 8;
                $output .= chr( ( $buffer >> $bufferSize ) & 0xFF );
            }
        }
        return $output;
    }

    /**
     * Encode binary data into Base32 (no padding).
     * @param string $data
     * @return string
     */
    public static function base32Encode( $data )
    {
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        $length = strlen( $data );
        for ( $i = 0; $i < $length; $i++ )
        {
            $buffer = ( ( $buffer << 8 ) | ord( $data[$i] ) ) & 0xFFFFFF;
            $bufferSize += 8;

            while ( $bufferSize >= 5 )
            {
                $bufferSize -= 5;
                $output .= self::$base32Alphabet[ ( $buffer >> $bufferSize ) & 0x1F ];
            }
        }

        if ( $bufferSize > 0 )
        {
            $output .= self::$base32Alphabet[ ( $buffer << ( 5 - $bufferSize ) ) & 0x1F ];
        }
        return $output;
    }
}
