<?php
/**
 * The sign-in code mail (design/standard/templates/mail/2fa_code.tpl): an HTML mail with a plain-text part in the
 * kernel's mail layout (design:mail/layout.tpl, design:mail/layout_text.tpl), and the plain-text template for a kernel
 * without that layout (mail/2fa_code_plain.tpl). Needs the kernel (Exponential 6.0.15 or later), no database, sends
 * nothing; run from the kernel root:
 *
 *   php vendor/bin/phpunit extension/sevenx_authentication_2fa/tests/kernel/SignInCodeMailTest.php
 *
 *  CM-01 - the template sets the subject with the site and the code, content_type text/html and body_text
 *  CM-02 - the HTML part is the layout: the code large in its own box, the button to continue, its address as text,
 *          the expiry, the security notes; the text part carries the same without markup
 *  CM-03 - the site name is escaped in the HTML part
 *  CM-04 - the template carries no styles and no markup of its own
 *  CM-05 - the plain-text template: the code, the link, the expiry and the security note, and no markup
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

class SignInCodeMailTest extends PHPUnit\Framework\TestCase
{
    private $savedGlobals = array();

    protected function setUp(): void
    {
        if ( !class_exists( 'eZTemplate' ) || !class_exists( 'expMailTemplateBody' ) || !method_exists( 'expMailTemplateBody', 'applyRendered' ) )
            $this->markTestSkipped( 'needs the kernel with the mail layout (Exponential 6.0.15 or later)' );
        foreach ( array( 'eZTemplateCompilerSettings', 'eZSiteBasics', 'eZCurrentAccess' ) as $name )
            $this->savedGlobals[$name] = array_key_exists( $name, $GLOBALS ) ? array( $GLOBALS[$name] ) : null;
        $GLOBALS['eZTemplateCompilerSettings']['compile'] = false;
        $GLOBALS['eZSiteBasics']['no-cache-adviced'] = true;
        if ( !isset( $GLOBALS['eZCurrentAccess']['name'] ) )
            $GLOBALS['eZCurrentAccess'] = array( 'name' => eZINI::instance()->variable( 'SiteSettings', 'DefaultAccess' ),
                                                 'type' => eZSiteAccess::TYPE_DEFAULT );
    }

    protected function tearDown(): void
    {
        foreach ( $this->savedGlobals as $name => $saved )
        {
            if ( $saved === null )
                unset( $GLOBALS[$name] );
            else
                $GLOBALS[$name] = $saved[0];
        }
    }

    private static function template( $name )
    {
        return dirname( __DIR__, 2 ) . '/design/standard/templates/mail/' . $name;
    }

    /** @return array html, text, subject, content_type */
    private function render( $template, $siteName = 'CM Site' )
    {
        $tpl = eZTemplate::factory();
        $tpl->resetVariables();
        foreach ( array( 'code' => '005611', 'expires' => 10, 'site_url' => 'https://www.example.invalid', 'site_name' => $siteName,
                         'verify_url' => 'https://www.example.invalid/user2fa/verify/code/005611',
                         'login_url' => 'https://www.example.invalid/user/login',
                         'user' => array( 'login' => 'cm-test', 'email' => 'cm-test@example.invalid' ) ) as $name => $value )
            $tpl->setVariable( $name, $value );
        $handler = set_exception_handler( null );
        restore_exception_handler();
        try
        {
            $html = $tpl->fetch( $template );
            return array( 'html' => $html,
                          'text' => $tpl->hasVariable( 'body_text' ) ? (string)$tpl->variable( 'body_text' ) : null,
                          'subject' => $tpl->hasVariable( 'subject' ) ? trim( (string)$tpl->variable( 'subject' ) ) : null,
                          'content_type' => $tpl->hasVariable( 'content_type' ) ? trim( (string)$tpl->variable( 'content_type' ) ) : null );
        }
        finally
        {
            $current = set_exception_handler( null );
            restore_exception_handler();
            if ( $current !== $handler )
                restore_exception_handler();
            $tpl->resetVariables();
        }
    }

    /** CM-01, CM-02 */
    public function testTheMailIsTheLayoutWithATextPart()
    {
        $mail = $this->render( self::template( '2fa_code.tpl' ) );
        $this->assertSame( 'Your sign-in code for CM Site: 005611', $mail['subject'] );
        $this->assertSame( 'text/html', $mail['content_type'] );
        $this->assertStringContainsString( 'role="presentation"', $mail['html'] );
        $this->assertMatchesRegularExpression( '#font-size:32px[^>]*>005611</td>#', $mail['html'], 'the code large in its box' );
        $this->assertMatchesRegularExpression( '#<a href="https://www.example.invalid/user2fa/verify/code/005611" style="display:inline-block[^>]*>Continue signing in</a>#', $mail['html'] );
        $this->assertStringContainsString( 'copy this address into your browser: https://www.example.invalid/user2fa/verify/code/005611', $mail['html'] );
        $this->assertStringContainsString( 'The code expires in 10 minutes.', $mail['html'] );
        $this->assertStringContainsString( 'If you did not try to sign in', $mail['html'] );
        $this->assertStringContainsString( 'Never share this code.', $mail['html'] );

        $this->assertNotNull( $mail['text'] );
        $this->assertStringContainsString( "    005611\n", $mail['text'] );
        $this->assertStringContainsString( 'Continue signing in: https://www.example.invalid/user2fa/verify/code/005611', $mail['text'] );
        $this->assertStringContainsString( 'Sign-in page: https://www.example.invalid/user/login', $mail['text'] );
        $this->assertStringContainsString( 'The code expires in 10 minutes.', $mail['text'] );
        $this->assertDoesNotMatchRegularExpression( '/<(table|td|p|a|div|br)\b|style="/i', $mail['text'] );
    }

    /** CM-03 */
    public function testTheSiteNameIsEscaped()
    {
        $mail = $this->render( self::template( '2fa_code.tpl' ), '<script>x</script>' );
        $this->assertStringNotContainsString( '<script>', $mail['html'] );
        $this->assertStringContainsString( '&lt;script&gt;x&lt;/script&gt;', $mail['html'] );
    }

    /** CM-04 */
    public function testTheTemplateCarriesNoStylesOrMarkup()
    {
        $source = file_get_contents( self::template( '2fa_code.tpl' ) );
        $this->assertDoesNotMatchRegularExpression( '/style\s*=|<style/i', $source );
        $this->assertDoesNotMatchRegularExpression( '/<[a-z]/i', preg_replace( '/\{\*.*?\*\}/s', '', $source ) );
        $this->assertStringContainsString( 'design:mail/layout.tpl', $source );
        $this->assertStringContainsString( 'design:mail/layout_text.tpl', $source );
    }

    /** CM-05 */
    public function testThePlainTextTemplate()
    {
        $mail = $this->render( self::template( '2fa_code_plain.tpl' ) );
        $this->assertSame( 'Your sign-in code for CM Site: 005611', $mail['subject'] );
        $this->assertStringContainsString( "    005611\n", $mail['html'] );
        $this->assertStringContainsString( "https://www.example.invalid/user2fa/verify/code/005611\n", $mail['html'] );
        $this->assertStringContainsString( 'The code expires in 10 minutes.', $mail['html'] );
        $this->assertStringContainsString( 'If you did not try to sign in', $mail['html'] );
        $this->assertDoesNotMatchRegularExpression( '/<[a-z]/i', $mail['html'] );
    }
}
