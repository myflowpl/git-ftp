<?php
require 'GitFtp.php';
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
                float: right;
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
            .files-list {
                float: right;
                width: 570px;
            }
            iframe {
                border: 0;
                width: 100%;
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
                <form id="commit-form"  method="GET">
                    <input type="hidden" name="server" value="<?php echo $_GET['server']?>" />
                    <?php
                    $cr = $git->getCurrentCommit();
                    $cr = $cr['commit'];
                    foreach($git->getCommits() as $r):?>
                        <p>
                            <input type="radio" name="commit" <?php  if($cr == $r['commit']) echo'checked="checked"' ?> value="<?php echo $r['commit']?>" id="commit-<?php echo $r['commit']?>" /> <label  for="commit-<?php echo $r['commit']?>"><?php echo $r['message']?></label><br>
                        </p>
                        <?php endforeach;?>
                </form>
            </div>

            <div class="files-list">
                <iframe   onload = "setIframeHeight( this )" seamless="seamless" src="<?php
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
