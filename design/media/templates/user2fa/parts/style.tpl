{* The look of the two-factor pages in the media design: the parts the design has no furniture for (the code field,
   the choice of method, the QR code with its key, the steps, and the social login buttons). Everything else is the
   design's own account furniture from stylesheets/account.css of sevenx_themes_media.

   The rules are in stylesheets/sevenx_authentication_2fa_media.css of this extension's media design. ezcss_require
   hands it to the media pagelayout's ezcss_load, which packs it with the design's stylesheets (ezjscore packer). No
   <style> block: a Content-Security-Policy without 'unsafe-inline' for style-src drops it.
   Included once by each page. Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{ezcss_require( 'sevenx_authentication_2fa_media.css' )}
