<?php

/*
	BitBucket Sync (c) Alex Lixandru

	https://bitbucket.org/alixandru/bitbucket-sync

	File: gateway.php
	Version: 1.0.0
	Description: Service hook handler for BitBucket projects


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
	This script accepts commit information from BitBucket. Commit information 
	is automatically posted by BitBucket after each push to a repository, 
	through its Post Service Hook. For details on how to setup a service hook, 
	see https://confluence.atlassian.com/display/BITBUCKET/POST+hook+management
 */

require_once( 'config.php' );

error_log('gateway.php');

// For 4.3.0 <= PHP <= 5.4.0
if (!function_exists('http_response_code'))
{

	error_log('gateway.php 1');
    function http_response_code($newcode = NULL)
    {
    	error_log('gateway.php 2');
        static $code = 200;
        if($newcode !== NULL)
        {
            header('X-PHP-Response-Code: '.$newcode, true, $newcode);
            if(!headers_sent())
                $code = $newcode;
            	error_log('gateway.php 3');
        }       
        error_log('gateway.php 4');
        return $code;
    }
}

$file = $CONFIG['commitsFilenamePrefix'] . time() . '-' . rand(0, 100);
$location = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == '/' ? '' : '/');

error_log('gateway.php 5');

// Parse auhentication key from request
if(isset($_GET['key'])) {
	$key = strip_tags(stripslashes(urlencode($_GET['key'])));
	error_log('gateway.php 6');

} else $key = '';

error_log('gateway.php 7');

// check authentication key if authentication is required
if ( !$CONFIG['requireAuthentication'] || $CONFIG[ 'requireAuthentication' ] && $CONFIG[ 'gatewayAuthKey' ] == $key) {
	error_log('gateway.php 8');
	if(!empty($_POST['payload'])) {
		error_log('gateway.php 9');
		// store commit data
		if (get_magic_quotes_gpc()) {
			error_log('gateway.php 10');
			file_put_contents( $location . $file, stripslashes($_POST['payload']));
		} else {
			error_log('gateway.php 11');
			file_put_contents( $location . $file, $_POST['payload']);
		}
		
		error_log('gateway.php 12');
		// process the commit data right away
		if($CONFIG['automaticDeployment']) {
				error_log('gateway.php 13');
				$key = $CONFIG['deployAuthKey'];
				require_once( 'deploy.php' );
		}

		error_log('gateway.php 14');
		
	} else if(isset($_GET['test'])) {
		error_log('gateway.php 15');
		if(file_put_contents( $location . 'test', 'Files can be created by the gateway script.') === false) {
			error_log('gateway.php 16');
			echo "This script does not have access to create files in $location";
		} else {
			error_log('gateway.php 17');
			echo "Files can be created by this script in $location";
		}
	}
}
else http_response_code(401);
error_log('gateway.php 18 - 401');

/* Omit PHP closing tag to help avoid accidental output */
