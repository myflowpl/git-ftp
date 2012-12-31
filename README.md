git-ftp.php
=================

* &copy; Piotr B³aszczak, pedro.blaszczak gmail com, 2012
* This application is licenced under [GNU General Public License, Version 3.0]


About
-----

git-ftp.php is a PHP script for deploing git project to the FTP server using web interface or command line.

You can use git-ftp.php for any script based projects (PHP in my case) 
in order to deploy them to the low cost servers 
where SSH or Git are not available and FTP is the only option.

The script allowes you to:
- manage multiple project from one git-ftp.php file
- define multiple deployment servers for each project
- track and store currently uploaded revision
- upload only the files that changed since the last upload


Download & Install
------------------

In order to use the script:
you just need to download the git-ftp.php file and place it to the:
a) directly to your git tracked project directory in order to use it only for one project
b) to directory that contains multiple git tracket projects in order to use it for multiple projects

Example of file structure:

/www/firstGitProject
/www/firstGitProject/.git
/www/firstGitProject/git-ftp.php    <<<<< scenario A, one project
/www/secoundGitProject
/www/secoundGitProject/.git
/www/git-ftp.php                    <<<<< secenario B, multiple projects


In order to develop, fix bugs, add new features to the script:
- download the whole project
- edit files in src directory
- use src/git-ftp-test.php to test the working copy (for help see the comments inside the file)
- when you finish run the src/compile.php in order to combine all files into one git-ftp.php file
- now use git-ftp.php file and enjoy your work :)


How to use it - Graphic User Interface
--------------------------------------

In order to use the GUI just access the file from the web browse.
In example from the Download & Install section we have the /www dir that is the root of localhost domain.
So go to http://localhost/git-ftp.php and just use it following the steps:

- select the project you wont to deploy
- if it's the first time you run the script, you have to define the server connection, form will show up, just fill it up, click test connection if it's ok click Save
- the server connection data will be stored inside the project in /www/projectName/.git/git-ftp-config.php
- you can define and edit multiple servers but remember to keep the names unique, other wise they will overwrite each other
- now when you have your project selected and server configured you can see the commit list and the list of filest witch need to be uploaded
- just click the upload button in order to upload the filest to the server

How to use it - Command Line (TODO)
----------------------------

Command line interface is not yet implemented, but it will work something like this:

go to the dir with the script file and projects

	cd /www

see the available projects list

	$ php git-ftp.php
	
see the servers defined fo the project

	$ php git-ftp.php projectName
	
see the files that changed since last deploy

	$ php git-ftp.php projectName serverName
	
deploy the project

	$ php git-ftp.php projectName serverName push

configure server

	$ php git-ftp.php projectName serverName config host ftp.mydomain.com
	$ php git-ftp.php projectName serverName config port 21
	$ php git-ftp.php projectName serverName config user admin
	$ php git-ftp.php projectName serverName config password mypass
	$ php git-ftp.php projectName serverName config remotePath /dir/on/the/ftp/server/projectName/