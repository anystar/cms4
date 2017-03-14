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
$f3->set('DEBUG', $settings["debug"]);

if($Did_F3_Load) 
	d("Fat free framework not found at ".$settings["paths"]["f3"].". Please download from http://fatfreeframework.com/");

########################################
## Check folder and file permissions  ##
########################################

// Require php extentions for operation
if (!extension_loaded("SQLite3")) {
	echo "SQLite3 php extention not loaded!";
	die;
}

// Require php extension gd for image operations
if (!extension_loaded("gd")) {
	echo "GD extention not loaded!";
	die;
}

// Require random compat for random_byte not available in php <7
if (!file_exists($settings["paths"]["random_compat"]."/lib/random.php")) {
	echo "Random compatability library not found. Please download it from https://github.com/paragonie/random_compat";
	exit;
}

// Ensure we can write to client folder
writable(getcwd());

// Require folders for operation
if (!checkdir("tmp/")) { echo "<strong>tmp</strong> folder does not exist. Please create tmp folder in client folder.";exit; }
if (!checkdir("db/")) { echo "<strong>db</strong> folder does not exist. Please create db folder in client folder.";exit; }

// Require files for operations
checkhtaccess(".htaccess");
checkfile($settings["database"]);
checkdeny($settings["database"]);

// If we are calling cms.php..
if ($f3->PATH == "/cms.php") $f3->reroute("/admin");

##########################################
############ Error handling ##############
##########################################
// $error_log = $settings["paths"]["cms"]."/error.log";
// writable($error_log);
require_once("tools/error_handler.php");
error::construct(new DB\SQL('sqlite:'.$error_log), $f3->HOST.$f3->PATH);


########################################
####### phpLiteAdmin redirecting #######
########################################

if ($settings['enable_phpliteadmin']) {
	$db_sub_folder = "admindb";

	if ($f3->HOST == $settings["dev_host"])
		$settings["phpliteadmin_pass"] = $settings["webmaster_pass"];

	$url = pathinfo($_SERVER["REQUEST_URI"]);
	$dir = basename($url["dirname"]);

	$dir2 = explode("?", $url["basename"]);

	if ($url["filename"] == "phpliteadmin")
	{
		include($settings["paths"]["cms"]."/phpliteadmin/dynamic_myAdmin.php");
		exit;
	}

	if ($dir == $db_sub_folder || $url["basename"] == $db_sub_folder || $dir2[0] == $db_sub_folder)
	{
		include($settings["paths"]["cms"]."/phpliteadmin/phpliteadmin.php");
		exit;
	}
}

########################################
########## Fatfree framework ###########
########################################
if (isset($variables))
	foreach ($variables as $key=>$v)
		$f3->set($key, $v);

$f3->set('AUTOLOAD', $settings["paths"]["cms"]."/modules/" . ";" . getcwd()."/modules/");

$f3->UI = getcwd()."/;"; 								// Client directory files
$f3->UI .= rtrim($settings["paths"]["cms"], "/") . "/modulesUI/";   // Modules UI

if ($settings["cache"])
	$f3->set('CACHE', getcwd() . "/tmp/");
else
	$f3->set('CACHE', false);

$f3->set('ESCAPE',FALSE);

$f3->set("CMS", $settings["paths"]["cms"]);
$f3->set("ACE", $settings["cdn"]["ace_editor"]);

// Make database if it doesn't exist
if (!file_exists(getcwd()."/".$settings['database'])) {

	if (!is_dir("db")) mkdir("db");

	touch($settings["database"]);

	$db = new DB\SQL('sqlite:'.$settings['database']);

	if (!file_exists(getcwd()."/db/.htaccess"))
		file_put_contents(getcwd()."/db/.htaccess", "Deny from all");
}

// Connect to DB
$f3->set('DB', new DB\SQL('sqlite:'.$settings['database']));

// Ensure settings table exists
$result = $f3->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
if (!$result)
	$f3->DB->exec("CREATE TABLE 'settings' ('setting' TEXT, 'value' TEXT);");

// Ensure license table exists
$result = $f3->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='licenses'");
if (!$result)
	$f3->DB->exec("CREATE TABLE 'licenses' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'module' TEXT, 'name' TEXT, 'namespace' TEXT, 'key' TEXT)");


######################################################
########## Override settings from Database ###########
######################################################

$check = $f3->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");

if ($check)
{
	$fromDB = $f3->DB->exec("SELECT * FROM settings");

	foreach ($fromDB as $c)
	{	
		$setting = $c["setting"];
		$value   = $c["value"];

		$settings[$c["setting"]] = $c["value"];
	}
}

$f3->SETTINGS = $GLOBALS["settings"];

//$f3->developer = setting("developer_mode");
$f3->developer = 1;

determine_path($f3);

###############################################
############ Get brought modules ##############
###############################################

// Get modules from licensing table
$f3->installed_modules = $f3->DB->exec("SELECT * FROM licenses");

########################################
############ Load modules ##############
########################################

admin::instance();
content::instance();


	foreach ($f3->installed_modules as $module)
	{
		if (class_exists($module["module"]))
		{
			if (is_subclass_of($module["module"], "prefab"))
				$module["module"]::instance();
			else
				new $module["module"]($module["namespace"]);
		}
	}


	if ($extra_modules)
	{
		$f3->AUTOLOAD .= ";".getcwd()."/".$extra_modules_ui;

		foreach ($extra_modules as $module)
			new $module["module"]($module["namespace"]);
	}

	if (admin::$signed)
		new store();

	new debug();

$f3->run();