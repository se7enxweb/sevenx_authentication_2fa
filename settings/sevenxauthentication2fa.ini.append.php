<?php /* #?ini charset="utf-8"?

[General]
# Enable or disable the 2FA/social login extension globally.
Enabled=enabled

# Issuer name shown in authenticator apps (the TOTP account label).
Issuer=Exponential

# Default method for users who have not explicitly chosen: totp | email | disabled
DefaultMethod=disabled

# Require a second step at sign-in. When disabled, users opt in on their account page.
# When enabled, a user it applies to who has none set up is taken to the setup page
# after the password (DefaultMethod: totp sets up an authenticator app, email sends
# codes to the account's address without a setup) and is signed in only after it.
# It stays off here so that updating the extension never locks anyone out; the
# installers of new sites switch it on in settings/override for the groups below.
Enforce2FA=disabled

# Whom Enforce2FA applies to: members of these user groups, directly or through a
# group below them. Each value is the group's node id (as the administration shows
# it) or its object remote id, which is the same on every installation made from
# the standard data:
#   Administrator users  9b47a45624b023b1a76c73b74d704acf
#   Editors              3c160cca19fb135f83bd02d911f04db2
# Empty: every user. A list that names no existing group applies to nobody (the
# error log names the value).
EnforcedGroups[]

# Never asked for a second step by Enforce2FA (they may still set one up): members of
# these user groups (node id or object remote id), and accounts whose e-mail address
# is in one of these domains (exactly that domain). Meant for accounts of automated
# checks, e.g. ExemptEmailDomains[]=example.invalid (a reserved domain no mail
# reaches). Whoever may change these settings or move users into such a group
# decides who needs no second step: keep both lists short.
ExemptGroups[]
ExemptEmailDomains[]

# Days a user whom Enforce2FA asks for a second step may still sign in without one,
# with "Set up later" on the setup page, counted from the first sign-in that asked.
# 0: the setup comes first. Needs the user class's two-factor field to count.
GracePeriodDays=0

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

# Optional plain-text body override. If left empty or commented out, the template
# extension/sevenx_authentication_2fa/design/standard/templates/mail/2fa_code.tpl is used.
# Placeholders: {code}, {expires}, {site_url}, {verify_url}.
#Body=

# Sender address. If empty, the site default is used.
Sender=

[TOTPSettings]
# Secret length in bytes (Base32 encoded secret length will be larger).
SecretLength=20

# A key that encrypts TOTP secrets where they are stored (AES-256-GCM). Set it
# in settings/override, never here, to a long random value, e.g. the output of
#   openssl rand -base64 48
# The environment variable SEVENX_2FA_SECRET_KEY takes precedence. Without a
# key secrets are stored in plain text in the user object. Secrets stored
# before a key was set keep working and are encrypted when next stored;
# changing or losing the key makes every stored secret unreadable, so those
# users have to set up their authenticator again.
SecretKey=

[Security]
# Wrong codes allowed for one sign-in; after that the password is asked again.
# Every wrong code also counts as a failed login of the account
# (site.ini [UserSettings] MaxNumberOfFailedLogin).
MaxAttempts=5

# Seconds the second step of one sign-in stays open (authenticator codes).
# E-mail codes use [CodeSettings] EmailTTL.
ChallengeTTL=300

# Seconds before another e-mail code may be asked for, and how many times.
ResendInterval=60
MaxResends=3

# Seconds a user who has to set up a second step first (Enforce2FA) has for it.
SetupTTL=900

# Recovery codes an authenticator app gets when it is set up: one-time codes that
# sign in in place of the app's code when the phone is lost. They are shown once,
# only their hashes are stored, and the user can make a new set (which ends the old
# one) on the setup page with a current code. 0: none. At most 20.
RecoveryCodes=10

[OtherSignInPaths]
# The second step on the sign-in paths outside the web login form, which check a
# password themselves: rest (the REST interface with basic authentication), webdav,
# soap and expservices (the login of the remote services). The kernel asks this
# extension there (site.ini [SignInSecondFactorSettings] Handlers[]).
#
# For an account Enforce2FA applies to, a password alone is refused on these paths:
# REST answers 401 second_factor_required (403 second_factor_setup_required while the
# account has no second step yet), WebDAV and SOAP 401 with WWW-Authenticate and the
# reason in X-Exp-Sign-In-Refused, the expservices login 401 with error.reason. The
# account signs in there with an app password ([AppPasswords]) or, where CodeChannels[]
# allows it, with the current code of its authenticator app. Accounts Enforce2FA does
# not apply to, and exempt ones (ExemptGroups[], ExemptEmailDomains[]), are not
# touched. Console commands are never asked. Every refusal is in the audit trail
# (access.user.second_factor.refused) and in var/log/auth.log.
#
# disabled: the password alone signs in on these paths as before (not recommended).
Guard=enabled

# The paths guarded. An empty list guards none.
GuardedChannels[]
GuardedChannels[]=rest
GuardedChannels[]=webdav
GuardedChannels[]=soap
GuardedChannels[]=expservices

# Also refuse a password alone for accounts that set up a second step themselves
# (Enforce2FA does not apply to them): enabled | disabled
GuardOptedIn=disabled

# The paths on which the current code of an authenticator app counts as the second
# step: the POST field otp or the request header site.ini [SignInSecondFactorSettings]
# CodeHeader (X-Exp-OTP). Each code works once; [Security] MaxAttempts wrong codes lock
# the code for CodeLockSeconds. The expservices login makes a session, which is why it
# takes a code and not an app password. rest, webdav and soap send their credentials
# with every request, where a code that works once fits badly: use app passwords there.
CodeChannels[]
CodeChannels[]=expservices

# Seconds the authenticator code stays locked after MaxAttempts wrong codes.
CodeLockSeconds=900

[AppPasswords]
# App passwords: a password for one client (a WebDAV drive, a script, an app), made by
# the user on the two-step sign-in page (user2fa/setup) and shown once. Sent in place of
# the account's password, it signs in on the paths it was made for, without the second
# step. Only its salted hash is stored (in the account's two-factor field); the user
# revokes it there, and removing or resetting the second step revokes them all. Making
# one asks for the current code of the authenticator app. Making, revoking and using one
# is in the audit trail (access.user.app_password.create, .revoke, .use).
#
# enabled | disabled (disabled: none are made, and existing ones stop working)
AppPasswords=enabled

# The paths an app password may be made for. Only rest, webdav and soap are possible:
# they sign in one request at a time and never make a session the web interface takes.
Channels[]
Channels[]=rest
Channels[]=webdav
Channels[]=soap

# How many app passwords a user may have (at most 50).
MaxPerUser=10

# Days a new app password works; 0: until it is revoked.
ExpiryDays=365

# Seconds between two writes of "last used" (and its audit record) of an app password.
LastUsedInterval=900

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
