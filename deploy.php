
<?php
/*
	Github Deploy (c) Mikey Beck
	https://github.com/mikeybeck/github-deploy

	Based on BitBucket Sync (c) Alex Lixandru
	https://bitbucket.org/alixandru/bitbucket-sync

	File: deploy.php
	Version: 0.3.0
	Description: Deploy class for GitHub projects

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/



Class Deploy {

	function __construct($config) {
        $this->config = $config;
    }

    function http_response_code($newcode = NULL)     {
        static $code = 200;
        if($newcode !== NULL)         {
            header('X-PHP-Response-Code: '.$newcode, true, $newcode);
            if(!headers_sent())
                $code = $newcode;
        }
        return $code;
    }

    function run($setupRepo = '', $key = '') {

    	$config = $this->config;

//		if (!isset($key)) {
			if(isset($key) && !empty($key)) {
				$key = strip_tags(stripslashes(urlencode($key)));
			}
//		}

		if(isset($setupRepo) && !empty($setupRepo)) {
			# full synchronization
			$repo = strip_tags(stripslashes(urldecode($setupRepo)));
			$this->syncFull($config, $key, $repo);

		} else if(isset($_GET['retry'])) {
			# retry failed synchronizations
			$this->syncChanges($config, $key, true);

		} else {
			# commit synchronization
			$this->syncChanges($config, $key);
		}
	}



		/**
		 * Gets the full content of the repository and stores it locally.
		 * See explanation at the top of the file for details.
		 */
		function syncFull($config, $key, $repository) {
			//global $CONFIG, $DEPLOY, $DEPLOY_BRANCH; //Dont think these are needed any more?
			$deploy = $config->DEPLOY;
			$deploy_branch = $config->DEPLOY_BRANCH;

			$shouldClean = isset($_GET['clean']) && $_GET['clean'] == 1;

			// check authentication key if authentication is required
			if ( $shouldClean && $config::DEPLOY_AUTH_KEY == '' ) {
				// when cleaning, the auth key is mandatory, regardless of requireAuthentication flag
				http_response_code(403);
				echo " # Cannot clean right now. A non-empty deploy auth key must be defined for cleaning.";
				$this->loginfo($config::VERBOSE, " Cannot clean right now. A non-empty deploy auth key must be defined for cleaning.");
				return false;
			} else if ( ($config::REQUIRE_AUTHENTICATION || $shouldClean) && $config::DEPLOY_AUTH_KEY != $key ) {
				http_response_code(401);
				echo " # Unauthorized." . ($shouldClean && empty($key) ? " The deploy auth key must be provided when cleaning." : "");
				$this->loginfo($config::VERBOSE, " # Unauthorized." . " The deploy auth key must be provided when cleaning." );
				return false;
			}

			echo "<pre>\nGithub Deploy - Full Deploy\n============================\n";

			// determine the destination of the deployment
			if( array_key_exists($repository, $deploy) ) {
				$deployLocation = $deploy[ $repository ] . (substr($deploy[ $repository ], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			} else {
				echo " # Unknown repository: $repository!";
				$this->loginfo($config::VERBOSE, " # Unknown repository: $repository!" );
				return false;
			}

			// determine from which branch to get the data
			if( isset($deploy_branch_arr) && array_key_exists($o->repository->name, $deploy_branch_arr) ) {
				$deploy_branch = $deploy_branch_arr[ $o->repository->name ];
			} else {
				// Use default branch
				$deploy_branch = $config::DEPLOY_BRANCH;
			}


			// build URL to get the full archive
			$baseUrl = 'https://github.com/';
			$repoUrl = (!empty($_GET['team']) ? $_GET['team'] : $config::REPO_OWNER) . "/$repository/";
			$branchUrl = 'archive/' . $deploy_branch . '.zip';

			echo "repoUrl: " . $repoUrl . "\n";
			echo "branchUrl: " . $branchUrl . "\n";

			// store the zip file temporary
			$zipFile = 'full-' . time() . '-' . rand(0, 100);
			$zipLocation = $config::COMMITS_FOLDER . (substr($config::COMMITS_FOLDER, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);

			// get the archive
			$this->loginfo($config::VERBOSE, " * Fetching archive from $baseUrl$repoUrl$branchUrl\n");
			$result = $this->getFileContents($config, $baseUrl . $repoUrl . $branchUrl, $zipLocation . $zipFile);

			// extract contents
			$this->loginfo($config::VERBOSE, " * Extracting archive to $zipLocation\n");
			$zip = new ZipArchive;

			if( $zip->open($zipLocation . $zipFile) === true ) {
				$zip->extractTo($zipLocation);
				$stat = $zip->statIndex(0);
				$folder = $stat['name'];
				$zip->close();
			} else {
				echo " # Unable to extract files. Is the repository name correct?";
				$this->loginfo($config::VERBOSE, " # Unable to extract files. Is the repository name correct?");
				unlink($zipLocation . $zipFile);
				return false;
			}

			// validate extracted content
			if( empty($folder) || !is_dir( $zipLocation . $folder ) ) {
				echo " # Unable to find the extracted files in $zipLocation\n";
				$this->loginfo($config::VERBOSE, " # Unable to find the extracted files in $zipLocation");
				unlink($zipLocation . $zipFile);
				return false;
			}

			// delete the old files, if instructed to do so
			if( $shouldClean ) {
				$this->loginfo($config::VERBOSE, " * Deleting old content from $deployLocation\n");
				if( $this->deltree($deployLocation) === false ) {
					echo " # Unable to completely remove the old files from $deployLocation. Process will continue anyway!\n";
					$this->loginfo($config::VERBOSE, " # Unable to completely remove the old files from $deployLocation. Process will continue anyway!");
				}
			}

			// copy the contents over
			$this->loginfo($config::VERBOSE, " * Copying new content to $deployLocation\n");
			if( $this->cptree($zipLocation . $folder, $deployLocation) == false ) {
				echo " # Unable to deploy the extracted files to $deployLocation. Deployment is incomplete!\n";
				$this->loginfo($config::VERBOSE, " # Unable to deploy the extracted files to $deployLocation. Deployment is incomplete!");
				$this->deltree($zipLocation . $folder, true);
				unlink($zipLocation . $zipFile);
				return false;
			}

			// clean up
			$this->loginfo($config::VERBOSE, " * Cleaning up temporary files and folders\n");
			$this->deltree($zipLocation . $folder, true);
			unlink($zipLocation . $zipFile);

			echo "\nFinished deploying $repository.\n</pre>";
			$this->loginfo($config::VERBOSE, " Finished deploying $repository.");
		}


		/**
		 * Synchronizes changes from the commit files.
		 * See explanation at the top of the file for details.
		 */
		function syncChanges($config, $key, $retry = false) {
			//global $CONFIG;
			global $processed;
			global $rmdirs;

			// check authentication key if authentication is required
			if ( $config::REQUIRE_AUTHENTICATION && $config::DEPLOY_AUTH_KEY != $key) {
				http_response_code(401);
				echo " # Unauthorized";
				$this->loginfo($config::VERBOSE, " # Unauthorized");
				return false;
			}

			echo "<pre>\nGithub Deploy\n==============\n";

			$prefix = $config::COMMITS_FILENAME_PREFIX;
			if($retry) {
				$prefix = "failed-$prefix";
			}

			$processed = array();
			$rmdirs = array();
			$location = $config::COMMITS_FOLDER . (substr($config::COMMITS_FOLDER, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			$commits = @scandir($location, 0);

			if($commits)
			foreach($commits as $file) {
				if( $file != '.' && $file != '..' && is_file($location . $file)
					&& stripos($file, $prefix) === 0 ) {
					// get file contents and parse it
					$json = @file_get_contents($location . $file);
					$del = true;
					echo " * Processing file $file\n";
					if(!$json || !$this->deployChangeSet( $config, $json )) {
						echo " # Could not process file $file!\n";
						$this->loginfo($config::VERBOSE, " # Could not process file $file!");
						$del = false;
					}
					flush();

					if($del) {
						// delete file afterwards
						unlink( $location . $file );
					} else {
						// keep failed file for later processing
						if(!$retry) rename( $location . $file, $location . 'failed-' . $file );
					}
				}
			}

			// remove old (renamed) directories which are empty
			foreach($rmdirs as $dir => $name) {
				if(@rmdir($dir)) {
					echo " * Removed empty directory $name\n";
					$this->loginfo($config::VERBOSE, " * Removed empty directory $name");
				}
			}
			echo "\nFinished processing commits.\n</pre>";
			$this->loginfo($config::VERBOSE, " Finished processing commits.");
		}


		/**
		 * Deploys commits to the file-system (i.e. to the commit directory)
		 */
		function deployChangeSet( $config, $postData ) {
			//global $CONFIG, $DEPLOY, $DEPLOY_BRANCH;
			global $processed;
			global $rmdirs;

			$deploy = $config->DEPLOY;
			$deploy_branch = '';
			$deploy_branch_arr = $config->DEPLOY_BRANCH;

			$o = json_decode($postData);
			if( !$o ) {
				// could not parse ?
				echo "    ! Invalid JSON file\n";
				$this->loginfo($config::VERBOSE, " ! Invalid JSON file");
				return false;
			}

			// determine the destination of the deployment
			if( array_key_exists($o->repository->name, $deploy) ) {
				$deployLocation = $deploy[ $o->repository->name ] . (substr($deploy[ $o->repository->name ], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);

			} else {
				// unknown repository ?
				echo "    ! Repository not configured for sync: {$o->repository->name}\n";
				$this->loginfo($config::VERBOSE, " ! Repository not configured for sync: {$o->repository->name}");
				return false;
			}

			// determine from which branch to get the data
			if( isset($deploy_branch_arr) && array_key_exists($o->repository->name, $deploy_branch_arr) ) {
				$deploy_branch = $deploy_branch_arr[ $o->repository->name ];
			} else {
				// Use default branch
				$deploy_branch = $config::DEPLOY_BRANCH;
			}


			// Determine if correct branch pushed to.  If not, exit.
			// Test if deploy branch name is at end of ref
			$neglength = strlen($deploy_branch) * -1;

			if (isset($o->ref)) {
				if (substr($o->ref, $neglength - 1) === "/" . $deploy_branch) {
					//Branch name ok, do nothing
				} else {
					$this->loginfo($config::VERBOSE, " 'exiting! Incorrect branch'\n");
					$this->loginfo($config::VERBOSE, '$ o->ref: ' . $o->ref);
					$this->loginfo($config::VERBOSE, '$ deploy_branch: ' . $deploy_branch);
					$this->loginfo($config::VERBOSE, print_r($o, true));

					exit;
				}
			}


			//URL looks something like: https://raw.githubusercontent.com/mikeybeck/test-deploy/master/ - this one doesn't seem to have the api limits
			// OR https://api.github.com/repos/mikeybeck/repo-name/contents/wp-links-opml3.php?ref=branch-name - this one is limited to files <1mb

			// build URL to get the updated files
			//$baseUrl = "https://api.github.com/repos";
			//$apiUrl = '/';
			//$repoUrl = $o->repository->full_name;           # repo-owner/repo-name
			//$rawUrl = '/contents';
			//$branchUrl = "/";

			$baseUrl = "https://raw.githubusercontent.com";
			$apiUrl = '/';
			$repoUrl = $o->repository->full_name;           # repo-owner/repo-name
			$rawUrl = '/';
			$branchUrl = $deploy_branch . "/";


			// prepare to get the files
			$pending_add = array();
			$pending_rem = array();
			$pending_mod = array();

			// loop through commits
			foreach($o->commits as $commit) {
				// Github post info doesn't include branch name so we assume it's correct...
				// And this means we can't do the whole 'pending' thing. (maybe.  Dunno.  sorry.)
				$this->loginfo($config::VERBOSE, " -------------------- ");
				$this->loginfo($config::VERBOSE, "    > Change-set: " . trim($commit->message) . "\n");
				$files_added = array_merge($pending_add, $commit->added);
				$files_removed = array_merge($pending_rem, $commit->removed);
				$files_modified = array_merge($pending_mod, $commit->modified);

				$files_added_and_modified = array_merge($files_added, $files_modified);


				foreach ($files_added_and_modified as $file) {
					//add_mod_file($file_modded);
					if( empty($processed[$file]) ) {
						$processed[$file] = 1; // mark as processed
						$contents = $this->getFileContents($config, $baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						//error_log('contents ' . $contents);
						if( $contents == 'Not Found' ) {
							$this->loginfo($config::VERBOSE, 'File ' . $file . " contents not found.  Trying again...\n");
							// try one more time
							$contents = $this->getFileContents($config, $baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file);
						}

						if( $contents != 'Not Found' && $contents !== false ) {
							if( !is_dir( dirname($deployLocation . $file) ) ) {
								// attempt to create the directory structure first
								mkdir( dirname($deployLocation . $file), 0755, true );
							}
							file_put_contents( $deployLocation . $file, $contents );
							$this->loginfo($config::VERBOSE, "      - Synchronized $file\n");

							if ($config::CHECK_FILES) {
								$file_to_check = $deployLocation . $file;
								$this->check_file_exists($file_to_check, false, $config::VERBOSE);
							}

							} else {
								echo "      ! Could not get file contents for $file\n";
								$this->loginfo($config::VERBOSE, " ! Could not get file contents for $file");
								flush();
							}
						}
					}

					foreach ($files_removed as $file) {
						//remove_file($file_removed);
						unlink( $deployLocation . $file );
						$processed[$file] = 0; // to allow for subsequent re-creating of this file
						$rmdirs[dirname($deployLocation . $file)] = dirname($file);
						$this->loginfo($config::VERBOSE, "      - Removed $file\n");
						if ($config::CHECK_FILES) {
							$file_to_check = $deployLocation . $file;
							$this->check_file_exists($file_to_check, true, $config::VERBOSE);
						}
					}


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

		function check_file_exists($file_to_check, $deleted, $verbose) {
			
			$this->loginfo($verbose, 'Checking file exists: ' . $file_to_check);
			if (file_exists($file_to_check)) {
				if ($deleted) {
					// File should not exist
					$this->loginfo($verbose, "....It DOES!! ***ERROR*** ***ERROR***");
				} else {
				    $this->loginfo($verbose, "....It does");
				}
			} else { 
				if ($deleted) {
					// File should not exist
					$this->loginfo($verbose, "....It does not.");
				} else {
				    $this->loginfo($verbose, "....It does NOT!! ***ERROR*** ***ERROR***");
				}
			}
		}


		/**
		 * Gets a remote file contents using CURL
		 */
		function getFileContents($config, $url, $writeToFile = false) {

			// create a new cURL resource
			$ch = curl_init();

			//$url = $url . "?ref=deploy";
			$url = str_replace(' ', '%20', $url); // This single line of code was the solution after *many* hours of debugging.  Please treat with due reverence.

			// set URL and other appropriate options
			curl_setopt($ch, CURLOPT_URL, $url);

			curl_setopt($ch, CURLOPT_HEADER, 0);
			//curl_setopt($ch, CURLOPT_VERBOSE, 1);


		    if ($writeToFile) {
		        $out = fopen($writeToFile, "wb");
		        if ($out == FALSE) {
		            throw new Exception("Could not open file `$writeToFile` for writing");
		        }
		        curl_setopt($ch, CURLOPT_FILE, $out);
		    } else {
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    }


		    $headers = [
			    'Accept: application/vnd.github.v3.raw'
			];

			//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			// Set default user agent here in case no api user is set
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");

			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
			$API_USER = $config::API_USER;
			if(!empty($API_USER)) {
				curl_setopt($ch, CURLOPT_USERPWD, $config::API_USER . ':' . $config::API_PASSWORD);
				curl_setopt($ch, CURLOPT_USERAGENT, $config::API_USER);
			}


			// Remove to leave curl choose the best version
			//curl_setopt($ch, CURLOPT_SSLVERSION,3);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

			// grab URL
			$data = curl_exec($ch);
			$data2 = (string) $data;

			if(curl_exec($ch) === false) {
				$this->loginfo($config::VERBOSE, 'Curl error: ' . curl_error($ch));
			}

			if ($data2 == '404: Not Found') {
				$this->loginfo($config::VERBOSE, "Token required! File at url " . $url . " not downloaded");
			}

			/**
		      * Check HTTP return status.
		      */
		     $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		     $curl_info = curl_getinfo($ch);
		     if ($http_code != 200) {
		     	$this->loginfo($config::VERBOSE, 'Cant\'t get file from URL '.$url.' - cURL error: '.curl_error($ch) . 'Http code: '. $http_code);
		     	$this->loginfo($config::VERBOSE, 'More info: ' . print_r($curl_info, true));
		     }

			if(curl_errno($ch) != 0) {
				echo "      ! File transfer error: " . curl_error($ch) . "\n";
		     	$this->loginfo($config::VERBOSE, "      ! File transfer error: " . curl_error($ch) . "\n");
			}


			// close cURL resource, and free up system resources
			curl_close($ch);

			return $data2;

		}


		/**
		 * Copies the directory contents, recursively, to the specified location
		 */
		function cptree($dir, $dst) {
			if (!file_exists($dst)) if(!mkdir($dst, 0755, true)) return false;
			if (!is_dir($dir) || is_link($dir)) return copy($dir, $dst); // should not happen
			$files = array_diff(scandir($dir), array('.','..'));
			$sep = (substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			$dsp = (substr($dst, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			foreach ($files as $file) {
				(is_dir("$dir$sep$file")) ? $this->cptree("$dir$sep$file", "$dst$dsp$file") : copy("$dir$sep$file", "$dst$dsp$file");
			}
			return true;
		}


		/**
		 * Deletes a directory recursively, no matter whether it is empty or not
		 */
		function deltree($dir, $deleteParent = false) {
			if (!file_exists($dir)) return false;
			if (!is_dir($dir) || is_link($dir)) return unlink($dir);
			// prevent deletion of current directory
			$cdir = realpath($dir);
			$adir = dirname(__FILE__);
			$cdir = $cdir . (substr($cdir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			$adir = $adir . (substr($adir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			if( $cdir == $adir ) {
				$this->loginfo($config::VERBOSE, " * Contents of '" . basename($adir) . "' folder will not be cleaned up.\n");
				return true;
			}
			// process contents of this dir
			$files = array_diff(scandir($dir), array('.','..'));
			$sep = (substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
			foreach ($files as $file) {
				(is_dir("$dir$sep$file")) ? $this->deltree("$dir$sep$file", true) : unlink("$dir$sep$file");
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
		function loginfo($verbose, $message) {
			//global $CONFIG;

			if( $verbose ) {
                error_log($message);
				echo $message;
				flush();
			}
		}

}