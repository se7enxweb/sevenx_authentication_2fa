<?php /* #?ini charset="utf-8"?

[General]
# Enable or disable the 2FA/social login extension globally.
Enabled=enabled

# Issuer name shown in authenticator apps (the TOTP account label).
Issuer=Exponential

# Default method for users who have not explicitly chosen: totp | email | disabled
DefaultMethod=disabled

# Require 2FA for all users with the configured method. When disabled, users opt-in.
Enforce2FA=disabled

# Allow fallback to email OTP when TOTP is configured but the authenticator is unavailable.
AllowEmailFallback=enabled

[CodeSettings]
# TOTP/email code length in digits.
Length=6

# Time step in seconds for TOTP codes.
TimeStep=30

# Number of past/future time windows to accept (compensates for clock skew).
Window=1

# Email / temporary code lifetime in seconds.
EmailTTL=600

# Hash algorithm for TOTP: SHA1 | SHA256 | SHA512.
Algorithm=SHA1

[EmailSettings]
# Subject of the OTP email. {code} is replaced with the actual code.
Subject=Your login verification code is {code}

# Plain-text body. {code} and {expires} are replaced.
Body=Enter the following code to complete your login:

{code}

This code expires in {expires} minutes.

# Sender address. If empty, the site default is used.
Sender=

[TOTPSettings]
# Secret length in bytes (Base32 encoded secret length will be larger).
SecretLength=20

[SocialLogin]
# Master switch for OAuth/social login handlers.
Enabled=disabled

# If enabled, new users without a local account can be auto-created.
AutoCreateUser=disabled

# Default user group node ID for auto-created users.
DefaultUserGroupNodeID=12

# Default authentication match for social users.
AuthenticationMatch=email

[SocialProviders]
# Provider-specific settings are read from separate .ini files or from the
# provider-specific blocks below.  Each provider needs:
#   ClientID     = the OAuth application id
#   ClientSecret = the OAuth application secret
#   Scope        = space-separated OAuth scopes
#   Enabled      = enabled | disabled

[Google]
Enabled=disabled
ClientID=
ClientSecret=
Scope=openid email profile
AuthorizationURL=https://accounts.google.com/o/oauth2/v2/auth
TokenURL=https://oauth2.googleapis.com/token
UserInfoURL=https://openidconnect.googleapis.com/v1/userinfo

[Facebook]
Enabled=disabled
ClientID=
ClientSecret=
Scope=email public_profile
AuthorizationURL=https://www.facebook.com/v18.0/dialog/oauth
TokenURL=https://graph.facebook.com/v18.0/oauth/access_token
UserInfoURL=https://graph.facebook.com/v18.0/me?fields=id,name,email

[TwitterX]
Enabled=disabled
ClientID=
ClientSecret=
Scope=users.read tweet.read
AuthorizationURL=https://twitter.com/i/oauth2/authorize
TokenURL=https://api.twitter.com/2/oauth2/token
UserInfoURL=https://api.twitter.com/2/users/me?user.fields=email

[Instagram]
Enabled=disabled
ClientID=
ClientSecret=
Scope=user_profile user_media
AuthorizationURL=https://api.instagram.com/oauth/authorize
TokenURL=https://api.instagram.com/oauth/access_token
UserInfoURL=https://graph.instagram.com/me?fields=id,username,email

[Meta]
Enabled=disabled
ClientID=
ClientSecret=
Scope=email public_profile
AuthorizationURL=https://www.facebook.com/v18.0/dialog/oauth
TokenURL=https://graph.facebook.com/v18.0/oauth/access_token
UserInfoURL=https://graph.facebook.com/v18.0/me?fields=id,name,email

[Idme]
Enabled=disabled
ClientID=
ClientSecret=
Scope=REPLACE_WITH_COMMUNITY_SCOPE
# Common ID.me scopes: military, student, teacher, responder, government,
# employee, hospital_employee, alumni, nurse, medical, and their _canada variants.
# For OIDC flows include openid in the scope.
Op=signin
# Op can be signin or signup.
EID=
# Optional external identifier passed through to ID.me.
Nonce=
# Optional OIDC nonce.
UsePKCE=enabled
# PKCE is required/recommended by ID.me and is always enabled for this handler.
AuthorizationURL=https://api.id.me/oauth/authorize
TokenURL=https://api.id.me/oauth/token
UserInfoURL=https://api.id.me/api/public/v3/attributes.json

*/ ?>
