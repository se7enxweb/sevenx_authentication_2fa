<?php
/**
 * sevenxAuthentication2faAttempts: wrong codes end a challenge after the
 * limit, a challenge ends after its time, e-mail codes can be asked for again
 * only after the interval and only so often, and e-mail codes are compared by
 * their salted hash.
 */

use PHPUnit\Framework\TestCase;

class AttemptsTest extends TestCase
{
    const NOW = 1791331200;

    private function pending( $extra = array() )
    {
        return array_merge( array( 'user_id' => 42, 'method' => 'totp', 'expires' => self::NOW + 300, 'attempts' => 0, 'resends' => 0, 'sent_at' => self::NOW ), $extra );
    }

    public function testFiveWrongCodesEndTheChallenge()
    {
        $p = $this->pending();
        for ( $i = 1; $i <= 4; $i++ )
        {
            $p = sevenxAuthentication2faAttempts::fail( $p );
            $this->assertTrue( sevenxAuthentication2faAttempts::isOpen( $p, 5, self::NOW ), "after $i" );
            $this->assertSame( 5 - $i, sevenxAuthentication2faAttempts::remaining( $p, 5 ) );
        }
        $p = sevenxAuthentication2faAttempts::fail( $p );
        $this->assertFalse( sevenxAuthentication2faAttempts::isOpen( $p, 5, self::NOW ) );
        $this->assertSame( 0, sevenxAuthentication2faAttempts::remaining( $p, 5 ) );
    }

    public function testChallengeEndsAfterItsTime()
    {
        $p = $this->pending();
        $this->assertTrue( sevenxAuthentication2faAttempts::isOpen( $p, 5, self::NOW + 300 ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::isOpen( $p, 5, self::NOW + 301 ) );
    }

    public function testNoChallengeIsNotOpen()
    {
        $this->assertFalse( sevenxAuthentication2faAttempts::isOpen( null, 5, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::isOpen( array(), 5, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::isOpen( $this->pending( array( 'user_id' => 0 ) ), 5, self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::isOpen( $this->pending( array( 'expires' => null ) ), 5, self::NOW ) );
    }

    public function testResendWaitsForTheIntervalAndStopsAtTheLimit()
    {
        $p = $this->pending( array( 'method' => 'email' ) );
        $this->assertSame( 60, sevenxAuthentication2faAttempts::resendWait( $p, 60, 3, self::NOW ) );
        $this->assertSame( 1, sevenxAuthentication2faAttempts::resendWait( $p, 60, 3, self::NOW + 59 ) );
        $this->assertSame( 0, sevenxAuthentication2faAttempts::resendWait( $p, 60, 3, self::NOW + 60 ) );

        $t = self::NOW;
        for ( $i = 1; $i <= 3; $i++ )
        {
            $t += 60;
            $this->assertSame( 0, sevenxAuthentication2faAttempts::resendWait( $p, 60, 3, $t ) );
            $p = sevenxAuthentication2faAttempts::resent( $p, $t );
        }
        $this->assertSame( -1, sevenxAuthentication2faAttempts::resendWait( $p, 60, 3, $t + 3600 ) );
    }

    public function testEmailCodeComparedByItsSaltedHash()
    {
        $salt = 'f00d';
        $p = $this->pending( array( 'method' => 'email', 'code_salt' => $salt, 'code_hash' => sevenxAuthentication2faAttempts::codeHash( '123456', $salt ) ) );
        $this->assertTrue( sevenxAuthentication2faAttempts::emailCodeMatches( $p, '123456' ) );
        $this->assertTrue( sevenxAuthentication2faAttempts::emailCodeMatches( $p, '123 456' ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::emailCodeMatches( $p, '123457' ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::emailCodeMatches( $p, '' ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::emailCodeMatches( $p, array( '123456' ) ) );
        $this->assertFalse( sevenxAuthentication2faAttempts::emailCodeMatches( $this->pending(), '123456' ) );
        // The session holds no code in plain text
        $this->assertStringNotContainsString( '123456', json_encode( $p ) );
    }
}
