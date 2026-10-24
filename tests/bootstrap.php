<?php
/**
 * PHPUnit bootstrap for the sevenx_authentication_2fa unit tests.
 *
 * Loads only the plain PHP classes under test from this checkout. Nothing of
 * Exponential is booted: no database, no siteaccess, no INI, no session. The
 * redirect rules are the extension's own (the kernel's are tested by the
 * kernel), and the secret key is set by the tests that need one.
 *
 *   php vendor/bin/phpunit --bootstrap extension/sevenx_authentication_2fa/tests/bootstrap.php \
 *       extension/sevenx_authentication_2fa/tests/unit
 */

date_default_timezone_set( 'UTC' );

$checkout = dirname( __DIR__ );
require_once $checkout . '/classes/sevenxauthentication2fatotp.php';
require_once $checkout . '/classes/sevenxauthentication2faattempts.php';
require_once $checkout . '/classes/sevenxauthentication2faredirect.php';
require_once $checkout . '/classes/sevenxauthentication2facrypto.php';
require_once $checkout . '/classes/sevenxauthentication2fa.php';
require_once $checkout . '/classes/sevenxauthentication2fapolicy.php';

// The kernel's interface of the sign-in paths outside the web login form (Exponential 6.0.15), declared here as the
// kernel declares it when the tests run without a kernel, so the handler's own rules are tested too
if ( !interface_exists( 'expSignInSecondFactorHandler' ) )
{
    interface expSignInSecondFactorHandler
    {
        public function checkSignIn( eZUser $user, $channel, $code );
        public function appPasswordUser( $login, $secret, $channel );
    }
}
require_once $checkout . '/classes/sevenxauthentication2fasignin.php';

sevenxAuthentication2faRedirect::$useKernelRules = false;
sevenxAuthentication2faCrypto::setKey( '' );
