<?php
/**
 * sevenxAuthentication2faTOTP: the RFC 6238 test vectors, the time window,
 * the refusal of a code that was already used, and the secret format.
 */

use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    // RFC 6238 appendix B: the ASCII seeds, Base32 encoded
    const SEED_SHA1 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    const NOW = 1791331200; // 2026-10-06 00:00:00 UTC, a multiple of 30

    private function seed( $ascii )
    {
        return sevenxAuthentication2faTOTP::base32Encode( $ascii );
    }

    public function testRfc6238VectorsForEveryAlgorithm()
    {
        $sha1 = $this->seed( '12345678901234567890' );
        $sha256 = $this->seed( '12345678901234567890123456789012' );
        $sha512 = $this->seed( '1234567890123456789012345678901234567890123456789012345678901234' );
        $this->assertSame( self::SEED_SHA1, $sha1 );

        $vectors = array(
            59          => array( '94287082', '46119246', '90693936' ),
            1111111109  => array( '07081804', '68084774', '25091201' ),
            1234567890  => array( '89005924', '91819424', '93441116' ),
            2000000000  => array( '69279037', '90698825', '38618901' ),
            20000000000 => array( '65353130', '77737706', '47863826' ),
        );
        foreach ( $vectors as $time => $codes )
        {
            $this->assertSame( $codes[0], sevenxAuthentication2faTOTP::code( $sha1, $time, 8, 30, 'SHA1' ), "SHA1 at $time" );
            $this->assertSame( $codes[1], sevenxAuthentication2faTOTP::code( $sha256, $time, 8, 30, 'SHA256' ), "SHA256 at $time" );
            $this->assertSame( $codes[2], sevenxAuthentication2faTOTP::code( $sha512, $time, 8, 30, 'SHA512' ), "SHA512 at $time" );
        }
    }

    public function testSixDigitCodeIsTheLastSixDigits()
    {
        $this->assertSame( '287082', sevenxAuthentication2faTOTP::code( self::SEED_SHA1, 59, 6 ) );
    }

    public function testWindowAcceptsTheNeighbouringStepsOnly()
    {
        $secret = self::SEED_SHA1;
        $step = intdiv( self::NOW, 30 );
        foreach ( array( -1, 0, 1 ) as $offset )
        {
            $code = sevenxAuthentication2faTOTP::codeForStep( $secret, $step + $offset );
            $this->assertSame( $step + $offset, sevenxAuthentication2faTOTP::matchStep( $secret, $code, 1, self::NOW ), "offset $offset" );
        }
        foreach ( array( -2, 2 ) as $offset )
        {
            $code = sevenxAuthentication2faTOTP::codeForStep( $secret, $step + $offset );
            $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, $code, 1, self::NOW ), "offset $offset" );
        }
        // Window 0: the current step only
        $previous = sevenxAuthentication2faTOTP::codeForStep( $secret, $step - 1 );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, $previous, 0, self::NOW ) );
    }

    public function testWindowIsCappedAtThreeSteps()
    {
        $secret = self::SEED_SHA1;
        $step = intdiv( self::NOW, 30 );
        $code = sevenxAuthentication2faTOTP::codeForStep( $secret, $step - 5 );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, $code, 99, self::NOW ) );
    }

    public function testUsedCodeIsRefused()
    {
        $secret = self::SEED_SHA1;
        $step = intdiv( self::NOW, 30 );
        $code = sevenxAuthentication2faTOTP::codeForStep( $secret, $step );

        $this->assertSame( $step, sevenxAuthentication2faTOTP::matchStep( $secret, $code, 1, self::NOW, 6, 30, 'SHA1', null ) );
        // The same code again, after it signed in at $step
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, $code, 1, self::NOW, 6, 30, 'SHA1', $step ) );
        // An older code inside the window, after a newer one was used
        $older = sevenxAuthentication2faTOTP::codeForStep( $secret, $step - 1 );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, $older, 1, self::NOW, 6, 30, 'SHA1', $step ) );
        // The next step's code is still good
        $next = sevenxAuthentication2faTOTP::codeForStep( $secret, $step + 1 );
        $this->assertSame( $step + 1, sevenxAuthentication2faTOTP::matchStep( $secret, $next, 1, self::NOW, 6, 30, 'SHA1', $step ) );
    }

    public function testMalformedInputIsRefused()
    {
        $secret = self::SEED_SHA1;
        $code = sevenxAuthentication2faTOTP::code( $secret, self::NOW );
        $this->assertNotFalse( sevenxAuthentication2faTOTP::matchStep( $secret, substr( $code, 0, 3 ) . ' ' . substr( $code, 3 ), 1, self::NOW ) );
        $this->assertNotFalse( sevenxAuthentication2faTOTP::matchStep( $secret, substr( $code, 0, 3 ) . '-' . substr( $code, 3 ), 1, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, '', 1, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, '12345', 1, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, '1234567', 1, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, 'abcdef', 1, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( $secret, array( $code ), 1, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::matchStep( '', $code, 1, self::NOW ) );
    }

    public function testGeneratedSecretsAreLongRandomBase32()
    {
        $a = sevenxAuthentication2faTOTP::generateSecret();
        $b = sevenxAuthentication2faTOTP::generateSecret();
        $this->assertSame( 32, strlen( $a ) );
        $this->assertTrue( sevenxAuthentication2faTOTP::isValidSecret( $a ) );
        $this->assertNotSame( $a, $b );
        $this->assertSame( 20, strlen( sevenxAuthentication2faTOTP::base32Decode( $a ) ) );
        $this->assertSame( 'ABCD EFGH IJKL', sevenxAuthentication2faTOTP::groupSecret( 'ABCDEFGHIJKL' ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::isValidSecret( 'short' ) );
        $this->assertFalse( sevenxAuthentication2faTOTP::isValidSecret( 'ABCDEFGHIJKLMNO1' ) );
    }

    public function testProvisioningUriEscapesLabelAndNamesTheSettings()
    {
        $uri = sevenxAuthentication2faTOTP::provisioningUri( 'a b@example.invalid', 'ABCDEFGHIJKLMNOP', 'Fit & Healthy', 6, 30, 'sha256' );
        $this->assertSame( 'otpauth://totp/Fit%20%26%20Healthy:a%20b%40example.invalid?secret=ABCDEFGHIJKLMNOP&issuer=Fit%20%26%20Healthy&digits=6&period=30&algorithm=SHA256', $uri );
        $this->assertStringContainsString( 'algorithm=SHA1', sevenxAuthentication2faTOTP::provisioningUri( 'x', 'ABCDEFGHIJKLMNOP', 'I', 6, 30, 'md5' ) );
    }
}
