{* The social login buttons of a login or registration page: one link per provider that sevenxauthentication2fa.ini
   enables ([SocialLogin] Enabled and the provider's own Enabled), nothing at all when none is. Each link starts
   user2fa/oauth/<provider>; RedirectURI says where to go after signing in (the view checks it by the safe
   redirect rules).

   Variables: context ('login', the default, or 'register': the heading), redirect (optional, a path).
   The markup is .exp-2fa-social; a design draws it with its own stylesheet (admin: user2fa/exp_style.tpl, media:
   user2fa/parts/style.tpl). Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{if and( ezini_hasvariable( 'SocialLogin', 'Enabled', 'sevenxauthentication2fa.ini' ), eq( ezini( 'SocialLogin', 'Enabled', 'sevenxauthentication2fa.ini' ), 'enabled' ) )}
{def $tfa_providers = array()
     $tfa_known = array( hash( 'key', 'google',    'block', 'Google',    'name', 'Google',    'mark', 'G' ),
                         hash( 'key', 'facebook',  'block', 'Facebook',  'name', 'Facebook',  'mark', 'f' ),
                         hash( 'key', 'meta',      'block', 'Meta',      'name', 'Meta',      'mark', 'M' ),
                         hash( 'key', 'twitter',   'block', 'TwitterX',  'name', 'X',         'mark', 'X' ),
                         hash( 'key', 'instagram', 'block', 'Instagram', 'name', 'Instagram', 'mark', 'I' ),
                         hash( 'key', 'idme',      'block', 'Idme',      'name', 'ID.me',     'mark', 'ID' ) )
     $tfa_suffix = cond( and( is_set( $redirect ), $redirect, ne( $redirect, '/' ) ), concat( '?RedirectURI=', $redirect|urlencode ), '' )}
{foreach $tfa_known as $tfa_p}
    {if and( ezini_hasvariable( $tfa_p.block, 'Enabled', 'sevenxauthentication2fa.ini' ), eq( ezini( $tfa_p.block, 'Enabled', 'sevenxauthentication2fa.ini' ), 'enabled' ) )}
        {set $tfa_providers = $tfa_providers|append( hash( 'key', $tfa_p.key, 'mark', $tfa_p.mark,
              'name', cond( ezini_hasvariable( $tfa_p.block, 'DisplayName', 'sevenxauthentication2fa.ini' ), ezini( $tfa_p.block, 'DisplayName', 'sevenxauthentication2fa.ini' ), $tfa_p.name ) ) )}
    {/if}
{/foreach}
{if $tfa_providers|count}
<nav class="exp-2fa-social" aria-label="{'Social login'|i18n( 'extension/sevenx_authentication_2fa' )|wash}">
    <p class="exp-2fa-or">{if eq( first_set( $context, 'login' ), 'register' )}{'Or register with'|i18n( 'extension/sevenx_authentication_2fa' )}{else}{'Or sign in with'|i18n( 'extension/sevenx_authentication_2fa' )}{/if}</p>
    <ul>
    {foreach $tfa_providers as $tfa_p}
        <li><a href={concat( 'user2fa/oauth/', $tfa_p.key, $tfa_suffix )|ezurl}><span class="exp-2fa-mark" aria-hidden="true">{$tfa_p.mark|wash}</span> {'Continue with %provider'|i18n( 'extension/sevenx_authentication_2fa',, hash( '%provider', $tfa_p.name ) )|wash}</a></li>
    {/foreach}
    </ul>
</nav>
{/if}
{undef $tfa_providers $tfa_known $tfa_suffix}
{/if}
