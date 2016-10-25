<?php

/*
	BitBucket Sync (c) Alex Lixandru

	https://bitbucket.org/alixandru/bitbucket-sync

	File: deploy.php
	Version: 2.0.0
	Description: Local file sync script for BitBucket projects


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
	the POST service hook in BitBucket and let the script synchronize changes 
	as they happen (through operation mode 1).
	
	
	1. Full synchronization
	
	This mode can be enabled by specifying the "setup" GET parameter in the URL
	in which case the script will get the full repository from BitBucket and
	deploy it locally. This is done by getting a zip archive of the project,
	extracting it locally and copying its contents over to the specified
	project location, on the local file-system.
	
	This operation mode does not necessarily need a POST service hook to be 
	defined in BitBucket for the project and is generally suited for initial 
	set-up of projects that will be kept in sync with this script. 
	
	
	2. Commit synchronization
	
	This is the default mode which is used when the script is accessed with
	no parameters in the URL. In this mode, the script updates only the files
	which have been modified by a commit that was pushed to the repository.
	
	The script reads commit information saved locally by the gateway script
	and attempts to synchronize the local file system with the updates that
	have been made in the BitBucket project. The list of files which have
	been changed (added, updated or deleted) will be taken from the commit
	files. This script tries to optimize the synchronization by not processing 
	files more than once.
	
	When a deployment fails the original commit file is preserved. It is 
	possible to retry processing failed synchronizations by specifying the 
	"retry" GET parameter in the URL.
	
 */


ini_set('display_errors','On'); 
ini_set('error_reporting', E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "/var/www/dev.ibestcreatine.com/htdocs/gh-sync/php-error.log");

error_log('deploy.php');

require_once( 'config.php' );

// For 4.3.0 <= PHP <= 5.4.0
if (!function_exists('http_response_code'))
{
	error_log('deploy.php 1');
    function http_response_code($newcode = NULL)
    {
    	error_log('deploy.php 2');
        static $code = 200;
        if($newcode !== NULL)
        {
        	error_log('deploy.php 3');
            header('X-PHP-Response-Code: '.$newcode, true, $newcode);
            if(!headers_sent())
                $code = $newcode;
            error_log('deploy.php 4');
        }       
        error_log('deploy.php 5');
        return $code;
    }
}

if (!isset($key)) {
	error_log('deploy.php 6');
	if(isset($_GET['key'])) {
		error_log('deploy.php 7');
		$key = strip_tags(stripslashes(urlencode($_GET['key'])));

	} else $key = '';
	error_log('deploy.php 8');
}

if(isset($_GET['setup']) && !empty($_GET['setup'])) {
	error_log('deploy.php 9');
	# full synchronization
	$repo = strip_tags(stripslashes(urldecode($_GET['setup'])));
	syncFull($key, $repo);
	
} else if(isset($_GET['retry'])) {
	error_log('deploy.php 10');
	# retry failed synchronizations
	syncChanges($key, true);
	
} else {
	error_log('deploy.php 11');
	# commit synchronization
	syncChanges($key);
}


/**
 * Gets the full content of the repository and stores it locally.
 * See explanation at the top of the file for details.
 */
function syncFull($key, $repository) {
	error_log('deploy.php 12');
	global $CONFIG, $DEPLOY, $DEPLOY_BRANCH;
	$shouldClean = isset($_GET['clean']) && $_GET['clean'] == 1;

	// check authentication key if authentication is required
	if ( $shouldClean && $CONFIG[ 'deployAuthKey' ] == '' ) {
		error_log('deploy.php 13');
		// when cleaning, the auth key is mandatory, regardless of requireAuthentication flag
		http_response_code(403);
		echo " # Cannot clean right now. A non-empty deploy auth key must be defined for cleaning.";
		return false;
	} else if ( ($CONFIG[ 'requireAuthentication' ] || $shouldClean) && $CONFIG[ 'deployAuthKey' ] != $key ) {
		error_log('deploy.php 14');
		http_response_code(401);
		echo " # Unauthorized." . ($shouldClean && empty($key) ? " The deploy auth key must be provided when cleaning." : "");
		return false;
	}
	
	echo "<pre>\nGithub Sync - Full Deploy\n============================\n";
	
	// determine the destination of the deployment
	if( array_key_exists($repository, $DEPLOY) ) {
		error_log('deploy.php 15');
		$deployLocation = $DEPLOY[ $repository ] . (substr($DEPLOY[ $repository ], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
		error_log('deploy.php 15 deployLocation = ' . $deployLocation);
	} else {
		error_log('deploy.php 16');
		echo " # Unknown repository: $repository!";
		return false;
	}
	
	// determine from which branch to get the data
	if( isset($DEPLOY_BRANCH) && array_key_exists($repository, $DEPLOY_BRANCH) ) {
		error_log('deploy.php 17');
		$deployBranch = $DEPLOY_BRANCH[ $repository ];
	} else {
		error_log('deploy.php 18');
		// use the default branch
		$deployBranch = $CONFIG['deployBranch'];
	}

	error_log('deploy.php 19');

	// build URL to get the full archive
	$baseUrl = 'https://github.com/';
	$repoUrl = (!empty($_GET['team']) ? $_GET['team'] : $CONFIG['apiUser']) . "/$repository/";
	$branchUrl = 'archive/' . $deployBranch . '.zip';

	echo "repoUrl: " . $repoUrl;
	echo "branchUrl: " . $branchUrl;
	
	// store the zip file temporary
	$zipFile = 'full-' . time() . '-' . rand(0, 100);
	$zipLocation = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);

	// get the archive
	loginfo(" * Fetching archive from $baseUrl$repoUrl$branchUrl\n");
	$result = getFileContents($baseUrl . $repoUrl . $branchUrl, $zipLocation . $zipFile);

	// extract contents
	loginfo(" * Extracting archive to $zipLocation\n");
	$zip = new ZipArchive;

	error_log('deploy.php 20');

	if( $zip->open($zipLocation . $zipFile) === true ) {
		error_log('deploy.php 21');
		$zip->extractTo($zipLocation);
		$stat = $zip->statIndex(0); 
		$folder = $stat['name'];
		$zip->close();
	} else {
		error_log('deploy.php 22');
		echo " # Unable to extract files. Is the repository name correct?";
		unlink($zipLocation . $zipFile);
		return false;
	}
	
	error_log('deploy.php 23');
	// validate extracted content
	if( empty($folder) || !is_dir( $zipLocation . $folder ) ) {
		error_log('deploy.php 24');
		echo " # Unable to find the extracted files in $zipLocation\n";
		unlink($zipLocation . $zipFile);
		return false;
	}
	
	error_log('deploy.php 25');
	// delete the old files, if instructed to do so
	if( $shouldClean ) {
		error_log('deploy.php 26');
		loginfo(" * Deleting old content from $deployLocation\n");
		if( deltree($deployLocation) === false ) {
			error_log('deploy.php 27');
			echo " # Unable to completely remove the old files from $deployLocation. Process will continue anyway!\n";
		}
	}
	
	error_log('deploy.php 28');
	// copy the contents over
	loginfo(" * Copying new content to $deployLocation\n");
	if( cptree($zipLocation . $folder, $deployLocation) == false ) {
		error_log('deploy.php 29');
		echo " # Unable to deploy the extracted files to $deployLocation. Deployment is incomplete!\n";
		deltree($zipLocation . $folder, true);
		unlink($zipLocation . $zipFile);
		return false;
	}
	
	error_log('deploy.php 30');
	// clean up
	loginfo(" * Cleaning up temporary files and folders\n");
	//deltree($zipLocation . $folder, true);
	//unlink($zipLocation . $zipFile);
	
	echo "\nFinished deploying $repository.\n</pre>";
}


/**
 * Synchronizes changes from the commit files.
 * See explanation at the top of the file for details.
 */
function syncChanges($key, $retry = false) {
	error_log('deploy.php 31');
	global $CONFIG;
	global $processed;
	global $rmdirs;
	
	// check authentication key if authentication is required
	if ( $CONFIG[ 'requireAuthentication' ] && $CONFIG[ 'deployAuthKey' ] != $key) {
		error_log('deploy.php 32');
		http_response_code(401);
		echo " # Unauthorized";
		return false;
	}

	echo "<pre>\nBitBucket Sync\n==============\n";
	
	$prefix = $CONFIG['commitsFilenamePrefix'];
	if($retry) {
		error_log('deploy.php 33');
		$prefix = "failed-$prefix";
	}
	
	$processed = array();
	$rmdirs = array();
	$location = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	$commits = @scandir($location, 0);

	if($commits)
	error_log('deploy.php 34');
	foreach($commits as $file) {
		error_log('deploy.php 35');
		if( $file != '.' && $file != '..' && is_file($location . $file) 
			&& stripos($file, $prefix) === 0 ) {
			error_log('deploy.php 36');
			// get file contents and parse it
			$json = @file_get_contents($location . $file);
			$del = true;
			echo " * Processing file $file\n";
			if(!$json || !deployChangeSet( $json )) {
				error_log("deploy.php 37 - # Could not process file $file!\n");
				echo " # Could not process file $file!\n";
				$del = false;
			}
			flush();
			
			if($del) {
				error_log('deploy.php 38');
				// delete file afterwards
				unlink( $location . $file );
			} else {
				error_log('deploy.php 39');
				// keep failed file for later processing
				if(!$retry) rename( $location . $file, $location . 'failed-' . $file );
			}
		}
	}
	
	error_log('deploy.php 40');
	// remove old (renamed) directories which are empty
	foreach($rmdirs as $dir => $name) {
		error_log('deploy.php 41');
		if(@rmdir($dir)) {
			error_log('deploy.php 42');
			echo " * Removed empty directory $name\n";
		}
	}
	error_log('deploy.php 43 - finished commits');
	echo "\nFinished processing commits.\n</pre>";
}


/**
 * Deploys commits to the file-system
 */
function deployChangeSet( $postData ) {
	error_log('deploy.php 44');
	global $CONFIG, $DEPLOY, $DEPLOY_BRANCH;
	global $processed;
	global $rmdirs;
	
	$o = json_decode($postData);
	if( !$o ) {
		error_log('deploy.php 45');
		// could not parse ?
		echo "    ! Invalid JSON file\n";
		return false;
	}
	
	// determine the destination of the deployment
	if( array_key_exists($o->repository->name, $DEPLOY) ) {
		error_log('deploy.php 46');
		$deployLocation = $DEPLOY[ $o->repository->name ] . (substr($DEPLOY[ $o->repository->name ], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
		error_log('deploy.php 46 deployLocation = ' . $deployLocation);
		//$deployLocation = $DEPLOY[ $o->repository->name ];
		//error_log('deploy.php 46.5 deployLocation = ' . $deployLocation);

	} else {
		error_log(print_r($o, true));
		error_log('deploy.php 47 - Repo not configured for sync.. (or this may be a bug)');
		// unknown repository ?
		echo "    ! Repository not configured for sync: {$o->repository->name}\n";
		return false;
	}
	
	// determine from which branch to get the data
	if( isset($DEPLOY_BRANCH) && array_key_exists($o->repository->name, $DEPLOY_BRANCH) ) {
		error_log('deploy.php 48');
		$deployBranch = $DEPLOY_BRANCH[ $o->repository->name ];
	} else {
		error_log('deploy.php 49');
		// use the default branch
		$deployBranch = $CONFIG['deployBranch'];
	}

	error_log(print_r($o, true));
	// Determine if correct branch pushed to.  If not, exit.
	// Test if deploy branch name is at end of ref
	error_log("strlen " . strlen($deployBranch));
	$neglength = strlen($deployBranch) * -1;
	error_log("strlenneg " . $neglength);

	if (substr($o->ref, $neglength - 1) === "/" . $deployBranch) {
		error_log("DEPLOY_BRANCH " . $deployBranch);
		error_log("O->ref: " . $o->ref);

	} else {
		error_log("DEPLOY_BRANCH " . $deployBranch);
		error_log("O->ref: " . $o->ref);
		error_log('exiting!');
		exit;
	}

	
	error_log('deploy.php 50');
	//URL looks something like: https://raw.githubusercontent.com/mikeybeck/test-deploy/master/

	// build URL to get the updated files
	//$baseUrl = $o->canon_url;                       # https://bitbucket.org
	//$apiUrl = '/api/1.0/repositories';              # /api/1.0/repositories
	//$repoUrl = $o->repository->absolute_url;        # /user/repo/
	//$rawUrl = 'raw/';							      # raw/
	//$branchUrl = $deployBranch . '/';     		  # branch/

	$baseUrl = "https://raw.githubusercontent.com";
	$apiUrl = '/';
	$repoUrl = $o->repository->full_name;           # mikeybeck/test-deploy
	$rawUrl = '/';
	$branchUrl = $deployBranch . "/";


	error_log('deploy.php 50.1 URL: ' . $baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl);
	
	// prepare to get the files
	$pending_add = array();
	$pending_rem = array();
	$pending_mod = array();
	
	// loop through commits
	error_log('deploy.php 50.1.1 commits: ' . print_r($o->commits, true));
	foreach($o->commits as $commit) {
		error_log('deploy.php 50.1.2 commit: ' . print_r($commit, true));
		// check if the branch is known at this step
		loginfo("    > Change-set: " . trim($commit->message) . "\n");
		//if(!empty($commit->branch) || !empty($commit->branches)) {
			//error_log('deploy.php 50.1.3 commit branch not empty :)');
			// if commit was on the branch we're watching, deploy changes
			//if( $commit->branch == $deployBranch || 
			//		(!empty($commit->branches) && array_search($deployBranch, $commit->branches) !== false)) {
				// if there are any pending files, merge them in
				//$files = array_merge($pending, $commit->files);
				$files_added = array_merge($pending_add, $commit->added);
				$files_removed = array_merge($pending_rem, $commit->removed);
				$files_modified = array_merge($pending_mod, $commit->modified);
				error_log('deploy.php 50.1.4 commit files added: ' . print_r($files_added, true));
				error_log('deploy.php 50.1.4 commit files removed: ' . print_r($files_removed, true));
				error_log('deploy.php 50.1.4 commit files modified: ' . print_r($files_modified, true));

				function add_mod_file($file) {
					if( empty($processed[$file]) ) {
						$processed[$file] = 1; // mark as processed
						$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						if( $contents == 'Not Found' ) {
							error_log('deploy.php 50.2 contents not found: ' . $file);
							// try one more time, BitBucket gets weirdo sometimes
							$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						}
						
						if( $contents != 'Not Found' && $contents !== false ) {
							if( !is_dir( dirname($deployLocation . $file) ) ) {
								// attempt to create the directory structure first
								mkdir( dirname($deployLocation . $file), 0755, true );
							}
							file_put_contents( $deployLocation . $file, $contents );
							loginfo("      - Synchronized $file\n");
							error_log('deploy.php 50.3 file synced: ' . $file);
							
						} else {
							echo "      ! Could not get file contents for $file\n";
							error_log('deploy.php 50.4 could not get file contents: ' . $file);
							flush();
						}
					}
				}

				function remove_file($file) {
					unlink( $deployLocation . $file );
					$processed[$file] = 0; // to allow for subsequent re-creating of this file
					$rmdirs[dirname($deployLocation . $file)] = dirname($file);
					loginfo("      - Removed $file\n");
					error_log('deploy.php 50.5 file removed: ' . $file);
				}

				foreach ($files_added as $file) {
					//add_mod_file($file_added);
					if( empty($processed[$file]) ) {
						$processed[$file] = 1; // mark as processed
						$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						if( $contents == 'Not Found' ) {
							error_log('deploy.php 50.2 contents not found: ' . $file);
							// try one more time, BitBucket gets weirdo sometimes
							$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						}
						
						if( $contents != 'Not Found' && $contents !== false ) {
							if( !is_dir( dirname($deployLocation . $file) ) ) {
								// attempt to create the directory structure first
								mkdir( dirname($deployLocation . $file), 0755, true );
							}
							file_put_contents( $deployLocation . $file, $contents );
							loginfo("      - Synchronized $file\n");
							error_log('deploy.php 50.3 file synced: ' . $file);
							
						} else {
							echo "      ! Could not get file contents for $file\n";
							error_log('deploy.php 50.4 could not get file contents: ' . $file);
							flush();
						}
					}
				}
				foreach ($files_modified as $file) {
					//add_mod_file($file_modded);
					if( empty($processed[$file]) ) {
						$processed[$file] = 1; // mark as processed
						$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						if( $contents == 'Not Found' ) {
							error_log('deploy.php 50.2 contents not found: ' . $file);
							// try one more time, BitBucket gets weirdo sometimes
							$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						}
						
						if( $contents != 'Not Found' && $contents !== false ) {
							if( !is_dir( dirname($deployLocation . $file) ) ) {
								// attempt to create the directory structure first
								mkdir( dirname($deployLocation . $file), 0755, true );
							}
							file_put_contents( $deployLocation . $file, $contents );
							loginfo("      - Synchronized $file\n");
							error_log('deploy.php 50.3 file synced: ' . $file);
							
						} else {
							echo "      ! Could not get file contents for $file\n";
							error_log('deploy.php 50.4 could not get file contents: ' . $file);
							flush();
						}
					}
				}
				foreach ($files_removed as $file) {
					//remove_file($file_removed);
					unlink( $deployLocation . $file );
					$processed[$file] = 0; // to allow for subsequent re-creating of this file
					$rmdirs[dirname($deployLocation . $file)] = dirname($file);
					loginfo("      - Removed $file\n");
					error_log('deploy.php 50.5 file removed: ' . $file);
				}

				/*
				// get a list of files
				foreach($files as $file) {
					if( $file->type == 'modified' || $file->type == 'added' ) {
						if( empty($processed[$file->file]) ) {
							$processed[$file->file] = 1; // mark as processed
							$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file->file);
							if( $contents == 'Not Found' ) {
								error_log('deploy.php 50.2 contents not found: ' . $file->file);
								// try one more time, BitBucket gets weirdo sometimes
								$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file->file);
							}
							
							if( $contents != 'Not Found' && $contents !== false ) {
								if( !is_dir( dirname($deployLocation . $file->file) ) ) {
									// attempt to create the directory structure first
									mkdir( dirname($deployLocation . $file->file), 0755, true );
								}
								file_put_contents( $deployLocation . $file->file, $contents );
								loginfo("      - Synchronized $file->file\n");
								error_log('deploy.php 50.3 file synced: ' . $file->file);
								
							} else {
								echo "      ! Could not get file contents for $file->file\n";
								error_log('deploy.php 50.4 could not get file contents: ' . $file->file);
								flush();
							}
						}
						
					} else if( $file->type == 'removed' ) {
						unlink( $deployLocation . $file->file );
						$processed[$file->file] = 0; // to allow for subsequent re-creating of this file
						$rmdirs[dirname($deployLocation . $file->file)] = dirname($file->file);
						loginfo("      - Removed $file->file\n");
						error_log('deploy.php 50.5 file removed: ' . $file->file);
					}
				} */
			//}
			
			// clean pending files, if any
			$pending_add = array();
			$pending_rem = array();
			$pending_mod = array();
		
		//} else {
			// unknown branch for now, keep these files
			//$pending = array_merge($pending, $commit->files);
		//	$files_added = array_merge($pending_add, $commit->added);
		//	$files_removed = array_merge($pending_rem, $commit->removed);
		//	$files_modified = array_merge($pending_mod, $commit->modified);
		//}
	}
	
	return true;
}

error_log('deploy.php 51');

/**
 * Gets a remote file contents using CURL
 */
function getFileContents($url, $writeToFile = false) {

	error_log('deploy.php 52');
	global $CONFIG;
	
	// create a new cURL resource
	$ch = curl_init();
	
	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, $url);
	
	curl_setopt($ch, CURLOPT_HEADER, false);

    if ($writeToFile) {
        $out = fopen($writeToFile, "wb");
        if ($out == FALSE) {
            throw new Exception("Could not open file `$writeToFile` for writing");
        }
        curl_setopt($ch, CURLOPT_FILE, $out);
    } else {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	if(!empty($CONFIG['apiUser'])) {
		curl_setopt($ch, CURLOPT_USERPWD, $CONFIG['apiUser'] . ':' . $CONFIG['apiPassword']);
	}
	// Remove to leave curl choose the best version
	//curl_setopt($ch, CURLOPT_SSLVERSION,3); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
	
	// grab URL
	$data = curl_exec($ch);
	
	if(curl_errno($ch) != 0) {
		echo "      ! File transfer error: " . curl_error($ch) . "\n";
	}
	
	// close cURL resource, and free up system resources
	curl_close($ch);
	
	return $data;
}


/**
 * Copies the directory contents, recursively, to the specified location
 */
function cptree($dir, $dst) {
	error_log('deploy.php 53');
	if (!file_exists($dst)) if(!mkdir($dst, 0755, true)) return false;
	if (!is_dir($dir) || is_link($dir)) return copy($dir, $dst); // should not happen
	$files = array_diff(scandir($dir), array('.','..'));
	$sep = (substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	$dsp = (substr($dst, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	foreach ($files as $file) {
		(is_dir("$dir$sep$file")) ? cptree("$dir$sep$file", "$dst$dsp$file") : copy("$dir$sep$file", "$dst$dsp$file");
	}
	return true;
}


/**
 * Deletes a directory recursively, no matter whether it is empty or not
 */
function deltree($dir, $deleteParent = false) {
	error_log('deploy.php 54');
	if (!file_exists($dir)) return false;
	if (!is_dir($dir) || is_link($dir)) return unlink($dir);
	// prevent deletion of current directory
	$cdir = realpath($dir);
	$adir = dirname(__FILE__);
	$cdir = $cdir . (substr($cdir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	$adir = $adir . (substr($adir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	if( $cdir == $adir ) {
		loginfo(" * Contents of '" . basename($adir) . "' folder will not be cleaned up.\n");
		return true;
	}
	// process contents of this dir
	$files = array_diff(scandir($dir), array('.','..'));
	$sep = (substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	foreach ($files as $file) {
		(is_dir("$dir$sep$file")) ? deltree("$dir$sep$file", true) : unlink("$dir$sep$file");
	}

	if($deleteParent) {
		return rmdir($dir);
	} else {
		return true;
	}
}


/**
 * Outputs some information to the screen if verbose mode is enabled
 */
function loginfo($message) {
	global $CONFIG;
	if( $CONFIG['verbose'] ) {
		echo $message;
		flush();
	}
}
