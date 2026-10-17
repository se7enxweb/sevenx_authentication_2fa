/**
 * sevenx_authentication_2fa: the setup page (user2fa/setup.tpl of the standard and media designs).
 *
 * The enrolment of an authenticator app (#tfa-enrol) is shown only while the method "totp" is picked in
 * #tfa-setup-form. A file instead of an inline script, so the page works under a Content Security Policy without
 * 'unsafe-inline'.
 */
(function () {
    function start() {
        var form = document.getElementById( 'tfa-setup-form' ), enrol = document.getElementById( 'tfa-enrol' );
        if ( !form || !enrol || form.getAttribute( 'data-tfa-ready' ) ) return;
        form.setAttribute( 'data-tfa-ready', '1' );
        function update() {
            var picked = form.querySelector( 'input[name="Method"]:checked' );
            enrol.hidden = !picked || picked.value !== 'totp';
        }
        form.addEventListener( 'change', function ( e ) { if ( e.target && e.target.name === 'Method' ) update(); } );
        update();
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', start ); } else { start(); }
})();
