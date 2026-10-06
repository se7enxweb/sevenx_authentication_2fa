<?php
/**
 * sevenxAuthentication2faRedirect: where a visitor may be sent after the
 * second step or a social login. Paths of the site pass; other hosts,
 * protocol-relative URLs, backslashes, encoded tricks, schemes, control
 * characters and the sign-in views themselves fall back to the default.
 * The extension's own rules are tested here (the kernel's have their own tests).
 */

use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
{
    public static function safeTargets()
    {
        return array(
            array( '/content/view/full/2', '/content/view/full/2' ),
            array( 'content/view/full/2', '/content/view/full/2' ),
            array( '/Company/About?x=1#top', '/Company/About?x=1#top' ),
            array( '/admin/content/dashboard', '/admin/content/dashboard' ),
            array( '/user/edit', '/user/edit' ),
        );
    }

    /**
     * @dataProvider safeTargets
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('safeTargets')]
    public function testSafeTargetsPass( $in, $out )
    {
        $this->assertSame( $out, sevenxAuthentication2faRedirect::safe( $in ) );
    }

    public static function unsafeTargets()
    {
        return array(
            'other host'           => array( 'https://evil.example/' ),
            'protocol relative'    => array( '//evil.example/x' ),
            'backslash'            => array( '/\\evil.example' ),
            'double backslash'     => array( '\\\\evil.example' ),
            'encoded slashes'      => array( '%2F%2Fevil.example' ),
            'double encoded'       => array( '%252F%252Fevil.example' ),
            'encoded backslash'    => array( '/%5Cevil.example' ),
            'javascript'           => array( 'javascript:alert(1)' ),
            'encoded javascript'   => array( 'javascript%3Aalert(1)' ),
            'data'                 => array( 'data:text/html,x' ),
            'tab'                  => array( "/\t/evil.example" ),
            'newline'              => array( "/x\r\nSet-Cookie: a=b" ),
            'empty'                => array( '' ),
            'array'                => array( array( '/x' ) ),
            'null'                 => array( null ),
            'login view'           => array( '/user/login' ),
            'logout view'          => array( '/user/logout' ),
            'verify view'          => array( '/user2fa/verify/code/123456' ),
            'prefixed logout'      => array( '/admin/user/logout' ),
            'callback view'        => array( 'user2fa/callback/google' ),
        );
    }

    /**
     * @dataProvider unsafeTargets
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeTargets')]
    public function testUnsafeTargetsFallBack( $in )
    {
        $this->assertSame( '/fallback', sevenxAuthentication2faRedirect::safe( $in, '/fallback' ) );
    }

    public function testReasonsAreNamed()
    {
        $this->assertSame( 'host', sevenxAuthentication2faRedirect::localReason( '//evil.example' ) );
        $this->assertSame( 'scheme', sevenxAuthentication2faRedirect::localReason( 'https://evil.example' ) );
        $this->assertSame( 'backslash', sevenxAuthentication2faRedirect::localReason( '/\\evil' ) );
        $this->assertSame( 'encoded', sevenxAuthentication2faRedirect::localReason( '%2F%2Fevil' ) );
        $this->assertSame( 'control', sevenxAuthentication2faRedirect::localReason( "/a\nb" ) );
        $this->assertSame( 'type', sevenxAuthentication2faRedirect::localReason( 5 ) );
        $this->assertFalse( sevenxAuthentication2faRedirect::localReason( '/fine/path?q=%2F' ) );
    }
}
