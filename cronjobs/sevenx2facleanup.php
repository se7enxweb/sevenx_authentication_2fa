<?php
//
// sevenx_authentication_2fa - 7x Two-Factor and Social Authentication extension
// Copyright (C) 1998 - 2026 7x. All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//

/*!
  \file sevenx2facleanup.php
  Cronjob script that removes expired pending 2FA challenges from active sessions.
  Place this in cronjobs.ini as a daily or hourly job.
*/

// @description Remove expired pending 2FA challenges from active sessions.

eZDebug::writeNotice( 'sevenx_authentication_2fa cleanup cronjob started', 'sevenx2facleanup' );

eZSession::start();

sevenxAuthentication2faHelper::authLog( '2fa_cleanup_started', 'cronjob' );
sevenxAuthentication2faHelper::cleanupExpiredSessions();
sevenxAuthentication2faHelper::authLog( '2fa_cleanup_finished', 'cronjob' );

eZDebug::writeNotice( 'sevenx_authentication_2fa cleanup cronjob finished', 'sevenx2facleanup' );
