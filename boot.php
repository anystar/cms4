<?php

########################################
######## Default configuration #########
########################################
require_once("tools/tools.php");


// Merge config that may be coming from clients folder
if (isset($settings))
	$GLOBALS["settings"] = arrmerge(parse_ini_file("config.ini", true), $settings);
else
	$GLOBALS["settings"] = parse_ini_file("config.ini", true);

$settings = &$GLOBALS["settings"];

##########################################
######## Load Fat Free Framework #########
##########################################
$Did_F3_Load = (($f3 = include $settings["paths"]["f3"]) === false);

if($Did_F3_Load) 
	d("Fat free framework not found at ".$settings["paths"]["f3"].". Please download from http://fatfreeframework.com/");

###############################################
## Run installation if config does not exist ##
###############################################
if (!file_exists(getcwd()."/.htaccess")) {
	include("modules/wizard_creator.php");
	new wizard_creator($config["paths"]["cms"], $settings["paths"]["f3"]);
}

########################################
## Check folder and file permissions  ##
########################################

// Ensure we can write to client folder
writable(getcwd());

// Require folders for operation
if (!checkdir("tmp/")) { echo "<strong>tmp</strong> folder does not exist. Please create tmp folder in client folder.";exit; }
if (!checkdir("db/")) { echo "<strong>db</strong> folder does not exist. Please create db folder in client folder.";exit; }

// Require files for operation
checkhtaccess();
checkfile($settings["database"]);

// Require php extentions for operation
if (!extension_loaded("SQLite3")) {
	echo "SQLite3 php extention not loaded!";
	die;
}

if (!extension_loaded("gd")) {
	echo "GD extention not loaded!";
	die;
}

########################################
####### phpLiteAdmin redirecting #######
########################################

if ($settings['enable_phpliteadmin']) {
	$db_sub_folder = "admindb";

	if ($f3->HOST == "localhost" || $f3->HOST == "dev.webworksau.com")
		$adminDBpassword = $settings["global_pass"];

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
		include($config["paths"]["cms"]."/phpliteadmin/phpliteadmin.php");
		exit;
	}
}

########################################
########## Fatfree framework ###########
########################################
$f3->set("SETTINGS", $settings);

if (isset($variables))
	foreach ($variables as $key=>$v)
		$f3->set($key, $v);

$f3->set('AUTOLOAD', $settings["paths"]["cms"]."/modules/" . ";" . getcwd()."/modules/");
$f3->set('UI', getcwd()."/");
$f3->set('CACHE', getcwd() . "/tmp/");
$f3->set('ESCAPE',FALSE);
$f3->set('DEBUG', $settings["debug"]);

$f3->set("CMS", $settings["paths"]["cms"]);
$f3->set("ACE", $settings["cdn"]["ace_editor"]);

// Make database if it doesn't exist
if (!file_exists(getcwd()."/".$settings['database'])) {

	if (!is_dir("db")) mkdir("db");

	touch($settings["database"]);

	$db = new DB\SQL('sqlite:'.$settings['database']);
	$db->begin();
	$db->exec("CREATE TABLE 'settings' ('setting' TEXT, 'value' TEXT);");
	$db->commit();

	if (!file_exists(getcwd()."/db/.htaccess"))
		file_put_contents(getcwd()."/db/.htaccess", "Deny from all");
}

// Connect to DB
$f3->set('DB', new DB\SQL('sqlite:'.$settings['database']));

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

foreach ($settings["enabled_modules"] as $module)
	new $module();

$f3->run();