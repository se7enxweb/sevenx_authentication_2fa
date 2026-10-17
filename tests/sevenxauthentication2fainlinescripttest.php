<?php
/**
 * The templates of sevenx_authentication_2fa carry no inline JavaScript: no <script> element with code, no event handler attribute
 * (onclick=, onsubmit= ...) and no javascript: link. A Content-Security-Policy without 'unsafe-inline' for script-src
 * blocks those; the code is in the files of design/standard/javascript. Reads the files only, without the kernel.
 *
 * Run directly with:
 *   php tests/sevenxauthentication2fainlinescripttest.php
 *
 *  IS-01 - no <script> element with code in any template (a src= file or a JSON data block is fine; comments left out)
 *  IS-02 - no event handler attribute and no javascript: link in any template
 *  IS-03 - the setup pages (standard and media designs) print sevenx_authentication_2fa_setup.js
 */

$failures = 0;
function isCheck( $ok, $message )
{
    global $failures;
    echo ( $ok ? 'PASS' : 'FAIL' ), ": $message\n";
    if ( !$ok )
        $failures++;
}

$root = dirname( __DIR__ );
$templates = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/design', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file )
    if ( substr( (string)$file, -4 ) === '.tpl' )
        $templates[substr( (string)$file, strlen( $root ) + 1 )] = preg_replace( '/\{\*.*?\*\}/s', '', file_get_contents( (string)$file ) );
ksort( $templates );

// IS-01
$scripts = array();
foreach ( $templates as $path => $source )
    if ( preg_match( '/<script\b(?![^>]*\bsrc\s*=)(?![^>]*type\s*=\s*["\']application\/(?:ld\+)?json["\'])[^>]*>/i', $source ) )
        $scripts[] = $path;
isCheck( count( $templates ) > 0 && !$scripts, 'IS-01 no inline script in ' . count( $templates ) . ' templates' . ( $scripts ? ': ' . implode( ', ', $scripts ) : '' ) );

// IS-02
$handlers = array();
foreach ( $templates as $path => $source )
    if ( preg_match( '/<[a-z][^>]*\son[a-z]+\s*=/i', $source ) || preg_match( '/=\s*["\']?\s*javascript:/i', $source ) )
        $handlers[] = $path;
isCheck( !$handlers, 'IS-02 no event handler attribute or javascript: link' . ( $handlers ? ': ' . implode( ', ', $handlers ) : '' ) );

// IS-03
$js = (string)@file_get_contents( $root . '/design/standard/javascript/sevenx_authentication_2fa_setup.js' );
$ok = strpos( $js, "getElementById( 'tfa-setup-form' )" ) !== false && strpos( $js, "picked.value !== 'totp'" ) !== false;
foreach ( array( 'design/standard/templates/user2fa/setup.tpl', 'design/media/templates/user2fa/setup.tpl' ) as $path )
{
    $source = isset( $templates[$path] ) ? $templates[$path] : '';
    $ok = $ok && strpos( $source, "{ezscript( array( 'sevenx_authentication_2fa_setup.js' ) )}" ) !== false
              && strpos( $source, 'id="tfa-setup-form"' ) !== false;
}
isCheck( $ok, 'IS-03 the setup pages print sevenx_authentication_2fa_setup.js, which shows the enrolment for "totp"' );

echo $failures ? "FAIL $failures\n" : "PASS\n";
exit( $failures ? 1 : 0 );
