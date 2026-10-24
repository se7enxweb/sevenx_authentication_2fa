<?php
/**
 * sevenxAuthentication2faPolicy: whom Enforce2FA applies to (every user, or the members of the enforced groups,
 * less the exempt groups and e-mail domains), how long a grace period lasts, and recovery codes (made, stored only
 * as hashes, each usable once, kept through the stored JSON).
 */

use PHPUnit\Framework\TestCase;

class PolicyTest extends TestCase
{
    const NOW = 1791331200;
    const ADMINS = 12;
    const EDITORS = 13;
    const MEMBERS = 11;
    const USERS = 4;
    const TESTS = 900;

    public function testOffAppliesToNobody()
    {
        $this->assertFalse( sevenxAuthentication2faPolicy::applies( false, array(), array(), array(), array( self::USERS, self::ADMINS ), 'a@example.org' ) );
    }

    public function testWithoutGroupsItAppliesToEveryUserAsBefore()
    {
        $this->assertTrue( sevenxAuthentication2faPolicy::applies( true, array(), array(), array(), array( self::USERS, self::MEMBERS ), 'm@example.org' ) );
    }

    public function testWithGroupsOnlyTheirMembers()
    {
        $groups = array( self::ADMINS, self::EDITORS );
        $this->assertTrue( sevenxAuthentication2faPolicy::applies( true, $groups, array(), array(), array( self::USERS, self::ADMINS ), 'a@example.org' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::applies( true, $groups, array(), array(), array( self::USERS, self::EDITORS, 77 ), 'e@example.org' ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::applies( true, $groups, array(), array(), array( self::USERS, self::MEMBERS ), 'm@example.org' ) );
    }

    public function testGroupsThatResolveToNothingApplyToNobody()
    {
        // EnforcedGroups[] was set, but no value named an existing group: not "every user"
        $this->assertFalse( sevenxAuthentication2faPolicy::applies( true, array(), array(), array(), array( self::USERS, self::ADMINS ), 'a@example.org', true ) );
    }

    public function testExemptGroupWinsOverEnforcedGroup()
    {
        $this->assertFalse( sevenxAuthentication2faPolicy::applies( true, array( self::ADMINS ), array( self::TESTS ), array(), array( self::USERS, self::ADMINS, self::TESTS ), 'a@example.org' ) );
    }

    public function testExemptEmailDomainIsExactAndCaseBlind()
    {
        $domains = array( 'example.invalid' );
        $this->assertFalse( sevenxAuthentication2faPolicy::applies( true, array( self::ADMINS ), array(), $domains, array( self::ADMINS ), 'bot@Example.Invalid' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::applies( true, array( self::ADMINS ), array(), $domains, array( self::ADMINS ), 'bot@sub.example.invalid' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::applies( true, array( self::ADMINS ), array(), $domains, array( self::ADMINS ), 'bot@example.invalid.attacker.org' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::applies( true, array( self::ADMINS ), array(), $domains, array( self::ADMINS ), 'example.invalid' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::emailInDomains( 'x@example.invalid', array( '@example.invalid.' ) ) );
    }

    public function testGracePeriod()
    {
        $this->assertSame( 0, sevenxAuthentication2faPolicy::graceLeft( 0, null, self::NOW ) );
        $this->assertSame( 7 * 86400, sevenxAuthentication2faPolicy::graceLeft( 7, null, self::NOW ) );
        $this->assertSame( 86400, sevenxAuthentication2faPolicy::graceLeft( 7, self::NOW - 6 * 86400, self::NOW ) );
        $this->assertSame( 0, sevenxAuthentication2faPolicy::graceLeft( 7, self::NOW - 7 * 86400, self::NOW ) );
        $this->assertSame( 0, sevenxAuthentication2faPolicy::graceLeft( 7, self::NOW - 30 * 86400, self::NOW ) );
    }

    public function testRecoveryCodesAreDistinctAndReadable()
    {
        $codes = sevenxAuthentication2faPolicy::newRecoveryCodes( 10 );
        $this->assertCount( 10, $codes );
        $this->assertCount( 10, array_unique( $codes ) );
        foreach ( $codes as $code )
            $this->assertMatchesRegularExpression( '/^[A-HJKMNP-Z2-9]{5}-[A-HJKMNP-Z2-9]{5}$/', $code );
        $this->assertCount( 20, sevenxAuthentication2faPolicy::newRecoveryCodes( 50 ) );
    }

    public function testRecoveryCodeIsNotMistakenForAnAppCode()
    {
        $this->assertFalse( sevenxAuthentication2faPolicy::looksLikeRecoveryCode( '123456' ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::looksLikeRecoveryCode( '123 456' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::looksLikeRecoveryCode( 'abcde-fghjk' ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::looksLikeRecoveryCode( ' ABCDE FGHJK ' ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::looksLikeRecoveryCode( 'ABCDE-FGHJ0' ) );
    }

    public function testEachRecoveryCodeWorksOnceAndOnlyHashesAreStored()
    {
        $codes = sevenxAuthentication2faPolicy::newRecoveryCodes( 3 );
        $stored = sevenxAuthentication2faPolicy::hashRecoveryCodes( $codes );
        foreach ( $codes as $code )
            $this->assertStringNotContainsString( str_replace( '-', '', $code ), json_encode( $stored ) );

        $this->assertFalse( sevenxAuthentication2faPolicy::useRecoveryCode( $stored, 'ZZZZZ-ZZZZZ' ) );
        $left = sevenxAuthentication2faPolicy::useRecoveryCode( $stored, strtolower( $codes[1] ) );
        $this->assertIsArray( $left );
        $this->assertSame( 2, sevenxAuthentication2faPolicy::recoveryCodesLeft( $left ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::useRecoveryCode( $left, $codes[1] ) );
        $this->assertIsArray( sevenxAuthentication2faPolicy::useRecoveryCode( $left, $codes[0] ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::useRecoveryCode( null, $codes[0] ) );
    }

    public function testRecoveryCodesAndGraceStartSurviveTheStoredJson()
    {
        $codes = sevenxAuthentication2faPolicy::newRecoveryCodes( 2 );
        $value = new sevenxAuthentication2fa( 'totp', 'JBSWY3DPEHPK3PXP', true );
        $value->setRecovery( sevenxAuthentication2faPolicy::hashRecoveryCodes( $codes ) );
        $value->setSetupAskedAt( self::NOW );
        $back = sevenxAuthentication2fa::fromJson( $value->toJson() );
        $this->assertSame( 2, $back->attribute( 'recovery_codes_left' ) );
        $this->assertSame( self::NOW, $back->setupAskedAt() );
        $this->assertIsArray( sevenxAuthentication2faPolicy::useRecoveryCode( $back->recovery(), $codes[0] ) );

        $plain = sevenxAuthentication2fa::fromJson( ( new sevenxAuthentication2fa() )->toJson() );
        $this->assertNull( $plain->recovery() );
        $this->assertNull( $plain->setupAskedAt() );
    }
}
