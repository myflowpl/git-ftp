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
        if(isset($config['name1'])){
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
        $config['remoteCommit'] = $commit;
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