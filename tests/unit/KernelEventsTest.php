<?php
/**
 * The changes of an account's second step that are also events of the kernel's audit (Exponential 6.0.15,
 * expSignInSecondFactor::audit()): they reach the user audit log, the account's security activity (user/security) and
 * the security alerts by e-mail. sevenxAuthentication2faHelper::kernelEvent() maps an auth.log action to the event,
 * with only the details the event names (never a code). No database: plain values; without the kernel's class the
 * test declares its constants as the kernel does.
 */

use PHPUnit\Framework\TestCase;

if ( !class_exists( 'expSignInSecondFactor' ) )
{
    class expSignInSecondFactor
    {
        const AUDIT_2FA_ENROLL = 'access.user.two_factor.enroll';
        const AUDIT_2FA_REMOVE = 'access.user.two_factor.remove';
        const AUDIT_2FA_RECOVERY_USED = 'access.user.two_factor.recovery_used';
        const AUDIT_2FA_RECOVERY_ISSUED = 'access.user.two_factor.recovery_issued';
        const AUDIT_2FA_VERIFY_FAILED = 'access.user.two_factor.verify.failed';
    }
}
require_once dirname( __DIR__, 2 ) . '/classes/sevenxauthentication2fahelper.php';

class KernelEventsTest extends TestCase
{
    public function testTheChangesOfTheSecondStepAreKernelEvents()
    {
        if ( !defined( 'expSignInSecondFactor::AUDIT_2FA_ENROLL' ) )
        {
            // a kernel before these events: nothing is recorded, as it should be
            $this->assertNull( sevenxAuthentication2faHelper::kernelEvent( '2fa_setup_totp_ok', '' ) );
            return;
        }
        $this->assertSame( array( 'name' => 'access.user.two_factor.enroll', 'after' => array( 'method' => 'totp' ), 'result' => 'success' ),
                           sevenxAuthentication2faHelper::kernelEvent( '2fa_setup_totp_ok', 'totp configured and verified' ) );
        $this->assertSame( array( 'method' => 'email' ), sevenxAuthentication2faHelper::kernelEvent( '2fa_setup_email', '' )['after'] );
        $this->assertSame( 'access.user.two_factor.remove', sevenxAuthentication2faHelper::kernelEvent( '2fa_setup_disabled', '' )['name'] );
        $this->assertSame( array( 'how' => 'reset' ), sevenxAuthentication2faHelper::kernelEvent( '2fa_setup_reset', '' )['after'] );
        $this->assertSame( array( 'left' => 7 ), sevenxAuthentication2faHelper::kernelEvent( '2fa_recovery_code_used', 'left=7' )['after'] );
        $this->assertSame( array( 'count' => 10 ), sevenxAuthentication2faHelper::kernelEvent( '2fa_recovery_codes_issued', 'count=10' )['after'] );
        $failed = sevenxAuthentication2faHelper::kernelEvent( '2fa_verify_failed', 'method=totp code=123456' );
        $this->assertSame( array( 'access.user.two_factor.verify.failed', array( 'method' => 'totp' ), 'failed' ),
                           array( $failed['name'], $failed['after'], $failed['result'] ), 'only the details the event names, never a code' );
    }

    public function testOtherActionsAreNoKernelEvents()
    {
        foreach ( array( '2fa_verify_view', '2fa_setup_view', 'login_succeeded', 'oauth_attempt', '2fa_email_code_sent' ) as $action )
            $this->assertNull( sevenxAuthentication2faHelper::kernelEvent( $action, '' ), $action );
    }
}
