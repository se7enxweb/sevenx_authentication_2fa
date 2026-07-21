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
  \class sevenxAuthentication2faQR sevenxauthentication2faqr.php
  \ingroup sevenx_authentication_2fa
  \brief Generates scannable QR codes for TOTP provisioning URIs.
*/
class sevenxAuthentication2faQR
{
    /**
     * Return a PNG QR code as a data URI.
     * @param string $text the data to encode (e.g. an otpauth:// URI)
     * @param int $size width and height in pixels
     * @return string data:image/png;base64,... URI
     */
    public static function pngDataUri( $text, $size = 200 )
    {
        $thirdParty = __DIR__ . '/thirdparty/psyonqrcode.php';
        if ( !file_exists( $thirdParty ) )
        {
            eZDebug::writeError( 'QR code generator file missing: ' . $thirdParty, 'sevenxAuthentication2faQR' );
            return '';
        }

        require_once( $thirdParty );

        $options = array(
            'w' => (int)$size,
            'h' => (int)$size,
            'p' => 0,
        );

        $qr = new sevenxPsyonQRCode( $text, $options );
        $image = $qr->render_image();

        ob_start();
        imagepng( $image );
        $png = ob_get_clean();

        if ( !$png )
            return '';

        return 'data:image/png;base64,' . base64_encode( $png );
    }
}
