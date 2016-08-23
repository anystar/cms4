<?php
########################################
######## Default configuration #########
########################################
require_once("tools/tools.php");

// Merge config that may be coming from clients folder
if (isset($config))
	$config = array_merge_recursive(parse_ini_file("config.ini", true), $config);
else
	$config = parse_ini_file("config.ini", true);

###############################################
## Run installation if config does not exist ##
###############################################
if (!file_exists(getcwd()."/.htaccess")) {
	include("modules/wizard_creator.php");
	new wizard_creator($config["paths"]["cms"], $config["paths"]["f3"]);
}

########################################
## Check folder and file permissions  ##
########################################
// Required folders for operation
if (!file_exists($config["paths"]["cms"])) d("Webworks CMS not found at". $config["paths"]["cms"].". Please update $\cms_location variable to point to CMS folder.");
if(($f3 = include $config["paths"]["f3"]) === false) d("Fat free framework not found at ".$config["paths"]["f3"].". Please download from http://fatfreeframework.com/");
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

	if ($f3->HOST == "localhost" || $f3->HOST == "dev.webworksau.com")
		$adminDBpassword = $config["global_pass"];

	$url = pathinfo($_SERVER["REQUEST_URI"]);
	$dir = basename($url["dirname"]);

	$dir2 = explode("?", $url["basename"]);

	if ($url["filename"] == "phpliteadmin")
	{
		include($config["paths"]["cms"]."/phpliteadmin/dynamic_myAdmin.php");
		exit;
	}

	if ($dir == $db_sub_folder || $url["basename"] == $db_sub_folder || $dir2[0] == $db_sub_folder)
	{
		include($config["paths"]["cms"]."/phpliteadmin.php");
		exit;
	}
}

########################################
########## Fatfree framework ###########
########################################
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

####################################################
########## Override config from Database ###########
####################################################

$check = $f3->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");

if ($check)
{
	$config_definition = parse_ini_file("config-definitions.ini", true);
	
	$settings = $f3->DB->exec("SELECT * FROM settings");
	foreach ($settings as $config)
	{	
		$setting = $config["setting"];
		$value   = $config["value"];

		if (isset($config_definition[$setting]))
			if (!$config_definition[$setting]["permission"])
				continue;

		$f3->SETTINGS[$config["setting"]] = $config["value"];
	}
}


########################################
############ Load modules ##############
########################################

new admin();

foreach ($f3->CONFIG["enabled_modules"] as $module)
	new $module();

$f3->run();