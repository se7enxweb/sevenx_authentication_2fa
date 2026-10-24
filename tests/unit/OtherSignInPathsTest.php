<?php
/**
 * The sign-in paths outside the web login form (REST with basic authentication, WebDAV, SOAP, the expservices login):
 * when a password alone is refused (sevenxAuthentication2faPolicy::otherPathDecision()), the lock of authenticator
 * codes sent there, app passwords (made, stored only as a salted hash, found only on the channels they were made
 * for, expired, revoked, kept through the stored JSON), and the sentences a client gets
 * (sevenxAuthentication2faSignIn::message()). No database: plain values.
 */

use PHPUnit\Framework\TestCase;

class OtherSignInPathsTest extends TestCase
{
    const NOW = 1791331200;

    // ------------------------------------------------------------------ the decision

    public function testAnAccountEnforce2FADoesNotApplyToIsNotTouched()
    {
        // not enforced, no second step of its own (or GuardOptedIn off): the password alone signs in as before
        $this->assertNull( sevenxAuthentication2faPolicy::otherPathDecision( true, false, false, 'disabled', false, null, null ) );
        $this->assertNull( sevenxAuthentication2faPolicy::otherPathDecision( true, false, false, 'totp', true, null, null ) );
    }

    public function testAPathThatIsNotGuardedIsNotTouched()
    {
        $this->assertNull( sevenxAuthentication2faPolicy::otherPathDecision( false, true, false, 'totp', false, null, null ) );
    }

    public function testAnEnforcedAccountWithAPasswordAloneIsRefused()
    {
        $this->assertSame( 'second_factor_required', sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'totp', false, null, null ) );
        $this->assertSame( 'second_factor_required', sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'email', true, '', null ) );
    }

    public function testAnEnforcedAccountWithoutASecondStepMustSetItUpFirst()
    {
        $this->assertSame( 'second_factor_setup_required', sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'disabled', true, '123456', null ) );
    }

    public function testARightCodeCountsOnlyWhereCodesAreAccepted()
    {
        $this->assertNull( sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'totp', true, '123456', true ) );
        $this->assertSame( 'second_factor_invalid', sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'totp', false, '123456', null ) );
        $this->assertSame( 'second_factor_invalid', sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'totp', true, '123456', false ) );
        // e-mail codes cannot be sent to a client that has no page: a code there is never right
        $this->assertSame( 'second_factor_invalid', sevenxAuthentication2faPolicy::otherPathDecision( true, true, false, 'email', true, '123456', true ) );
    }

    public function testAnAccountThatChoseASecondStepIsGuardedOnlyWhenAskedFor()
    {
        $this->assertSame( 'second_factor_required', sevenxAuthentication2faPolicy::otherPathDecision( true, false, true, 'totp', false, null, null ) );
    }

    public function testWrongCodesLockTheCodeForAWhile()
    {
        $failures = null;
        for ( $i = 0; $i < 5; $i++ )
        {
            $this->assertFalse( sevenxAuthentication2faPolicy::codeLocked( $failures, 5, 900, self::NOW + $i ) );
            $failures = sevenxAuthentication2faPolicy::codeFailed( $failures, 900, self::NOW + $i );
        }
        $this->assertSame( array( 'count' => 5, 'since' => self::NOW ), $failures );
        $this->assertTrue( sevenxAuthentication2faPolicy::codeLocked( $failures, 5, 900, self::NOW + 10 ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::codeLocked( $failures, 5, 900, self::NOW + 900 ), 'the lock ends' );
        $this->assertSame( array( 'count' => 1, 'since' => self::NOW + 901 ), sevenxAuthentication2faPolicy::codeFailed( $failures, 900, self::NOW + 901 ),
                           'a new series starts after the lock' );
    }

    public function testChannelListsKeepKnownChannelsOnly()
    {
        $this->assertSame( array( 'rest', 'webdav' ), sevenxAuthentication2faPolicy::channelList( array( ' WebDAV', 'rest', 'ftp' ) ) );
        // an app password never goes to the expservices login, which makes a session
        $this->assertSame( array( 'soap' ), sevenxAuthentication2faPolicy::channelList( array( 'expservices', 'soap' ), sevenxAuthentication2faPolicy::APP_PASSWORD_CHANNELS ) );
    }

    // ------------------------------------------------------------------ app passwords

    public function testANewAppPasswordIsShownOnceAndStoredOnlyAsAHash()
    {
        $made = sevenxAuthentication2faPolicy::newAppPassword( '  Laptop   WebDAV  ', array( 'webdav', 'expservices' ), self::NOW, self::NOW + 86400 );
        $this->assertMatchesRegularExpression( '/^[a-z2-9]{4}(-[a-z2-9]{4}){5}$/', $made['password'] );
        $entry = $made['entry'];
        $this->assertSame( 'Laptop WebDAV', $entry['name'] );
        $this->assertSame( array( 'webdav' ), $entry['channels'] );
        $this->assertSame( 64, strlen( $entry['hash'] ) );
        $plain = str_replace( '-', '', $made['password'] );
        $this->assertStringNotContainsString( $plain, json_encode( $entry ), 'the password itself is never stored' );
        $this->assertSame( sevenxAuthentication2faPolicy::hashAppPassword( $plain, $entry['salt'] ), $entry['hash'] );
        $other = sevenxAuthentication2faPolicy::newAppPassword( 'x', array( 'rest' ), self::NOW );
        $this->assertNotSame( $made['password'], $other['password'] );
        $this->assertNotSame( $entry['salt'], $other['entry']['salt'] );
        $this->assertSame( 0, $other['entry']['expires'] );
    }

    public function testAnAppPasswordIsTypedWithOrWithoutDashesAndInAnyCase()
    {
        $this->assertSame( 'abcdefghjkmnpqrstuvwxyz2', sevenxAuthentication2faPolicy::normalizeAppPassword( 'ABCD-efgh jkmn-pqrs-tuvw-xyz2' ) );
        $this->assertSame( '', sevenxAuthentication2faPolicy::normalizeAppPassword( 'publishing$' ), 'an ordinary password is not one' );
        $this->assertSame( '', sevenxAuthentication2faPolicy::normalizeAppPassword( 'abcd-efgh-jkmn-pqrs-tuvw-xyz0' ), '0 is not in the alphabet' );
        $this->assertSame( '', sevenxAuthentication2faPolicy::normalizeAppPassword( array() ) );
    }

    public function testAnAppPasswordSignsInOnlyWhereItWasMadeFor()
    {
        $made = sevenxAuthentication2faPolicy::newAppPassword( 'drive', array( 'webdav' ), self::NOW );
        $second = sevenxAuthentication2faPolicy::newAppPassword( 'script', array( 'rest', 'soap' ), self::NOW );
        $entries = array( $made['entry'], $second['entry'] );
        $this->assertSame( 0, sevenxAuthentication2faPolicy::findAppPassword( $entries, $made['password'], 'webdav', self::NOW ) );
        $this->assertSame( 0, sevenxAuthentication2faPolicy::findAppPassword( $entries, strtoupper( $made['password'] ), 'webdav', self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::findAppPassword( $entries, $made['password'], 'rest', self::NOW ) );
        $this->assertSame( 1, sevenxAuthentication2faPolicy::findAppPassword( $entries, $second['password'], 'soap', self::NOW ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::findAppPassword( $entries, $second['password'], 'expservices', self::NOW ),
                            'never on the expservices login, whatever was stored' );
        $wrong = sevenxAuthentication2faPolicy::newAppPassword( 'x', array( 'webdav' ), self::NOW );
        $this->assertFalse( sevenxAuthentication2faPolicy::findAppPassword( $entries, $wrong['password'], 'webdav', self::NOW ) );
    }

    public function testAnExpiredOrRevokedAppPasswordNoLongerSignsIn()
    {
        $made = sevenxAuthentication2faPolicy::newAppPassword( 'drive', array( 'webdav' ), self::NOW, self::NOW + 60 );
        $entries = array( $made['entry'] );
        $this->assertSame( 0, sevenxAuthentication2faPolicy::findAppPassword( $entries, $made['password'], 'webdav', self::NOW + 59 ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::findAppPassword( $entries, $made['password'], 'webdav', self::NOW + 60 ) );
        $this->assertTrue( sevenxAuthentication2faPolicy::describeAppPassword( $made['entry'], self::NOW + 60 )['expired'] );

        $left = sevenxAuthentication2faPolicy::removeAppPassword( $entries, $made['entry']['id'] );
        $this->assertSame( array(), $left );
        $this->assertFalse( sevenxAuthentication2faPolicy::removeAppPassword( $entries, 'nope' ) );
        $this->assertFalse( sevenxAuthentication2faPolicy::findAppPassword( $left, $made['password'], 'webdav', self::NOW ) );
    }

    public function testATemplateNeverSeesTheSaltOrTheHash()
    {
        $made = sevenxAuthentication2faPolicy::newAppPassword( 'drive', array( 'webdav' ), self::NOW );
        $seen = sevenxAuthentication2faPolicy::describeAppPassword( $made['entry'], self::NOW );
        $this->assertArrayNotHasKey( 'salt', $seen );
        $this->assertArrayNotHasKey( 'hash', $seen );
        $this->assertSame( array( 'webdav' ), $seen['channels'] );
    }

    public function testAppPasswordsAndCodeFailuresAreKeptThroughTheStoredJson()
    {
        $made = sevenxAuthentication2faPolicy::newAppPassword( 'drive', array( 'webdav', 'rest' ), self::NOW );
        $data = new sevenxAuthentication2fa( 'email', '', true );
        $data->setAppPasswords( array( $made['entry'], array( 'id' => 'broken' ) ) );
        $data->setCodeFailures( array( 'count' => 2, 'since' => self::NOW ) );
        $back = sevenxAuthentication2fa::fromJson( $data->toJson() );
        $this->assertCount( 1, $back->appPasswords(), 'a malformed entry is left out' );
        $this->assertSame( $made['entry']['hash'], $back->appPasswords()[0]['hash'] );
        $this->assertSame( 0, sevenxAuthentication2faPolicy::findAppPassword( $back->appPasswords(), $made['password'], 'rest', self::NOW ) );
        $this->assertSame( array( 'count' => 2, 'since' => self::NOW ), $back->codeFailures() );
        $back->setCodeFailures( null );
        $this->assertStringNotContainsString( 'code_failures', $back->toJson() );
        $this->assertStringNotContainsString( 'app_passwords', ( new sevenxAuthentication2fa() )->toJson() );
    }

    // ------------------------------------------------------------------ what a client is told

    public function testTheSentencesNameWhatWouldHelp()
    {
        $apps = array( 'enabled' => true, 'channels' => array( 'rest', 'webdav', 'soap' ) );
        $webdav = sevenxAuthentication2faSignIn::message( 'second_factor_required', 'webdav', false, $apps );
        $this->assertStringContainsString( 'app password', $webdav );
        $this->assertStringNotContainsString( 'X-Exp-OTP', $webdav );
        $services = sevenxAuthentication2faSignIn::message( 'second_factor_required', 'expservices', true, $apps );
        $this->assertStringContainsString( 'otp', $services );
        $this->assertStringNotContainsString( 'app password', $services, 'no app password on the expservices login' );
        $this->assertStringContainsString( 'API key', sevenxAuthentication2faSignIn::message( 'second_factor_required', 'rest', false, $apps ) );
        $this->assertStringContainsString( 'user2fa/setup', sevenxAuthentication2faSignIn::message( 'second_factor_setup_required', 'webdav', false, $apps ) );
        $this->assertStringContainsString( 'not accepted', sevenxAuthentication2faSignIn::message( 'second_factor_invalid', 'webdav', false, $apps ) );
        $off = array( 'enabled' => false, 'channels' => array() );
        $this->assertStringContainsString( 'Sign in on the site', sevenxAuthentication2faSignIn::message( 'second_factor_required', 'soap', false, $off ) );
    }

    public function testTheHandlerIsTheKernelsInterface()
    {
        $this->assertTrue( is_subclass_of( 'sevenxAuthentication2faSignIn', 'expSignInSecondFactorHandler' ) );
        $site = (string)file_get_contents( dirname( __DIR__, 2 ) . '/settings/site.ini.append.php' );
        $this->assertStringContainsString( "[SignInSecondFactorSettings]\n", $site );
        $this->assertStringContainsString( "\nHandlers[]=sevenxAuthentication2faSignIn\n", $site );
        $this->assertStringNotContainsString( "\nHandlers[]\n", $site, 'never empties the list of other handlers' );
        $ini = (string)file_get_contents( dirname( __DIR__, 2 ) . '/settings/sevenxauthentication2fa.ini.append.php' );
        $this->assertStringContainsString( "\nGuard=enabled\n", $ini );
        $this->assertStringContainsString( "\nCodeChannels[]=expservices\n", $ini );
        $this->assertStringNotContainsString( "\nChannels[]=expservices\n", $ini, 'no app password for the expservices login' );
    }
}
