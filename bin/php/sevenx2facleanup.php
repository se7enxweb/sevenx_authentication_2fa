#!/usr/bin/env php
<?php
/**
 * sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension
 * Copyright (C) 1998 - 2026 7x. All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Usage:
 *   php extension/sevenx_authentication_2fa/bin/php/sevenx2facleanup.php
 *
 * Removes expired pending 2FA challenges from the current session storage.
 *
 * @description Remove expired pending 2FA challenges from session storage.
 */

// @description Remove expired pending 2FA challenges from session storage.

require_once 'autoload.php';

$script = eZScript::instance(
    array(
        'description' => "sevenx_authentication_2fa session cleanup\n" .
                         "Removes expired pending 2FA challenges from session storage.\n",
        'use-session' => true,
        'use-modules' => false,
        'use-extensions' => true
    )
);

$script->startup();
$script->initialize();

$cli = eZCLI::instance();
$cli->output( 'Cleaning expired sevenx_authentication_2fa session data...' );

sevenxAuthentication2faHelper::authLog( '2fa_cleanup_started', 'cli' );
sevenxAuthentication2faHelper::cleanupExpiredSessions();
sevenxAuthentication2faHelper::authLog( '2fa_cleanup_finished', 'cli' );

$cli->output( 'Cleanup complete.' );
$script->shutdown( 0 );
