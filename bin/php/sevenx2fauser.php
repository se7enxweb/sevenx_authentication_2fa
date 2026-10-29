#!/usr/bin/env php
<?php
/**
 * sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension
 * Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The second step of one account, from the server's console: for the administrator who lost the phone, and for
 * accounts of automated checks.
 *
 *   php extension/sevenx_authentication_2fa/bin/php/sevenx2fauser.php --login=<login> [--status]
 *       What the account has (method, whether it is confirmed, recovery codes left) and whether Enforce2FA applies.
 *   ... --login=<login> --reset
 *       Removes the second step and unlocks the account after wrong codes (failed logins back to 0). With Enforce2FA
 *       the next sign-in asks for a new setup; the user can then sign in with the password and set it up again.
 *   ... --login=<login> --recovery-codes
 *       A new set of recovery codes for the account's authenticator, printed once (the old ones stop working). Hand
 *       them over the way a temporary password would be.
 *   ... --login=<login> --enrol-totp --secret-file=<path>
 *       Sets up an authenticator for the account with a new secret, written to <path> (mode 0600, must not exist)
 *       and printed nowhere. For the accounts of automated checks, which compute the codes from that file; never
 *       for a person's account, whose secret belongs in their own app only.
 *   ... --enforced
 *       Every account Enforce2FA applies to that has no working second step yet (id and login), and how many
 *       accounts have each method. Run it before switching enforcement on.
 *
 * Every change is written to var/log/auth.log. No secret and no code is ever printed, except the recovery codes
 * that --recovery-codes is asked for.
 *
 * @description Show, reset or set up the second step of one account; list accounts enforcement would stop.
 */

// @description Show, reset or set up the second step of one account; list accounts enforcement would stop.

require_once 'autoload.php';

$script = eZScript::instance(
    array(
        'description' => "sevenx_authentication_2fa: the second step of one account\n" .
                         "Status, reset (lost phone), new recovery codes, an authenticator for automated checks,\n" .
                         "and the accounts Enforce2FA would ask for a setup.\n",
        'use-session' => false,
        'use-modules' => true,
        'use-extensions' => true
    )
);

$script->startup();
$options = $script->getOptions(
    '[login:][user-id:][status][reset][recovery-codes][enrol-totp][secret-file:][enforced]',
    '',
    array(
        'login'          => 'The account by its login',
        'user-id'        => 'The account by its user (object) id',
        'status'         => 'Show the account\'s second step (default)',
        'reset'          => 'Remove the second step and reset the failed logins',
        'recovery-codes' => 'Make a new set of recovery codes and print it once',
        'enrol-totp'     => 'Set up an authenticator with a new secret written to --secret-file (automated checks only)',
        'secret-file'    => 'Where --enrol-totp writes the secret (mode 0600; must not exist)',
        'enforced'       => 'List the accounts Enforce2FA applies to that have no working second step',
    )
);
$script->initialize();

$cli = eZCLI::instance();
$helper = sevenxAuthentication2faHelper::instance();

$fail = function ( $message ) use ( $cli, $script ) {
    $cli->error( 'FAIL ' . $message );
    $script->shutdown( 1 );
};

// Changes are made as the administrator the console runs for, like the other user scripts
$admin = eZUser::fetch( (int)eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) );
if ( $admin instanceof eZUser )
    $admin->loginCurrent();

if ( $options['enforced'] )
{
    $db = eZDB::instance();
    $rows = $db->arrayQuery( 'SELECT contentobject_id, login FROM ezuser ORDER BY contentobject_id' );
    $counts = array( 'totp' => 0, 'email' => 0, 'none' => 0 );
    $missing = array();
    foreach ( $rows as $row )
    {
        $userID = (int)$row['contentobject_id'];
        $user = eZUser::fetch( $userID );
        if ( !$user instanceof eZUser || !$user->isEnabled() || $userID === (int)eZINI::instance()->variable( 'UserSettings', 'AnonymousUserID' ) )
            continue;
        $data = $helper->userData( $userID );
        $active = $data && $data->isActive();
        $counts[$active ? $data->method() : 'none']++;
        if ( !$active && $helper->isEnforcedFor( $user ) )
            $missing[] = sprintf( '%8d  %s%s', $userID, $row['login'], $helper->canStore( $userID ) ? '' : '  (its class has no two-factor field: cannot sign in while enforced)' );
    }
    $cli->output( 'Enforce2FA: ' . ( $helper->isEnforced() ? 'enabled' : 'disabled (listing whom it would apply to)' ) );
    $cli->output( sprintf( 'Accounts with an authenticator: %d, with e-mail codes: %d, with none: %d', $counts['totp'], $counts['email'], $counts['none'] ) );
    if ( !$helper->isEnforced() )
        $cli->output( 'Enforce2FA is off: the list below is empty until it is on (EnforcedGroups[] decides whom it applies to).' );
    $cli->output( sprintf( 'Asked to set up a second step at their next sign-in: %d', count( $missing ) ) );
    foreach ( $missing as $line )
        $cli->output( $line );
    $script->shutdown( 0 );
}

$user = null;
if ( $options['login'] )
    $user = eZUser::fetchByName( $options['login'] );
elseif ( $options['user-id'] )
    $user = eZUser::fetch( (int)$options['user-id'] );
else
    $fail( 'give --login=<login> or --user-id=<id> (or --enforced)' );
if ( !$user instanceof eZUser )
    $fail( 'no such account' );

$userID = (int)$user->attribute( 'contentobject_id' );
$login = $user->attribute( 'login' );
if ( !$helper->canStore( $userID ) )
    $fail( "the class of '$login' has no two-factor field (datatype sevenxauthentication2fa): nothing can be stored" );

if ( $options['reset'] )
{
    $helper->saveUserData( $userID, new sevenxAuthentication2fa() );
    eZUser::setFailedLoginAttempts( $userID, 0, true );
    eZContentCacheManager::clearContentCacheIfNeeded( $userID );
    sevenxAuthentication2faHelper::authLog( '2fa_reset_console', 'second step removed and failed logins reset from the console', $userID );
    $cli->output( "PASS '$login': second step removed, failed logins reset" .
                  ( $helper->isEnforcedFor( $user ) ? '; the next sign-in asks for a new setup' : '' ) );
    $script->shutdown( 0 );
}

if ( $options['recovery-codes'] )
{
    $codes = $helper->issueRecoveryCodes( $userID );
    if ( !$codes )
        $fail( "'$login' has no confirmed authenticator, or RecoveryCodes=0" );
    $cli->output( "New recovery codes of '$login' (the old ones no longer work; each works once):" );
    foreach ( $codes as $code )
        $cli->output( '  ' . $code );
    $script->shutdown( 0 );
}

if ( $options['enrol-totp'] )
{
    $file = (string)$options['secret-file'];
    if ( $file === '' )
        $fail( '--enrol-totp needs --secret-file=<path>' );
    if ( file_exists( $file ) )
        $fail( "$file exists; remove it first" );
    $dir = dirname( $file );
    if ( !is_dir( $dir ) || !is_writable( $dir ) )
        $fail( "$dir is not a writable directory" );
    $settings = $helper->totpSettings();
    $secret = sevenxAuthentication2faTOTP::generateSecret( $settings['bytes'] );
    $old = umask( 0077 );
    $written = file_put_contents( $file, json_encode( array(
        'login' => $login, 'user_id' => $userID, 'secret' => $secret,
        'digits' => $settings['digits'], 'period' => $settings['period'], 'algorithm' => $settings['algorithm'],
    ) ) . "\n", LOCK_EX );
    umask( $old );
    if ( $written === false )
        $fail( "could not write $file" );
    chmod( $file, 0600 );
    // Confirmed at once: the secret never went to a person who would have to type a code
    $helper->saveUserData( $userID, new sevenxAuthentication2fa( 'totp', $secret, true ) );
    sevenxAuthentication2faHelper::authLog( '2fa_enrol_totp_console', 'authenticator set up from the console (automated checks)', $userID );
    $cli->output( "PASS '$login': authenticator set up; its secret is in $file (0600)" );
    $script->shutdown( 0 );
}

// --status (default)
$data = $helper->userData( $userID );
$active = $data && $data->isActive();
$cli->output( "Account: $login (user id $userID)" );
$cli->output( 'Second step: ' . ( $active ? $data->method() : 'none' ) .
              ( $data && $data->method() === 'totp' && !$data->verified() ? ' (an authenticator set up but never confirmed)' : '' ) );
if ( $active && $data->method() === 'totp' )
    $cli->output( 'Recovery codes left: ' . sevenxAuthentication2faPolicy::recoveryCodesLeft( $data->recovery() ) );
$cli->output( 'Enforce2FA applies: ' . ( $helper->isEnforcedFor( $user ) ? 'yes' : 'no' ) );
if ( !$active && $helper->isEnforcedFor( $user ) && $helper->graceDays() > 0 )
    $cli->output( 'Grace period left: ' . (int)ceil( $helper->graceLeft( $userID ) / 86400 ) . ' day(s)' );
$cli->output( 'Failed logins: ' . (int)eZUser::failedLoginAttemptsByUserID( $userID ) );
$script->shutdown( 0 );
