<?php
/**
 * Deploys git project to the ftp server
 *
 * @author https://github.com/myflowpl <pedro.blaszczak@gmail.com>
 */
class GitFtp
{

    private $connection;
    private $project;
    private $config;
    public $defaultConfig = array(
        'name' => '',
        'host' => '',
        'port' => '21',
        'user' => '',
        'password' => '',
        'remoteCommit' => null,
        'remotePath' => '/',
    );

    public function __construct()
    {
        $projects = self::getProjects();
        if (count($projects)) {
            $this->setProject($projects[0]);
        }
    }

    /**
     * @param $projectDirName directory with the git repo inside
     */
    function setProject($projectDirName)
    {
        $projects = self::getProjects();

        if (!in_array($projectDirName, $projects)) {
            return $this->error('project dir "' . $projectDirName . '" does not exist');
        }
        $projectDirName = rtrim($projectDirName, '/');
        $this->project = $projectDirName;
    }

    public function getProject(){
        return $this->project;
    }

    public function hasProjects(){
        return count(self::getProjects());
    }

    /**
     * @param $serverName server name in config file for the project
     */
    function setServer($serverName)
    {
        if (!$this->project) {
            return $this->error('you have to set project first before you select the server');
        }
        $servers = $this->getServers();
        if (!isset($servers[$serverName])) {
            $this->error('config for id ' . $serverName . ' does not exist');
        }
        $this->config = $servers[$serverName];
    }

    public function hasServers(){
        if (!$this->project) {
            return $this->error('you have to set project first before you save the server');
        }

        return count($this->getServers());
    }

    public function saveServer($config){
        $file = $this->project . '/.git/git-ftp-config.php';
        $data = array();
        if (file_exists($file)) {
            $data = include $file;
        }

        if(isset($config['name1']) AND $config['name1'] AND isset($data[$config['name1']])){
            $config = $config+$data[$config['name1']];
            unset($data[$config['name1']]);
            unset($config['name1']);
        }
        $config = $config+$this->defaultConfig;
        $data[$config['name']] = $config;

        $str = var_export($data, true);
        if (file_exists($file)) {
            unlink($file);
        }
        file_put_contents($file, '<?php return ' . $str . ';');
    }

    /**
     * returns the array of commits
     * uses git log command
     *
     * @return array
     */
    function getCommits()
    {
        if (!$this->project) {
            return $this->error('you have to set project first before you can get the config');
        }
        $list = array();
        $lines = array();
        exec("git --git-dir {$this->project}/.git log", $lines);
        foreach ($lines as $k => $line) {
            if (substr($line, 0, 7) == 'commit ') {
                $row = array(
                    'commit' => substr($line, 7),
                    'author' => substr($lines[$k + 1], 8),
                    'date' => substr($lines[$k + 2], 8),
                    'message' => substr($lines[$k + 4], 4, 50),
                );
                $list[$row['commit']] = $row;
            }
        }
        return $list;
    }

    /**
     * returns the current selected server name of the project
     *
     * @return string
     */
    public function getServerName()
    {
        if ($this->config) {
            return $this->config['name'];
        }
        return null;
    }

    /**
     * get config for selected server
     * applies the defaults
     */
    public function getConfig()
    {
        if (!$this->config) {
            return $this->error('you have to set project and the server first before you can get the config');
        }
        return $this->config+$this->defaultConfig;
    }

    public function getServers()
    {
        if (!$this->project) {
            return $this->error('you have to set project first before you can get the servers');
        }
        $file = $this->project . '/.git/git-ftp-config.php';
        $data = array();
        if (file_exists($file)) {
            $data = include $file;
        }
        return $data;
    }

    /**
     * retrieves available projects
     *
     * @return array projects
     */
    public static function getProjects()
    {
        $projects = array();
        if (is_dir('.git')) {
            $projects[] = '.';
        }
        $data = scandir('.');
        foreach ($data as $dir) {
            if ($dir == '.' OR $dir == '..') {
                continue;
            }
            if (is_dir($dir) AND is_dir($dir . '/.git')) {
                $projects[] = $dir;
            }
        }
        return $projects;
    }

    /**
     * close the connection
     */
    function __destruct()
    {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }

    /**
     * prints error message
     *
     * @param $message
     */
    function error($message)
    {
        die($message);
    }

    public function getCurrentCommit()
    {
        $c = $this->getConfig();
        if ($c['remoteCommit']) {
            return $c['remoteCommit'];
        }
        $c = end($this->getCommits());
        return $c;
    }

    public function getHeadCommit()
    {
        $c = current($this->getCommits());
        return $c;
    }

    public function setCurrentCommit($commit)
    {
        if (!$commit) {
            die("empty commit");
        }
        $config = $this->getConfig();
        if(is_string($commit)){
            $commits = $this->getCommits();
            pr($commit);
            if(!isset($commits[$commit])){
                die('commit not found');
            }
            $commit = $commits[$commit];
        }
        $config['remoteCommit'] = $commit;
        if($this->config){
            $this->config['remoteCommit'] = $commit;
        }
        $this->saveServer($config);
    }

    public function getDiff()
    {
        $current = $this->getCurrentCommit();
        $files = array();
        $command = "git  --git-dir {$this->project}/.git diff --name-status {$current['commit']} HEAD";
        exec($command, $files);

        return $files;
    }

    public function testConnection($c){
		$con = @ftp_connect($c['host'], $c['port']) or die ("Cannot connect to host");
		if(!$con) return false;
		$l = @ftp_login($con, $c['user'], $c['password']) or die("Cannot login");
		if($l) return true;
		return false;
	}
	
    public function getFtp()
    {
        if (!$this->connection) {
            $c = $this->config;
            $this->connection = ftp_connect($c['host'], $c['port']) or die ("Cannot connect to host");

            // send access parameters
            ftp_login($this->connection, $c['user'], $c['password']) or die("Cannot login");
        }
        return $this->connection;
    }

    public function push($logger)
    {
        $files = $this->getDiff();
        $this->uploadFiles($files, $logger);
        $this->setCurrentCommit($this->getHeadCommit());
    }

    public function uploadFiles($files, $logger)
    {

        // connect to FTP server (port 21)
        $c = $this->config;
        $conn_id = $this->getFtp();

        // perform file upload
        $dir = $c['remotePath'];
        $this->ftpMkdir($dir);
        foreach ($files as $file) {
            $status = 'error';
            $message = '';
            $name = substr($file, 2);
            $action = substr($file, 0, 1);
            $destination = $dir . $name;
            $src = $this->project.'/'.$name;

            // delete thie file
            if ($action == 'D') {
                if ($this->ftpDelete($destination, $message)) {
                    $status = 'success';
                }
                // upload the file
            } else {

                if ($this->ftpPut($destination, $src, $message)) {
                    $status = 'success';
                }
            }
            call_user_func($logger, $action, $name, $status, $message);
        }

    }

    function ftpPut($dest, $src, &$message = null){
        $dirName = pathinfo($dest, PATHINFO_DIRNAME);
        $this->ftpMkdir($dirName);

        $trackErrors = ini_get('track_errors');
        ini_set('track_errors', 1);
        if (!@ftp_put($this->getFtp(), $dest, $src, FTP_BINARY)) {
            // error message is now in $php_errormsg
            $msg = $php_errormsg;
            ini_set('track_errors', $trackErrors);
            if($message !== null){
                $message = $msg;
            }
            return false;
        }
        return true;
    }

    function ftpDelete($dest, &$message = null){
        $trackErrors = ini_get('track_errors');
        ini_set('track_errors', 1);
        if (!@ftp_delete($this->getFtp(), $dest)) {
            // error message is now in $php_errormsg
            $msg = $php_errormsg;
            ini_set('track_errors', $trackErrors);
            if($message !== null){
                $message = $msg;
            }
            return false;
        }
        return true;
    }

    function ftpMkdir($path)
    {
        $parts = explode("/", $path);
        $return = true;
        $fullpath = "";
        foreach ($parts as $part) {
            if (empty($part)) {
                $fullpath .= "/";
                continue;
            }
            $fullpath .= $part . "/";
            if (@ftp_chdir($this->getFtp(), $fullpath)) {
                ftp_chdir($this->getFtp(), $fullpath);
            } else {
                if (@ftp_mkdir($this->getFtp(), $part)) {
                    ftp_chdir($this->getFtp(), $part);
                } else {
                    $return = false;
                }
            }
        }
        return $return;
    }
}

/**
 * print variable, helper function
 *
 * @param $label
 * @param null $v
 */
function pr($label, $v = null)
{
    if (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
        if (func_num_args() == 1) {
            echo "\n" . var_export($label) . "\n\n";
        } else {
            echo "\n" . $label . "  ";
            echo "" . var_export($v) . "\n\n";
        }
    } else {
        if (func_num_args() == 1) {
            var_dump($label);
        } else {
            echo ($label . ' ');
            var_dump($v);
        }
    }
}

/**
 * print variable and die helper function
 *
 * @param $label
 * @param null $v
 */
function diee($label, $v = null)
{
    if (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
        if (func_num_args() == 1) {
            echo "\n" . var_export($label) . "\n\n";
        } else {
            echo "\n" . $label . "  ";
            echo "" . var_export($v) . "\n\n";
        }
    } else {
        if (func_num_args() == 1) {
            var_dump($label);
        } else {
            echo ($label . ' ');
            var_dump($v);
        }
    }
    die();
}

if (php_sapi_name() == "cli" && empty($_SERVER["REMOTE_ADDR"])) {
        
set_time_limit(0);

$git = new GitFtp();
diee('//TODO add CLI suport, i do not need cli for now so it is on my todo list');
    } else {
        ?><?php

set_time_limit(0);


function redirect($project, $server = ''){
    $self = $_SERVER['PHP_SELF'];
    $url = $self.'?project='.$project;
    if($server){
        $url .= '&server='.$server;
    }
    header( 'Location: '.$url ) ;
    exit;
}

$git = new GitFtp();

if(isset($_GET['testConnection'])){
	if($git->testConnection($_POST)){
		die('Connection SUCCESS');
	}else{
		die('Connection ERROR');
	}
}

$server = (isset($_GET["server"])) ? $_GET["server"] : null;
$project = (isset($_GET["project"])) ? $_GET["project"] : null;
$message = '';

if(!$git->hasProjects()){
    die('sorry no projects has been detected, place the git-ftp.php file in the project directory it self or in the parent directory');
}

// force one project selected
if(!$project){
    $project = $git->getProject();
    redirect($project);
}
if($project){
    $git->setProject($project);
}
if(!$server AND count($git->getServers())){
    $server = current($git->getServers());
    redirect($project, $server['name']);
}
if($server){
    $servers = $git->getServers();
    if(!isset($servers[$server])){
        redirect($project);
    }
    $git->setServer($server);
}

if(isset($_POST['saveServer'])){
    if($_POST['config']['name']){
        $git->saveServer($_POST['config']);
        redirect($project, $_POST['config']['name']);
    } else {
        $message = 'Name is required';
    }
}
if(isset($_POST['setCommit']) AND isset($_POST['commit'])){
    $git->setCurrentCommit($_POST['commit']);
}


if(isset($_POST['upload'])){
    ?><!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        * { margin:0; padding:0; }
        p.success {
            color: #006400;
        }
        p.error {
            color: #8b0000;
        }
        i {
            color: gray;
        }

    </style>
</head>
<body>
<h3>Upload Result</h3>
    <?php
        function logFiles($action, $file, $status, $message = null){
            echo '<p class="'.$status.'" title="'.$message.'">'.$action.' '.$file.' <i>'.$status.'</i></p>';
        }
    $git->push('logFiles');

        ?>
<br>
<i>(hit F5 or Ctrl+R to reload the page)</i>
</body></html>
    <?php
    die();
}
if(isset($_GET['fileDiff'])){
    ?><!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            * { margin:0; padding:0; }
            p.submit {
                overflow: hidden;
            }
            .submit {
                position: fixed;
                bottom: 0px;
                right:20px;
            }
        </style>
    </head>
<body>
<?php
$diffs = $git->getDiff();

if(count($diffs)){?>
    <h3>Changed files</h3>
    <form id="files-form"  method="POST">
        <?php
        foreach($git->getDiff() as $k=>$r):?>
            <p>
                <?php echo $r?>
                <!--			<input type="checkbox" name="commit" checked="checked" value="--><?php //echo $r?><!--" id="file---><?php //echo $k?><!--" /> <label  for="file---><?php //echo $k?><!--">--><?php //echo $r?><!--</label><br>-->
            </p>
            <?php endforeach;?>
        <p class="submit">
            <input name="upload" class="submit" type="submit" value="Upload">
        </p>
    </form>
        <?php }else { echo 'No changes detected';}?>
        </body></html>
<?php
    die();
}

$editServer = isset($_GET['editServer']);

function renderServerForm($git, $server = null){
    if(isset($_POST['config'])){
        $server = $_POST['config'];
    }
    if(!$server){
        $server = $git->defaultConfig;
    }
    echo "
    <form id='serverForm' class='server-form' method='POST'>
        <input type='hidden' name='config[name1]' value='{$server['name']}'/>
        <table>
        <tr>
            <td>name:</td>
            <td><input type='text' name='config[name]' value='{$server['name']}'/> </td>
        </tr>
        <tr>
            <td>host:</td>
            <td><input type='text' name='config[host]' value='{$server['host']}'/> </td>
        </tr>
        <tr>
            <td>port:</td>
            <td><input type='text' name='config[port]' value='{$server['port']}'/> </td>
        </tr>
        <tr>
            <td>user:</td>
            <td><input type='text' name='config[user]' value='{$server['user']}'/> </td>
        </tr>
        <tr>
            <td>password:</td>
            <td><input type='password' name='config[password]' value='{$server['password']}'/> </td>
        </tr>
        <tr>
            <td>remote path:</td>
            <td><input type='text' name='config[remotePath]' value='{$server['remotePath']}'/> </td>
        </tr>
        <tr>
            <td></td>
            <td>
			<input type='submit' name='saveServer' value='Save'/> 
			<input type='button' name='testServer' value='Test connection'  onClick=\"testConnection()\"/> 
			</td>
        </tr>
        </table>
    </form>
    ";
}
?><!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>git-ftp</title>

        <style>
            * { margin:0; padding:0; }
            #page {
                margin: auto;
                padding: 0 10px;
                width: 800px;
            }
            #hd {
                padding: 0 0 10px 0;
            }
            #bd {
                background-color: #F7F7F9;
                border: 1px solid #E1E1E8;
                border-radius: 4px 4px 4px 4px;
                min-height: 300px;
                padding: 5px;
                overflow: hidden;
            }
            #ft {
                color: gray;
                text-align: left;
                font-style: italic;
                font-size: 13px;
                margin: auto;
            }

            hr {
                background: gray;
                margin: 10px 0;
            }

            .server-form input{
                padding: 2px 5px;
                width: 300px;
            }
            .commit-list {
                float: left;
                width: 200px;
                border-right: 1px solid gray;
                padding: 0 5px 5px 0;
            }
            .commit-list p {
                color: #696969;
                font-style: italic;
                font-size: 10px;
            }
            .commit-list .current-commit {
                color: #006400;
                font-weight: bold;
            }
            .files-list {
                float: right;
                width: 570px;
            }
            .commit-list-box {
                height: 500px;
                overflow: auto;
            }
            iframe {
                border: 0;
                width: 100%;
                height: 500px;
                overflow: auto;
            }
        </style>
        <script type='text/javascript'>
		testConnection = function(){
			var host = document.forms.serverForm['config[host]'].value;
			var port = document.forms.serverForm['config[port]'].value;
			var user = document.forms.serverForm['config[user]'].value;
			var password = document.forms.serverForm['config[password]'].value;
			
			
			ajax('<?php echo $_SERVER['PHP_SELF'].'?testConnection=1'?>', function(data){
				alert(data);
			}, 'host='+host+'&port='+port+'&user='+user+'&password='+password);
		}
		ajax = function (url, callback, params)
			{
				var xmlHttp;
				try
				{
					// Firefox, Opera 8.0+, Safari
					xmlHttp=new XMLHttpRequest(url);
				}
				catch (e)
				{
					// Internet Explorer
					try
					{
						xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
					}
					catch (e)
					{
						try
						{
							xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
						}
						catch (e)
						{
							alert("Your browser does not support AJAX!");
							return false;
						}
					}
				}
				xmlHttp.onreadystatechange=function()
				{
					if(xmlHttp.readyState==4)
					{
						callback(xmlHttp.responseText);
					}
				}

				//Sending POST method.
				if (params)
				{
					xmlHttp.open("POST", url, true);
					xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
					xmlHttp.send(params);
				}
				//Sending GET method.
				else
				{
					xmlHttp.open("GET", url, true);
					xmlHttp.send(null);
				}
			}

			ajax.boundFunc = function(method) 
			{
				var obj = this;
				return function() 
				{
					return method.apply(obj, arguments);
				};
			}
            function setIframeHeight( ifRef ) /** IMPORTANT: All framed documents *must* have a DOCTYPE applied **/
            {
                var ifDoc;

                try
                {
                    ifDoc = ifRef.contentWindow.document.documentElement;
                }
                catch( e )
                {
                    try
                    {
                        ifDoc = ifRef.contentDocument.documentElement;
                    }
                    catch(ee)
                    {
                    }
                }

                if( ifDoc )
                {
                    ifRef.height = 1;
                    ifRef.height = ifDoc.scrollHeight;

                    /* For width resize, enable below.  */

                    // ifRef.width = 1;
                    // ifRef.width = ifDoc.scrollWidth;
                }
            }

        </script>
    </head>
<body>
    <div id="page">
        <div id="hd">
            <h1>git-ftp.php</h1>
        </div>
        <div id="bd">
            <form id="server-form" method="GET">
                Project:
                <select name="project" onchange="this.form.submit()">
                    <?php foreach(GitFtp::getProjects() as $n):?>
                    <option <?= ($project == $n) ? 'selected="selected"' : '' ?> value="<?php echo $n?>"><?php echo $n?></option>
                    <?php endforeach;?>
                </select>
                <?php if($git->getProject() AND $git->hasServers()):?>
                Server:
                <select name="server" onchange="this.form.submit()">
                    <?php foreach($git->getServers() as $k=>$cfg):?>
                    <option <?= (isset($_GET["server"]) && $_GET["server"] == $k) ? 'selected="selected"' : '' ?> value="<?php echo $k?>"><?php echo $cfg['name']?></option>
                    <?php endforeach;?>
                </select>
                <input class="submit" type="submit" name="editServer" value="Edit Server">
                <?php endif;?>
            </form>
            <hr>
            <?php if($message):?>
                <div class="message"><?php echo $message ?></div>
            <?php endif;?>
            <?php if(!$git->hasServers()): ?>
                <h4>The project has no servers to upload to</h4>
                <p>Create new server</p>
                <?php echo renderServerForm($git); ?>
            <?php elseif($editServer): ?>
                <p>Edit server</p>
                <?php echo renderServerForm($git, $git->getConfig()); ?>
            <?php else:?>



            <div class="commit-list">
            <h3>Commits list</h3>
                <div class="commit-list-box">
                    <form id="commit-form"  method="POST">
                        <input type="hidden" name="server" value="<?php echo $_GET['server']?>" />
                        <?php
                        $cr = $git->getCurrentCommit();
                        $cr = $cr['commit'];
                        $active = false;
                        foreach($git->getCommits() as $r):
                            if($cr == $r['commit']) $active=true;                        ?>
                            <div class="<?php  if($active) echo'current-commit"' ?>">
                                <input type="radio" name="commit" <?php  if($cr == $r['commit']) echo'checked="checked"' ?> value="<?php echo $r['commit']?>" id="commit-<?php echo $r['commit']?>" /> <label  for="commit-<?php echo $r['commit']?>"><?php echo $r['message']?></label><br>
                            </div>
                        <?php endforeach;?>
                        <p>
                            For the first time you probably wish to upload the project using advanced ftp client, like Fillezilla.<br>
                            After that you can tell the git-ftp what commit is a current commit uploaded to the server.
                        </p>
                        <button name="setCommit" type="submit">Set this commit!</button>
                    </form>
                </div>
            </div>

            <div class="files-list">
                <iframe class="file-list-iframe"  onload = "setIframeHeight( this )" seamless="seamless" src="<?php
                    $self = $_SERVER['PHP_SELF'];
                    $url = $self.'?project='.$project.'&server='.$server.'&fileDiff=true';
                    echo $url?>"></iframe>
            </div>




            <?php endif;?>
        </div>
        <div id="ft">
            developed by: pedro.blaszczak@gmail.com
        </div>
    </div>

</body>
</html>
<?php
    }
