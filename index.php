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
	This script has two modes of operation detailed below.
	
	The two modes of operation are complementary and are designed to be used
	with projects that are configured to be kept in sync through this script. 
	
	The usual way of getting the project prepared is to make an initial full 
	sync of the	project files (through operation mode 2) and then to configure
	the POST service hook in GitHub and let the script synchronize changes 
	as they happen (through operation mode 1).
	
	
	1. Full synchronization
	
	This mode can be enabled by specifying the "setup" GET parameter in the URL
	in which case the script will get the full repository from GitHub and
	deploy it locally. This is done by getting a zip archive of the project,
	extracting it locally and copying its contents over to the specified
	project location, on the local file-system.
	
	This operation mode does not necessarily need a POST service hook to be 
	defined in GitHub for the project and is generally suited for initial 
	set-up of projects that will be kept in sync with this script. 
	
	
	2. Commit synchronization
	
	This is the default mode which is used when the script is accessed with
	no parameters in the URL. In this mode, the script updates only the files
	which have been modified by a commit that was pushed to the repository.
	
	The script reads commit information saved locally by the gateway script
	and attempts to synchronize the local file system with the updates that
	have been made in the GitHub project. The list of files which have
	been changed (added, updated or deleted) will be taken from the commit
	files. This script tries to optimize the synchronization by not processing 
	files more than once.
	
	When a deployment fails the original commit file is preserved. It is 
	possible to retry processing failed synchronizations by specifying the 
	"retry" GET parameter in the URL.
	
 */

/*
	Ideally this file, along with the other related scripts should be protected by
	Basic Authentication or other security mechanisms to prevent any
	unauthorized use.
*/

ini_set('display_errors','On');
ini_set('error_reporting', E_ALL);		
ini_set("log_errors", 1);		
		
ini_set("error_log", "php-error.log");


require_once( 'config.php' );

$config = new Config();

$key = '';
if (isset($_GET['key']) && !empty($_GET['key'])) {
	$key = strip_tags(stripslashes(urlencode($_GET['key'])));
}

$setupRepo = '';
if (isset($_GET['setup'])) {
	$setupRepo = strip_tags(stripslashes(urlencode($_GET['setup'])));
	require_once( 'deploy.php' );
	$deploy = new Deploy($config);
	$deploy->run($setupRepo, $key);
}



// convenient shortcut to gateway.php file
require_once( 'gateway.php' );

$gateway = new Gateway($config);

$gateway->run($setupRepo, $key);


/* Omit PHP closing tag to help avoid accidental output */
