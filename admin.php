<?php

/*
    Github Deploy (c) Mikey Beck
    https://github.com/mikeybeck/github-deploy

    Based on BitBucket Sync (c) Alex Lixandru
    https://bitbucket.org/alixandru/bitbucket-sync

    THIS file based on the admin page in Wanchai's wonderful 
    FTPBucket: https://github.com/Wanchai/FTPbucket

    File: admin.php
    Version: 0.3.0
    Description: Admin page for Github Deploy script


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

	<title>Github Deploy</title>
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
        .log .commit-info:nth-of-type(2n) {
            background: #eee;
        }
	</style>

    <script type="text/javascript">

        // Wait for the page to load first
        window.onload = function() {

          //Get a reference to the link on the page
          // with an id of "mylink"
          var reverseButton = document.getElementById("reverse");

          //Set code to run when the link is clicked
          // by assigning a function to "onclick"
          reverseButton.onclick = function() {

            if (typeof(Storage) !== "undefined") {
                // Code for localStorage/sessionStorage.
                if (localStorage.getItem("reverse") == 1) {
                    localStorage.setItem("reverse", 0);               
                } else {
                    localStorage.setItem("reverse", 1);
                }
                location.reload();
            } else {
                // Sorry! No Web Storage support..
                alert('No localstorage detected. Reverse function disabled.');
            }
            
            return false;
          }

            var text = document.getElementById('log').innerHTML;

            var mySplitResult = text.split(/(^.*?--------------------.*$)/mg);

            var commits = [];

            for(var i = 0; i < mySplitResult.length; i++) {
                //console.log("<br /> Element " + i + " = " + mySplitResult[i]);
                if (mySplitResult[i].indexOf("--------------------") !== -1 ) {
                    commits.push("<div class='commit-info'><b>" + mySplitResult[i] + "</b>" + mySplitResult[i+1] + "</div>");
                    i++;
                } 
                
            }
                
            //textRev = text.split('\n').reverse().join('\n');

            if (localStorage.getItem("reverse") == 1) {
                //document.getElementById('log').innerHTML = textRev;
                document.getElementById('log').innerHTML = commits.reverse().join("").toString();
            } else {
                //document.getElementById('log').innerHTML = commits.join("").toString();
            }
        }
    </script>

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
                Logs - <a href='?delete_log=php-error-log'>Clear Log</a> <button id="reverse">Reverse</button>
                <?php $urlBase = '//'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']); ?>
                <a href=<?php echo $urlBase . "/index.php?test&key=" . $config::DEPLOY_AUTH_KEY; ?>>Test</a>
                <p>Set up repo:<br>
                <?php 
                    //Get repo names and provide a link to deploy each one
                    foreach(array_keys($config->DEPLOY) as $repo) {
                        ?>
                        <a href=<?php echo $urlBase . "/index.php?setup=". $repo ."&key=" . $config::DEPLOY_AUTH_KEY; ?>>
                        <?php echo $repo; ?></a><br />
                        <?php
                    } 
                ?>
                </p>
                
                <pre class='log' id="log"><?php echo $exp1; ?></pre>
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

