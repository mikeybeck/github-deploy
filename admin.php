<?php

/*
    GitHub Sync (c) Mikey Beck
    https://github.com/mikeybeck/github-sync

    Based on BitBucket Sync (c) Alex Lixandru
    https://bitbucket.org/alixandru/bitbucket-sync

    THIS file based on the admin page in Wanchai's wonderful 
    FTPBucket: https://github.com/Wanchai/FTPbucket

    File: admin.php
    Version: 0.2.0
    Description: Admin page for GitHub Sync script


    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.
*/
	
    session_start();

    include_once 'config.php';

    $config = new Config();
    

    if (isset($_POST['admin_password']) && $config::ADMIN_PASSWORD && $config::ADMIN_PASSWORD != ''  
        && $_POST['admin_password'] === $config::ADMIN_PASSWORD) {
        $_SESSION['logged'] = 'true';
        @header('Location: admin.php');
    }

    if (isset($_GET['delete_log']) && isset($_SESSION['logged'])) {
        clear_file($_GET['delete_log']);
        @header('Location: admin.php');
    }
?>

<!DOCTYPE HTML>
<head>
	<meta http-equiv="content-type" content="text/html" />

	<title>GitHub Sync</title>
	<style>
	    .log{
	        font: 12px verdana,sans-serif;
	        width: 100%;
	        border: solid 1px #000;
	        height: 500px;
	        overflow: scroll;
	        white-space: pre-wrap;       /* CSS 3 */
            white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
            white-space: -pre-wrap;      /* Opera 4-6 */
            white-space: -o-pre-wrap;    /* Opera 7 */
            word-wrap: break-word; 
	    }
	    td {
	        padding: 5px;
	    }
	</style>
</head>

<body>

<?php 
    if (!isset($_SESSION['logged'])) {
?>
        <form action="" method="post">
            <input type="password" name="admin_password" size="20" />
            <input type="submit" name="submit" value="Login" />
        </form>
<?php
    } else {
        $exp1 = '';
        if (file_exists('php-error.log')) {
            $log1 = file('php-error.log');
            foreach ($log1 as $ln) {
                $exp1 .= $ln;
            }
        }
        ?>
        
        <div>
                Logs - <a href='?delete_log=php-error-log'>Clear Log</a><br />
                <pre class='log'><?php echo $exp1; ?></pre>
        </div>
        
        <?php
    }
?>

</body>
</html>

<?php
function clear_file($file){

    error_log("file " . $file);
    
    if ($file == 'php-error-log'){
        $file = 'php-error.log';
    } else {
        die();
    }
    
    $file = @fopen($file, "r+");
    if ($file !== false) {
        ftruncate($file, 0);
        fclose($file);
    }
}