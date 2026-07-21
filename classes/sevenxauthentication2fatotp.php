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

// Polyfill for PHP < 5.6 where hash_equals() is unavailable.
if ( !function_exists( 'hash_equals' ) )
{
    function hash_equals( $knownString, $userString )
    {
        $knownLen = strlen( $knownString );
        $userLen = strlen( $userString );
        if ( $knownLen !== $userLen )
            return false;
        $result = 0;
        for ( $i = 0; $i < $knownLen; $i++ )
        {
            $result |= ord( $knownString[$i] ) ^ ord( $userString[$i] );
        }
        return $result === 0;
    }
}

/*!
  \class sevenxAuthentication2faTOTP sevenxauthentication2fatotp.php
  \ingroup sevenx_authentication_2fa
  \brief TOTP (RFC 6238) implementation without external dependencies.

  Generates Base32 secrets and 6-digit time-based codes compatible with
  Google Authenticator, Authy, Microsoft Authenticator and any other
  RFC 6238 / RFC 4226 compliant application.
*/
class sevenxAuthentication2faTOTP
{
    /**
     * RFC 4648 Base32 alphabet.
     * @var string
     */
    private static $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32 encoded secret.
     * @param int $length number of bytes of entropy
     * @return string
     */
    public static function generateSecret( $length = 20 )
    {
        $secret = '';
        for ( $i = 0; $i < $length; $i++ )
        {
            if ( function_exists( 'random_int' ) )
                $secret .= self::$base32Alphabet[random_int( 0, 31 )];
            else
                $secret .= self::$base32Alphabet[mt_rand( 0, 31 )];
        }
        return $secret;
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
        $label = rawurlencode( $issuer . ':' . $account );
        $issuerParam = rawurlencode( $issuer );
        $algorithm = strtoupper( $algorithm );
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&digits={$digits}&period={$period}&algorithm={$algorithm}";
    }

    /**
     * Generate a TOTP code for the given secret and time slot.
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

        $counter = floor( $time / $period );
        $secretBinary = self::base32Decode( $secret );
        $counterBytes = pack( 'N*', 0 ) . pack( 'N*', $counter );
        $hash = hash_hmac( strtolower( $algorithm ), $counterBytes, $secretBinary, true );

        $offset = ord( $hash[19] ) & 0x0F;
        $binary = ( ( ord( $hash[$offset] ) & 0x7F ) << 24 ) |
                  ( ( ord( $hash[$offset + 1] ) & 0xFF ) << 16 ) |
                  ( ( ord( $hash[$offset + 2] ) & 0xFF ) << 8 ) |
                  ( ord( $hash[$offset + 3] ) & 0xFF );

        $otp = $binary % pow( 10, $digits );
        return str_pad( (string)$otp, $digits, '0', STR_PAD_LEFT );
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
        if ( $time === null )
            $time = time();

        $code = preg_replace( '/[^0-9]/', '', $code );
        for ( $i = -$window; $i <= $window; $i++ )
        {
            $slot = $time + ( $i * $period );
            if ( hash_equals( self::code( $secret, $slot, $digits, $period, $algorithm ), $code ) )
                return true;
        }
        return false;
    }

    /**
     * Decode a Base32 string into binary.
     * @param string $input
     * @return string
     */
    public static function base32Decode( $input )
    {
        $input = strtoupper( $input );
        $input = str_replace( '=', '', $input );
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        for ( $i = 0; $i < strlen( $input ); $i++ )
        {
            $char = $input[$i];
            $value = strpos( self::$base32Alphabet, $char );
            if ( $value === false )
                continue;

            $buffer = ( $buffer << 5 ) | $value;
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
     * Encode binary data into Base32.
     * @param string $data
     * @return string
     */
    public static function base32Encode( $data )
    {
        $input = '';
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        for ( $i = 0; $i < strlen( $data ); $i++ )
        {
            $buffer = ( $buffer << 8 ) | ord( $data[$i] );
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
