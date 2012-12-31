<?php
/*
   use this file for the development purpose, if you wish to help and improve this tool of course :)
  
   just copy it to the directory of your project and change the require statement
 
	example:
	
	/www/firstGitProject
	/www/git-ftp                       <<<<< the git-ftp.php whole project with the src dir inside      
	/www/git-ftp-test.php              <<<<< in this case default require 'git-ftp/src/git-ftp-gui.php'; will work perfectly

	in your web browse run localhost/git-ftp-test.php and you can test the gui interface
 
 */

// uncomment one of the lines,

// GUI development
require 'git-ftp/src/git-ftp-gui.php';

// CLI development
//require 'git-ftp/src/git-ftp-cli.php';