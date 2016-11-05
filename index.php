<?php

/*
	GitHub Sync (c) Mikey Beck
	https://github.com/mikeybeck/github-sync

	Based on BitBucket Sync (c) Alex Lixandru
	https://bitbucket.org/alixandru/bitbucket-sync

	File: index.php
	Version: 0.2.0
	Description: User interface for GitHub Sync script


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/


/*
	This is empty for now to prevent web-server directory listing. Ideally, 
	this file, along with the other related scripts should be protected by
	Basic Authentication or other security mechanisms to prevent any
	unauthorized use.
*/

ini_set('display_errors','On'); 
ini_set('error_reporting', E_ALL);
ini_set("log_errors", 1);



require_once( 'config.php' );

$config = new Config();

require_once( 'gateway.php' );

$gateway = new Gateway($config);

$gateway->run();


/* Omit PHP closing tag to help avoid accidental output */
