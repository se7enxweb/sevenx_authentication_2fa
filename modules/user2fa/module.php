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

$Module = array( 'name' => 'user2fa',
                 'variable_params' => true );

$ViewList = array();

$ViewList['verify'] = array(
    'functions' => array( 'verify' ),
    'script' => 'verify.php',
    'params' => array(),
    'ui_context' => 'authentication',
    'unordered_params' => array( 'code' => 'Code' )
);

$ViewList['setup'] = array(
    'functions' => array( 'setup' ),
    'script' => 'setup.php',
    'params' => array(),
    'ui_context' => 'authentication',
    'unordered_params' => array()
);

$ViewList['callback'] = array(
    'functions' => array( 'callback' ),
    'script' => 'callback.php',
    'params' => array( 'provider' ),
    'ui_context' => 'authentication',
    'unordered_params' => array()
);

$ViewList['oauth'] = array(
    'functions' => array( 'oauth' ),
    'script' => 'oauth.php',
    'params' => array( 'provider' ),
    'ui_context' => 'authentication',
    'unordered_params' => array()
);

$FunctionList = array();
$FunctionList['verify'] = array();
$FunctionList['setup'] = array();
$FunctionList['callback'] = array();
$FunctionList['oauth'] = array();
