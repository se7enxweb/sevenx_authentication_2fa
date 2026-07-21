<?php /* #?ini charset="utf-8"?

[UserSettings]
# Tell eZUserLoginHandler where to find custom login handlers.
ExtensionDirectory[]=sevenx_authentication_2fa

# NOTE: To activate 2FA / social login you must set the login handler to sevenxUser2fa
# in your site override. Example:
#
# [UserSettings]
# LoginHandler[]=sevenxUser2fa
#
# If you keep LoginHandler[]=standard, the standard handler will log the user in
# before the 2FA challenge can run.

[SiteAccessSettings]
# Allow anonymous access to the 2FA verification and social callback views.
# AnonymousAccessList[]=user2fa/verify
# AnonymousAccessList[]=user2fa/setup
# AnonymousAccessList[]=user2fa/callback
# AnonymousAccessList[]=user2fa/oauth

*/ ?>
