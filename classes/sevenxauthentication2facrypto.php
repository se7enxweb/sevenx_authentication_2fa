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
  \class sevenxAuthentication2faCrypto sevenxauthentication2facrypto.php
  \ingroup sevenx_authentication_2fa
  \brief Encrypts TOTP secrets at rest when a key is configured.

  A TOTP secret has to be readable by the server, so it cannot be hashed like
  a password. When sevenxauthentication2fa.ini [TOTPSettings] SecretKey (or
  the environment variable SEVENX_2FA_SECRET_KEY) holds a key, secrets are
  stored as "enc1:" + base64( nonce . tag . ciphertext ), AES-256-GCM with a
  key derived from it by SHA-256. A secret stored without a key keeps
  working and is encrypted the next time it is stored. Without a key the
  secret is stored as before, in plain text in the user object's attribute.

  Plain PHP: the key can be set directly, so the unit tests need no settings.
*/
class sevenxAuthentication2faCrypto
{
    const PREFIX = 'enc1:';
    const CIPHER = 'aes-256-gcm';

    /**
     * The key set by a test or read from the settings; null when not read yet.
     * @var string|null
     */
    private static $key = null;

    /**
     * Set the key (an empty string means: store in plain text). For tests and
     * for scripts that know the key from elsewhere.
     * @param string|null $key null forgets it, so the settings are read again
     */
    public static function setKey( $key )
    {
        self::$key = $key === null ? null : (string)$key;
    }

    /**
     * The configured key, '' when there is none.
     * @return string
     */
    public static function key()
    {
        if ( self::$key === null )
        {
            $key = getenv( 'SEVENX_2FA_SECRET_KEY' );
            if ( !is_string( $key ) || $key === '' )
            {
                $key = '';
                if ( class_exists( 'eZINI' ) )
                {
                    $ini = eZINI::instance( 'sevenxauthentication2fa.ini' );
                    if ( $ini->hasVariable( 'TOTPSettings', 'SecretKey' ) )
                        $key = trim( (string)$ini->variable( 'TOTPSettings', 'SecretKey' ) );
                }
            }
            self::$key = $key;
        }
        return self::$key;
    }

    /**
     * Can secrets be encrypted here (a key and the cipher)?
     * @return bool
     */
    public static function isAvailable()
    {
        return self::key() !== '' && function_exists( 'openssl_encrypt' )
            && in_array( self::CIPHER, openssl_get_cipher_methods(), true );
    }

    /**
     * Is the stored value an encrypted one?
     * @param string $stored
     * @return bool
     */
    public static function isEncrypted( $stored )
    {
        return is_string( $stored ) && strncmp( $stored, self::PREFIX, strlen( self::PREFIX ) ) === 0;
    }

    /**
     * The value to store for a secret: encrypted when a key is configured.
     * @param string $secret
     * @return string
     */
    public static function encrypt( $secret )
    {
        $secret = (string)$secret;
        if ( $secret === '' || self::isEncrypted( $secret ) || !self::isAvailable() )
            return $secret;

        $nonce = random_bytes( 12 );
        $tag = '';
        $cipher = openssl_encrypt( $secret, self::CIPHER, hash( 'sha256', self::key(), true ), OPENSSL_RAW_DATA, $nonce, $tag, 'sevenx2fa', 16 );
        if ( $cipher === false )
            return $secret;
        return self::PREFIX . base64_encode( $nonce . $tag . $cipher );
    }

    /**
     * The secret of a stored value; '' when it is encrypted and cannot be read
     * (no key, another key, or changed data).
     * @param string $stored
     * @return string
     */
    public static function decrypt( $stored )
    {
        $stored = (string)$stored;
        if ( !self::isEncrypted( $stored ) )
            return $stored;
        if ( self::key() === '' || !function_exists( 'openssl_decrypt' ) )
            return '';

        $raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
        if ( $raw === false || strlen( $raw ) < 29 )
            return '';
        $plain = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, hash( 'sha256', self::key(), true ), OPENSSL_RAW_DATA,
                                  substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), 'sevenx2fa' );
        return $plain === false ? '' : $plain;
    }
}
