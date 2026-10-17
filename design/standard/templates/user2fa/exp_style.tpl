{* The look of the two-factor pages in every design without its own (the administration designs admin4, admin3,
   admin2, admin and the Admin UI, and the public designs other than media): the second step at sign-in (user2fa/verify),
   setting up and changing it (user2fa/setup), the social login's answers (user2fa/callback), the social login
   buttons of the login page and the 2FA field of a user object. Included once by each of them.

   The rules are in stylesheets/sevenx_authentication_2fa.css, loaded through design.ini [StylesheetSettings]
   (FrontendCSSFileList and BackendCSSFileList) and bundled by the ezjscore packer; required here as well for the
   page layouts that load no list. No <style> block: a Content-Security-Policy without 'unsafe-inline' for
   style-src drops it. The empty marker after it scopes the rules of the social login buttons, which follow it.
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{ezcss_require( 'sevenx_authentication_2fa.css' )}<span class="exp-2fa-css" hidden></span>
