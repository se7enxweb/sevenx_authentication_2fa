<?php
/**
 * How a configuration is stored: the TOTP secret is encrypted when a key is
 * set, stored values without a key keep working, a wrong key reads nothing,
 * the last used step is kept, and a template never sees a confirmed secret.
 */

use PHPUnit\Framework\TestCase;

class SecretStorageTest extends TestCase
{
    const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function tearDown(): void
    {
        sevenxAuthentication2faCrypto::setKey( '' );
    }

    public function testWithoutKeyTheSecretIsStoredAsBefore()
    {
        sevenxAuthentication2faCrypto::setKey( '' );
        $json = ( new sevenxAuthentication2fa( 'totp', self::SECRET, true, 1, 7 ) )->toJson();
        $this->assertStringContainsString( self::SECRET, $json );
        $back = sevenxAuthentication2fa::fromJson( $json );
        $this->assertSame( self::SECRET, $back->secret() );
        $this->assertSame( 7, $back->lastStep() );
    }

    public function testWithKeyTheSecretIsEncryptedAndReadBack()
    {
        sevenxAuthentication2faCrypto::setKey( 'a test key that is long enough' );
        $json = ( new sevenxAuthentication2fa( 'totp', self::SECRET, true ) )->toJson();
        $this->assertStringNotContainsString( self::SECRET, $json );
        $this->assertStringContainsString( '"enc1:', $json );
        $this->assertSame( self::SECRET, sevenxAuthentication2fa::fromJson( $json )->secret() );

        // Every encryption uses a new nonce
        $this->assertNotSame( sevenxAuthentication2faCrypto::encrypt( self::SECRET ), sevenxAuthentication2faCrypto::encrypt( self::SECRET ) );
    }

    public function testPlainValueStoredBeforeTheKeyStillWorks()
    {
        sevenxAuthentication2faCrypto::setKey( '' );
        $json = ( new sevenxAuthentication2fa( 'totp', self::SECRET, true ) )->toJson();
        sevenxAuthentication2faCrypto::setKey( 'a key set later' );
        $data = sevenxAuthentication2fa::fromJson( $json );
        $this->assertSame( self::SECRET, $data->secret() );
        $this->assertStringNotContainsString( self::SECRET, $data->toJson() );
    }

    public function testWrongKeyOrChangedDataReadsNothing()
    {
        sevenxAuthentication2faCrypto::setKey( 'key one' );
        $stored = sevenxAuthentication2faCrypto::encrypt( self::SECRET );
        sevenxAuthentication2faCrypto::setKey( 'key two' );
        $this->assertSame( '', sevenxAuthentication2faCrypto::decrypt( $stored ) );
        sevenxAuthentication2faCrypto::setKey( 'key one' );
        $changed = substr( $stored, 0, -2 ) . ( substr( $stored, -2 ) === 'AA' ? 'AB' : 'AA' );
        $this->assertSame( '', sevenxAuthentication2faCrypto::decrypt( $changed ) );
        sevenxAuthentication2faCrypto::setKey( '' );
        $this->assertSame( '', sevenxAuthentication2faCrypto::decrypt( $stored ) );
    }

    public function testTemplatesSeeTheSecretOfAnEnrolmentOnly()
    {
        $confirmed = new sevenxAuthentication2fa( 'totp', self::SECRET, true );
        $this->assertSame( '', $confirmed->attribute( 'secret' ) );
        $this->assertSame( '', $confirmed->attribute( 'enrolment_secret' ) );
        $this->assertTrue( $confirmed->attribute( 'is_active' ) );
        $this->assertTrue( $confirmed->attribute( 'has_secret' ) );

        $enrolling = new sevenxAuthentication2fa( 'totp', self::SECRET, false );
        $this->assertSame( self::SECRET, $enrolling->attribute( 'enrolment_secret' ) );
        $this->assertTrue( $enrolling->attribute( 'is_enrolling' ) );
        $this->assertFalse( $enrolling->attribute( 'is_active' ) );
    }

    public function testUnknownMethodIsDisabled()
    {
        $data = sevenxAuthentication2fa::fromJson( '{"method":"sms","secret":"","verified":true}' );
        $this->assertSame( 'disabled', $data->method() );
        $this->assertFalse( $data->isActive() );
        $this->assertSame( 'disabled', sevenxAuthentication2fa::fromJson( 'not json' )->method() );
    }
}
