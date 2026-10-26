{*
    The sign-in code mail of two-step verification (sevenxAuthentication2faEmail::sendCode()), sent to the account's
    address after the password was right. Essential mail (category security).

    Variables: code (the one-time code), expires (minutes), verify_url (the link that finishes the sign-in, absolute;
    it works only in the browser that typed the password), login_url (the sign-in page, absolute), site_url (the
    address the mail was sent from), site_name, user (eZUser).
    Sets subject, content_type (text/html) and body_text (the plain-text part).

    The look is the kernel's design:mail/layout.tpl (HTML) and design:mail/layout_text.tpl (text); this template only
    passes text and links, which the layout washes. With a kernel without that layout, the sender uses
    mail/2fa_code_plain.tpl instead.
*}
{def $tf_site = first_set( $site_name, ezini( 'SiteSettings', 'SiteName' ) )
     $tf_login = first_set( $login_url, '' )
     $tf_buttons = array( hash( 'label', 'Continue signing in'|i18n( 'extension/sevenx_authentication_2fa' ), 'url', $verify_url, 'kind', 'primary' ) )
     $tf_links = array()
     $tf_outro = array( 'If you did not try to sign in, you can ignore this mail: nobody can sign in without this code. Someone may know your password, though, so change it soon.'|i18n( 'extension/sevenx_authentication_2fa' ),
                        'Never share this code. We will never ask you for it.'|i18n( 'extension/sevenx_authentication_2fa' ) )
     $tf_title = 'Your sign-in code'|i18n( 'extension/sevenx_authentication_2fa' )
     $tf_intro = 'Use this code to finish signing in to %site.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%site', $tf_site ) )
     $tf_label = 'Sign-in code'|i18n( 'extension/sevenx_authentication_2fa' )
     $tf_text = 'Enter it on the sign-in page, or open the link to continue. The link works only in the browser where you entered your password.'|i18n( 'extension/sevenx_authentication_2fa' )
     $tf_notice = 'The code expires in %minutes minutes.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%minutes', $expires ) )
     $tf_note = 'If the button does not work, copy this address into your browser: %url'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%url', $verify_url ) )
     $tf_footer = 'You get this mail because two-step verification is turned on for your account on %site.'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%site', $tf_site ) )}
{if $tf_login|ne( '' )}
    {set $tf_links = array( hash( 'label', 'Sign-in page'|i18n( 'extension/sevenx_authentication_2fa' ), 'url', $tf_login ) )}
{/if}
{set-block scope=root variable=subject}{'Your sign-in code for %site: %code'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%site', $tf_site, '%code', $code ) )}{/set-block}
{set-block scope=root variable=content_type}text/html{/set-block}
{set-block scope=root variable=body_text}{include uri='design:mail/layout_text.tpl'
    mail_title=$tf_title mail_intro=$tf_intro mail_code=$code mail_code_label=$tf_label mail_paragraphs=array( $tf_text )
    mail_notice=$tf_notice mail_buttons=$tf_buttons mail_links=$tf_links mail_outro=$tf_outro mail_footer=$tf_footer
    mail_site_name=$tf_site}{/set-block}
{include uri='design:mail/layout.tpl'
    mail_title=$tf_title mail_intro=$tf_intro mail_code=$code mail_code_label=$tf_label mail_paragraphs=array( $tf_text )
    mail_notice=$tf_notice mail_buttons=$tf_buttons mail_links=$tf_links mail_links_note=$tf_note mail_outro=$tf_outro
    mail_footer=$tf_footer mail_site_name=$tf_site mail_preheader=concat( $tf_label, ': ', $code )}
{undef $tf_site $tf_login $tf_buttons $tf_links $tf_outro $tf_title $tf_intro $tf_label $tf_text $tf_notice $tf_note $tf_footer}
