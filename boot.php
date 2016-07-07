<?php

########################################
######## Default configuration #########
########################################
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
####### remote vs local configs ########
########################################

if ($_SERVER["DOCUMENT_ROOT"] == "/home/alan/www/")
{
	// Local machine (Alans Dell)
	$cms_location = "/home/cms/";
	$f3_location  = "/home/alan/www/f3/lib/base.php";
	$ckeditor_location = "<script src=\"http://localhost/ckeditor/ckeditor.js\"></script>";
	$debug = true;

	if (isset($config["remote_tools"]))
	{		
		$hash = file_get_contents($config["remote_tools"] . "dbhash");
		
		if (sha1_file($config["dbname"]) != $hash)
			d("local vs remote hashes do not match");
	}
}
else
{
	// Webworks Server
	$cms_location = "/home/cms/";
	$f3_location  = "/home/f3/lib/base.php";
	$ckeditor_location = "<script src=\"http://webworksau.com/ckeditor/ckeditor.js\"></script>";
	$debug = false;
}


########################################
########## Fatfree framework ###########
########################################

// Fat free framework
if(($f3 = include $f3_location) === false)
	d("Fat free framework not found at $f3_location. Please download from http://fatfreeframework.com/");

// Webworks CMS
if (!file_exists($cms_location))
	d("Webworks CMS not found at $cms_location. Please update $\cms_location variable to point to CMS folder.");

$f3->set("client", $config);
$f3->set("CMS", $cms_location);
$f3->set("ckeditor", $ckeditor_location);

$f3->set("CONFIG", $config);

if (isset($variables))
	foreach ($variables as $key=>$v)
		$f3->set($key, $v);

// Killackey CMS
$f3->set('AUTOLOAD', $cms_location."modules/" . ";" . getcwd()."/modules/");
$f3->set('UI', getcwd()."/");
$f3->set('CACHE', getcwd() . "/tmp/");
$f3->set('ESCAPE',FALSE);
$f3->set('DEBUG', $debug);


// Make database if it doesn't exist
if (!file_exists($config['dbname'])) {

	if (!is_dir("db")) mkdir("db");

	touch($config["dbname"]);

	$db = new DB\SQL('sqlite:'.$config['dbname']);
	$db->begin();
	$db->exec("CREATE TABLE 'settings' ('setting' TEXT, 'value' TEXT);");
	$db->commit();
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
