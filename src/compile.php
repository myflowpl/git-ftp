<?php
/**
 * combines all project files to one git-ftp.php file
 * for easier use, you don't have to copy all project, just git-ftp.php
 */

$git = file_get_contents('GitFtp.php');
$cli = file_get_contents('git-ftp-cli.php');
$gui = file_get_contents('git-ftp-gui.php');

$replace = array(
    "require 'GitFtp.php';"=>'',
);
$git = substr($git, 6);
$cli = substr($cli, 6);

$cli = strtr($cli, $replace);
$gui = strtr($gui, $replace);

$project = '<?php
'.$git.'

if (php_sapi_name() == "cli" && empty($_SERVER["REMOTE_ADDR"])) {
        '.$cli.'
    } else {
        ?>'.$gui.'<?php
    }
';

file_put_contents('../git-ftp.php', $project);
echo 'project compiled to ../git-ftp.php';