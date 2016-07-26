<?php

########################################
######## Default configuration #########
########################################
$config["inhouse_ip"] = "110.140.119.209";

$config["global_email"] = "admin@webworksau.com";
$config["global_pass"] = "3GHUQ3zzgvvpV5nA";

$config['email'] = isset($config['email']) ? $config['email'] : $config["global_email"];
$config['pass'] = isset($config['pass']) ? $config['pass'] : $config["global_pass"];

$config['enable_admin'] = isset($config['enable_admin']) ? $config['enable_admin'] : true;
$config['enable_phpliteadmin'] = isset($config['enable_phpliteadmin']) ? $config['enable_phpliteadmin'] : false;

$config['dbname'] = isset($config['dbname']) ? $config['dbname'] : "db/cmsdb";


if (!isset($config['enabled_modules']))
	$config['enabled_modules'] = [ "pages", "file_manager", "content_blocks", "contact", "gallery", "banners" ];

if (!isset($config['disabled_modules']))
	$config['disabled_modules'] = [ ];

if (isset($config['additional_modules']))
	$config['enabled_modules'] = array_merge($config['enabled_modules'], $config['additional_modules']);

########################################
####### remote vs local configs ########
########################################

if ($_SERVER["DOCUMENT_ROOT"] == "/home/alan/www/")
{
	// Local machine (Alans Dell)
	$config["cms_location"] = "/home/cms/";
	$f3_location  = "/home/alan/www/f3/lib/base.php";
	$config["ckeditor_location"] = "http://localhost/ckeditor/ckeditor.js";
	$ace_editor = "http://localhost/ace/src-min/ace.js";

	$debug = true;

	$config["contact.port"] = 2525;

	if (isset($config["remote_tools"]))
	{		
		$hash = file_get_contents($config["remote_tools"] . "dbhash");
		
		if (sha1_file($config["dbname"]) != $hash)
			d("local vs remote hashes do not match");
	}

	$config["global_email"] = "admin@webworksau.com";
	$config["global_pass"] = "twilight";
}
else
{
	// Webworks Server
	$config["cms_location"] = "/home/cms/";
	$f3_location  = "/home/f3/lib/base.php";
	$config["ckeditor_location"] = "http://webworksau.com/ckeditor/ckeditor.js";
	$ace_editor = "http://webworksau.com/ace/src-min/ace.js";
	
	$config["contact.port"] = 25;

	$debug = false;
}

########################################
## Check folder and file permissions  ##
########################################
if (!file_exists(getcwd()."/.htaccess")) {
	include("modules/wizard_creator.php");
	new wizard_creator($config["cms_location"], $f3_location);
}

// Required folders for operation
if (!file_exists(getcwd()."/tmp/")) { echo "<strong>tmp</strong> folder does not exist. Please create tmp folder in client folder with group writable permissions. (chmod g+w tmp or chmod 755 db)";exit; }
if (!is_writable(getcwd()."/tmp/")) { echo "Please make <strong>tmp</strong> folder writable by group";exit; }
if (!file_exists(getcwd()."/db")) { echo "<strong>db</strong> folder does not exist. Please create db folder in client folder with group writable permissions. (chmod g+w db chmod 755 db)";exit; }
if (!is_writable(getcwd()."/db/")) { echo "Please make <strong>db</strong> folder writable by group";exit; }
if (!is_writable(getcwd()."/".$config["dbname"])) { echo "Please make database file writable."; exit;}
if (!file_exists(getcwd()."/.htaccess")) htaccess_example();

########################################
####### phpLiteAdmin redirecting #######
########################################

if ($config['enable_phpliteadmin']) {
	$db_sub_folder = "admindb";

	$url = pathinfo($_SERVER["REQUEST_URI"]);
	$dir = basename($url["dirname"]);

	$dir2 = explode("?", $url["basename"]);

	if ($url["filename"] == "phpliteadmin")
	{
		include("/home/cms/phpliteadmin/dynamic_myAdmin.php");
		die;
	}

	if ($dir == $db_sub_folder || $url["basename"] == $db_sub_folder || $dir2[0] == $db_sub_folder)
	{
		include("/home/cms/phpliteadmin/phpliteadmin.php");
		die;
	}
}


########################################
########## Fatfree framework ###########
########################################

// Fat free framework
if(($f3 = include $f3_location) === false)
	d("Fat free framework not found at $f3_location. Please download from http://fatfreeframework.com/");

// Webworks CMS
if (!file_exists($config["cms_location"]))
	d("Webworks CMS not found at". $config["cms_location"].". Please update $\cms_location variable to point to CMS folder.");

$f3->set("client", $config);
$f3->set("CMS", $config["cms_location"]);
$f3->set("ACE", $ace_editor);

$f3->set("CONFIG", $config);

if (isset($variables))
	foreach ($variables as $key=>$v)
		$f3->set($key, $v);

// Killackey CMS
$f3->set('AUTOLOAD', $config["cms_location"]."modules/" . ";" . getcwd()."/modules/");
$f3->set('UI', getcwd()."/");
$f3->set('CACHE', getcwd() . "/tmp/");
$f3->set('ESCAPE',FALSE);
$f3->set('DEBUG', $debug);

require_once("tools/tools.php");

// Make database if it doesn't exist
if (!file_exists($config['dbname'])) {

	if (!is_dir("db")) mkdir("db");

	touch($config["dbname"]);

	$db = new DB\SQL('sqlite:'.$config['dbname']);
	$db->begin();
	$db->exec("CREATE TABLE 'settings' ('setting' TEXT, 'value' TEXT);");
	$db->commit();

	if (!file_exists(getcwd()."/db/.htaccess"))
		file_put_contents(getcwd()."/db/.htaccess", "Deny from all");
}

// Connect to DB
$f3->set('DB', new DB\SQL('sqlite:'.$config['dbname']));


########################################
############ LOAD MODULES ##############
########################################

new admin();

foreach ($f3->get("CONFIG.enabled_modules") as $module) {
	new $module();
}

$f3->run();

function d($e=null)
{
	echo "<pre>";
	print_r($e);
	echo "</pre>";
	die;
}

function htaccess_example() {


echo <<<EOF
<strong>.htaccess does not exist. Please use this snippet to create a .htaccess folder in the client directory.</strong>

<p>
<textarea cols=50 rows=10>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-l
RewriteRule .* cms.php [L,QSA]
RewriteRule .* cms.php [L,QSA]
</textarea>
</p>

<strong>This snippet redirects all requests to cms.php. If you want a folder accessable put this .htaccess file in each folder.</strong>
<p>
<textarea cols=50 rows=10>
RewriteEngine off
</textarea>
</p>

<strong>Hint: CMS modules will attempt to create .htaccess for you where they can.</strong>
EOF;

exit;
}