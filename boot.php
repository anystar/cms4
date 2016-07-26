<?php

########################################
######## Default configuration #########
########################################

if (!isset($config['enabled_modules']))
	$config['enabled_modules'] = [ "pages", "file_manager", "content_blocks", "contact", "gallery", "banners" ];

if (!isset($config['disabled_modules']))
	$config['disabled_modules'] = [ ];

if (isset($config['additional_modules']))
	$config['enabled_modules'] = array_merge($config['enabled_modules'], $config['additional_modules']);

$config = parse_ini_file("config.ini", true);

########################################
## Check folder and file permissions  ##
########################################
// Required folders for operation
if (!file_exists(getcwd()."/tmp/")) { echo "<strong>tmp</strong> folder does not exist. Please create tmp folder in client folder with group writable permissions. (chmod g+w tmp or chmod 755 db)";exit; }
if (!is_writable(getcwd()."/tmp/")) { echo "Please make <strong>tmp</strong> folder writable by group";exit; }
if (!file_exists(getcwd()."/db")) { echo "<strong>db</strong> folder does not exist. Please create db folder in client folder with group writable permissions. (chmod g+w db chmod 755 db)";exit; }
if (!is_writable(getcwd()."/db/")) { echo "Please make <strong>db</strong> folder writable by group";exit; }
if (!is_writable(getcwd()."/".$config["database"])) { echo "Please make database file writable."; exit;}
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
// Webworks CMS
if (!file_exists($config["paths"]["cms"]))
	d("Webworks CMS not found at $cms_location. Please update $\cms_location variable to point to CMS folder.");

// Fat free framework
if(($f3 = include $config["paths"]["f3"]) === false)
	d("Fat free framework not found at $f3_location. Please download from http://fatfreeframework.com/");

$f3->set("CONFIG", $config);

if (isset($variables))
	foreach ($variables as $key=>$v)
		$f3->set($key, $v);

$f3->set('AUTOLOAD', $config["paths"]["cms"]."/modules/" . ";" . getcwd()."/modules/");
$f3->set('UI', getcwd()."/");
$f3->set('CACHE', getcwd() . "/tmp/");
$f3->set('ESCAPE',FALSE);
$f3->set('DEBUG', $config["debug"]);

$f3->set("CMS", $config["paths"]["cms"]);
$f3->set("ACE", $config["cdn"]["ace_editor"]);

// Make database if it doesn't exist
if (!file_exists(getcwd()."/".$config['database'])) {

	if (!is_dir("db")) mkdir("db");

	touch($config["database"]);

	$db = new DB\SQL('sqlite:'.$config['database']);
	$db->begin();
	$db->exec("CREATE TABLE 'settings' ('setting' TEXT, 'value' TEXT);");
	$db->commit();

	if (!file_exists(getcwd()."/db/.htaccess"))
		file_put_contents(getcwd()."/db/.htaccess", "Deny from all");
}

// Connect to DB
$f3->set('DB', new DB\SQL('sqlite:'.$config['database']));

########################################
############ LOAD MODULES ##############
########################################

new admin();

 
$modules = explode(",", str_replace(' ','',$config["enabled_modules"]));

foreach ($modules as $module) {
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