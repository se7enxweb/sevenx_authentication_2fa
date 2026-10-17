<?php /* #?ini charset="utf-8"?

[ExtensionSettings]
# Register this extension as a design source. The exact search order is
# controlled by DesignExtensions in design.ini/site overrides.
DesignExtensions[]=sevenx_authentication_2fa

[StylesheetSettings]
# the administration styles of the extension (no style="" attributes in its admin templates), bundled by the ezjscore packer
BackendCSSFileList[]=sevenx_authentication_2fa_admin.css
# the two-factor pages and the 2FA field of a user object (verify, setup, callback; user2fa/exp_style.tpl), in the
# administration designs and the public designs without their own (everything is scoped to .exp-2fa)
BackendCSSFileList[]=sevenx_authentication_2fa.css
FrontendCSSFileList[]=sevenx_authentication_2fa.css

*/ ?>
