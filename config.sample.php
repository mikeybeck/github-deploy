<?php

/*
	GitHub Sync (c) Mikey Beck
	https://github.com/mikeybeck/github-sync

	Based on BitBucket Sync (c) Alex Lixandru
	https://bitbucket.org/alixandru/bitbucket-sync

	File: config.php
	Version: 0.2.0
	Description: Configuration class for GitHub Sync script


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/

class Config {

	//function __construct() {
	//	error/_log('config.php');
	//}

	const 	
		/**
		 * The location where to temporary store commit data sent by GitHub's
		 * Post Service hook. This is the location from where the deploy script
		 * will read information about what files to synchronize. The folder
		 * must exist on the web server and the process executing both the gateway
		 * script and the deploy script (usually a web server daemon), must have
		 * read and write access to this folder.
		 */
			COMMITS_FOLDER 			= 'commits',
		
		/**
		* Prefix of the temporary files created by the gateway script. This prefix
		* will be used to identify the files from `commitsFolder` which will be
		* used to extract commit information.
		*/
			COMMITS_FILENAME_PREFIX = 'commit-',

		/**
		 * Whether to perform the file synchronization automatically, immediately
		 * after the Post Service Hook is triggered, or leave it for manual deployment.
		 * If set on 'false', synchronization will need to be initiated by invoking
		 * deploy.php via a web browser, or through a cron-job on the web server
		 */
			AUTOMATIC_DEPLOYMENT 	= true, 

		/**
		 * The default branch to use for getting the changed files, if no specific
		 * per-project branch was configured below.
		 */
			DEPLOY_BRANCH 			= 'deploy',

		/** The repo owner's GitHub ID (REQUIRED) */
			REPO_OWNER 				= '',

		/** The ID of an user with read access to project files (NOT required UNLESS repo is private.) */
			API_USER				= '',

		/** The password of {apiUser} account (Not required unless repo is private) */
			API_PASSWORD			= '',

		/** Whether to print operation details. Very useful, especially when setting up projects */
			VERBOSE 				= true,

		/**
		 * If requireAuthentication is set to 'true' a secret value
		 * needs to be provided via an additional "key" URL parameter in script requests.
		 *
		 * While not required, github-sync is potentially left open to control
		 * by strangers should an authentication key not be set.
	 	 *
		 * Keys can be identical, or you can set unique values for each key.
		 *
	 	 * 'deployAuthKey' is typically used in the deploy URL
	 	 * Example: http://example.com/github-sync/deploy.php?key=value
	 	 *
	 	 * 'gatewayAuthKey' is typically used by the Post Service Hook.
	 	 * Example: http://example.com/github-sync/gateway.php?key=value
		 *
		 */
			REQUIRE_AUTHENTICATION 	= false,
		    DEPLOY_AUTH_KEY 		= '',
		    GATEWAY_AUTH_KEY		= '',

		    ADMIN_PASSWORD			= '';       



	/**
	 *
	 * REQUIRED:
	 *
	 * The location where the project files will be deployed when modified in the
	 * BitBucket project, identified by the name of the BitBucket project.
	 * The following pattern is used: [project-name] => [path on the web-server].
	 * This allows multiple BitBucket projects to be deployed to different
	 * locations on the web-server's file system.
	 *
	 * Multiple projects example:
	 *
	 * 	$DEPLOY = array(
	 *		'my-project-name' => '/home/www/site/',
	 *		'my-data' => '/home/www/data/',
	 *		'another-project' => '/home/username/public_html/',
	 *		'user.bitbucket.org' => '/home/www/bbpages/',
	 * 	);
	 *
	 * Make sure all these paths are writable! It is also recommended to use
	 * absolute paths in order to avoid any path issues.
	 */

	//was $DEPLOY
	public $DEPLOY = array(
	    '' => '',
	);

	/**
	 *
	 * OPTIONAL:
	 *
	 * The branch which will be deployed for each project. If no branch is
	 * specified for a project, the value given for {deployBranch} will be used.
	 * The following pattern is used: [project-name] => [branch].
	 *
	 * Multiple projects example:
	 *
	 * 	$DEPLOY_BRANCH = array(
	 * 		'my-project-name' => 'master',
	 * 		'another-project' => 'development',
	 * 	);
	 *
	 */
	//was $DEPLOY_BRANCH
	public $DEPLOY_BRANCH = array(
	 		'' => '',
	);

}


/* Omit PHP closing tag to help avoid accidental output */