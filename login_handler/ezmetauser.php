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
  \class eZMetaUser ezmetauser.php
  \ingroup sevenx_authentication_2fa
  \brief Meta (Facebook) OAuth 2.0 login handler.
*/
class eZMetaUser extends eZFacebookUser
{
    protected $provider = 'meta';
}
