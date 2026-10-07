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
# The second step happens before the user is signed in, so a siteaccess with
# RequireUserLogin=true (an administration interface) has to let it through.
# Each view checks for itself that this session passed the password (verify,
# setup) or started the social login (callback).
AnonymousAccessList[]=user2fa/verify
AnonymousAccessList[]=user2fa/setup
AnonymousAccessList[]=user2fa/callback
AnonymousAccessList[]=user2fa/oauth

[RoleSettings]
# The same views skip the kernel's policy check, as user/login does: before
# the second step the visitor is the anonymous user, who may not use an
# administration siteaccess at all, so no policy could let them through
# there. verify, oauth and callback check their own session state; setup
# also checks the policy user2fa/setup for a signed in user.
PolicyOmitList[]=user2fa/verify
PolicyOmitList[]=user2fa/setup
PolicyOmitList[]=user2fa/callback
PolicyOmitList[]=user2fa/oauth

# The extension's own interface strings (translations/<locale>/translation.ts)
[RegionalSettings]
TranslationExtensions[]=sevenx_authentication_2fa

*/ ?>
