<?php
/**
 * The media design's two-factor templates (user2fa/verify, setup, callback, parts/style and the 2FA field of the public
 * edit form) carry no inline CSS: no <style> block and no style="" attribute. A Content-Security-Policy without
 * 'unsafe-inline' for style-src drops those. Their rules are in design/media/stylesheets/sevenx_authentication_2fa_media.css,
 * which user2fa/parts/style.tpl hands to the media pagelayout's ezcss_load with ezcss_require. Reads the files only,
 * without the kernel.
 *
 * Run directly with:
 *   php tests/sevenxauthentication2famediainlinecsstest.php
 *
 *  MC-01 - no <style> block and no style="" attribute in the media design's templates (template comments left out)
 *  MC-02 - user2fa/parts/style.tpl requires the stylesheet with ezcss_require, and the file exists
 *  MC-03 - every tfa-* class of the media templates is defined in that stylesheet
 */

$failures = 0;
function mcCheck( $ok, $message )
{
    global $failures;
    echo ( $ok ? 'PASS' : 'FAIL' ), ": $message\n";
    if ( !$ok )
        $failures++;
}

$root = dirname( __DIR__ );
$sheetPath = 'design/media/stylesheets/sevenx_authentication_2fa_media.css';

$templates = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/design/media/templates', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file )
    if ( substr( (string)$file, -4 ) === '.tpl' )
        $templates[substr( (string)$file, strlen( $root ) + 1 )] = preg_replace( '/\{\*.*?\*\}/s', '', file_get_contents( (string)$file ) );
ksort( $templates );

// MC-01
$inline = array();
foreach ( $templates as $path => $source )
    if ( preg_match_all( '/<style\b/i', $source ) + preg_match_all( '/(?<![\w.\-])style\s*=\s*["\'{]/i', $source ) )
        $inline[] = $path;
mcCheck( count( $templates ) >= 5 && !$inline, 'MC-01 no inline CSS in ' . count( $templates ) . ' media templates' . ( $inline ? ': ' . implode( ', ', $inline ) : '' ) );

// MC-02
$part = isset( $templates['design/media/templates/user2fa/parts/style.tpl'] ) ? $templates['design/media/templates/user2fa/parts/style.tpl'] : '';
mcCheck( is_file( $root . '/' . $sheetPath ) && preg_match( "/\{ezcss_require\(\s*'sevenx_authentication_2fa_media\.css'\s*\)\}/", $part ),
         "MC-02 user2fa/parts/style.tpl requires $sheetPath" );
$sheet = is_file( $root . '/' . $sheetPath ) ? preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $root . '/' . $sheetPath ) ) : '';
mcCheck( strpos( $sheet, '.tfa-m {' ) !== false && strpos( $sheet, '<style' ) === false, 'MC-02 the stylesheet defines the .tfa-m frame' );

// MC-03
$missing = array();
foreach ( $templates as $path => $source )
    if ( preg_match_all( '/class="([^"]*)"/', $source, $m ) )
        foreach ( $m[1] as $list )
            foreach ( preg_split( '/\s+/', trim( preg_replace( '/\{.*?\}/s', ' ', $list ) ) ) as $class )
                if ( $class !== '' && strpos( $class, 'tfa-' ) === 0 && !preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![\w-])/', $sheet ) )
                    $missing[$class] = true;
mcCheck( !$missing, 'MC-03 every tfa-* class is defined' . ( $missing ? ': ' . implode( ', ', array_keys( $missing ) ) : '' ) );

echo $failures ? "FAIL: $failures\n" : "PASS: all\n";
exit( $failures ? 1 : 0 );
