<?php
/**
 * The admin templates of sevenx_authentication_2fa carry no inline CSS: no <style> block and no style="" attribute. A
 * Content-Security-Policy without 'unsafe-inline' for style-src-attr drops those, and the admin designs keep their
 * styles in stylesheets the ezjscore packer bundles. Reads the files only, without the kernel.
 *
 * Run directly with:
 *   php tests/sevenxauthentication2faadmininlinecsstest.php
 *
 *  IC-01 - no <style> block and no style="" attribute in the templates of the admin designs (template comments left out)
 *  IC-02 - design.ini [StylesheetSettings] BackendCSSFileList[] names each stylesheet below, and the file exists
 *  IC-03 - every exp-2fa-* class of the admin templates is defined in those stylesheets
 */

$failures = 0;
function icCheck( $ok, $message )
{
    global $failures;
    echo ( $ok ? 'PASS' : 'FAIL' ), ": $message\n";
    if ( !$ok )
        $failures++;
}

$root = dirname( __DIR__ );
$stylesheets = array( 'design/admin/stylesheets/sevenx_authentication_2fa_admin.css' );
$prefix = 'exp-2fa-';

$templates = array();
foreach ( glob( $root . '/design/{admin,admin2,admin3,admin4,admin4l,adminui}', GLOB_BRACE | GLOB_ONLYDIR ) as $dir )
{
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $it as $file )
        if ( substr( (string)$file, -4 ) === '.tpl' )
            $templates[substr( (string)$file, strlen( $root ) + 1 )] = preg_replace( '/\{\*.*?\*\}/s', '', file_get_contents( (string)$file ) );
}
ksort( $templates );

// IC-01
$inline = array();
foreach ( $templates as $path => $source )
    if ( preg_match_all( '/<style\b/i', $source ) + preg_match_all( '/(?<![\w.\-])style\s*=\s*["\'{]/i', $source ) )
        $inline[] = $path;
icCheck( count( $templates ) > 0 && !$inline, 'IC-01 no inline CSS in ' . count( $templates ) . ' admin templates' . ( $inline ? ': ' . implode( ', ', $inline ) : '' ) );

// IC-02
$ini = (string)@file_get_contents( $root . '/settings/design.ini.append.php' );
$sheet = '';
foreach ( $stylesheets as $path )
{
    $name = basename( $path );
    $ok = preg_match( '/^\[StylesheetSettings\]\s*$/m', $ini ) && preg_match( '/^BackendCSSFileList\[\]=' . preg_quote( $name, '/' ) . '\s*$/m', $ini ) && is_file( $root . '/' . $path );
    icCheck( $ok, "IC-02 design.ini loads $name for the admin" );
    $sheet .= is_file( $root . '/' . $path ) ? file_get_contents( $root . '/' . $path ) : '';
}

// IC-03
$missing = array();
if ( $prefix !== '' )
    foreach ( $templates as $path => $source )
        if ( preg_match_all( '/class="([^"]*)"/', $source, $m ) )
            foreach ( $m[1] as $list )
                foreach ( preg_split( '/\s+/', trim( preg_replace( '/\{.*?\}/s', ' ', $list ) ) ) as $class )
                    if ( $class !== '' && strpos( $class, $prefix ) === 0 && !preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![\w-])/', $sheet ) )
                        $missing[$class] = true;
if ( $prefix !== '' )
    icCheck( !$missing, "IC-03 every $prefix* class is defined" . ( $missing ? ': ' . implode( ', ', array_keys( $missing ) ) : '' ) );

// IC-04 the standard design's two-factor templates (user2fa/exp_style.tpl, included by verify, setup, callback and
// the admin's datatype edit template) carry no <style> block either: their stylesheet is loaded by design.ini for
// the public and the administration designs, and defines the .exp-2fa frame
$standard = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/design/standard/templates', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file )
    if ( substr( (string)$file, -4 ) === '.tpl' && preg_match_all( '/<style\b/i', preg_replace( '/\{\*.*?\*\}/s', '', file_get_contents( (string)$file ) ) ) )
        $standard[] = substr( (string)$file, strlen( $root ) + 1 );
icCheck( !$standard, 'IC-04 no <style> block in the standard design\'s templates' . ( $standard ? ': ' . implode( ', ', $standard ) : '' ) );
$pages = 'design/standard/stylesheets/sevenx_authentication_2fa.css';
$pagesCss = is_file( $root . '/' . $pages ) ? file_get_contents( $root . '/' . $pages ) : '';
icCheck( preg_match( '/^FrontendCSSFileList\[\]=sevenx_authentication_2fa\.css\s*$/m', $ini )
         && preg_match( '/^BackendCSSFileList\[\]=sevenx_authentication_2fa\.css\s*$/m', $ini )
         && strpos( $pagesCss, '.exp-2fa {' ) !== false,
         "IC-04 design.ini loads $pages for the public and the administration designs" );
icCheck( strpos( (string)@file_get_contents( $root . '/design/standard/templates/user2fa/exp_style.tpl' ), "ezcss_require( 'sevenx_authentication_2fa.css' )" ) !== false,
         'IC-04 user2fa/exp_style.tpl requires the stylesheet for the page layouts that load no list' );

echo $failures ? "FAIL: $failures\n" : "PASS: all\n";
exit( $failures ? 1 : 0 );
