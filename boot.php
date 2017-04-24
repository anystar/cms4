<?php
// Very handy global functions
require_once("tools/tools.php");

########################################
######## Default configuration #########
########################################

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

$f3->set("DEBUG", $settings["debug"]);

if($Did_F3_Load) 
	d("Fat free framework not found at ".$settings["paths"]["f3"].". Please download from http://fatfreeframework.com/");

determine_path($f3);

// Redirect cms to admin
if (isroute("/cms")) {
	$f3->reroute("/admin", true);
	exit;
}

########################################
## Check folder and file permissions  ##
########################################

// Required php extentions for operation
if (!extension_loaded("SQLite3")) {
	echo "SQLite3 php extention not loaded!";
	die;
}

// Required php extension gd for image operations
if (!extension_loaded("gd")) {
	echo "GD extention not loaded!";
	die;
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
	$f3->DB->exec("CREATE TABLE 'licenses' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'module' TEXT, 'name' TEXT, 'namespace' TEXT, 'key' TEXT, 'order' INT)");
else
{
	// Patch with order column
	$checklicensetable =  base::instance()->DB->exec("PRAGMA table_info(licenses)");
	$found = false;	
	foreach ($checklicensetable as $column) if ($column["name"] == "order") $found = true;
	if (!$found) $f3->DB->exec("ALTER TABLE 'licenses' ADD COLUMN 'order' INT");
}

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

###############################################
############ Get brought modules ##############
###############################################

// Get modules from licensing table
$f3->installed_modules = $f3->DB->exec("SELECT * FROM licenses ORDER BY `order` ASC");

########################################
############ Load modules ##############
########################################

admin::instance();
backup::instance();

	foreach ($f3->installed_modules as $module)
	{
		if (class_exists($module["module"]))
		{
			if (is_subclass_of($module["module"], "prefab"))
				$module["module"]::instance($module["namespace"]);
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


############################################################
############ Populate debug template variable ##############
############################################################
if (admin::$signed) {
	$hive = $f3->hive();	

	$keys = ['HEADERS', 'GET', 'POST', 'COOKIE', 'REQUEST', 'SESSION', 'FILES', 'SERVER', 'ENV', 'CMS', 'ACE', 'DB', 'SETTINGS', 'AJAX','AGENT','ALIAS','ALIASES','AUTOLOAD','BASE','BITMASK','BODY','CACHE','CASELESS','CONFIG','CORS','DEBUG','DIACRITICS','DNSBL','EMOJI','ENCODING','ERROR','ESCAPE','EXCEPTION','EXEMPT','FALLBACK','FRAGMENT','HALT','HIGHLIGHT','HOST','IP','JAR','LANGUAGE','LOCALES','LOGS','ONERROR','ONREROUTE','PACKAGE','PARAMS','PATH','PATTERN','PLUGINS','PORT','PREFIX','PREMAP','QUERY','QUIET','RAW','REALM','RESPONSE','ROOT','ROUTES','SCHEME','SERIALIZER','TEMP','TIME','TZ','UI','UNLOAD','UPLOADS','URI','VERB','VERSION','XFRAME'];
	foreach ( $hive as $key => $value ) {
		if ( in_array( $key, $keys ) ) {
			unset( $hive[ $key ] );
		}
	}

	$hive = json_encode($hive);

	$code .= "<script>";
	$code .= "var obj = ".$hive.";";
	$code .= "console.log({'Template Variables' : obj});";
	$code .= "</script>";

	$f3->set("debug", $code);

	Template::instance()->filter("krumo", function ($array) {
		require_once $GLOBALS["settings"]["paths"]["krumo"];

		return krumo($array);
	});

	Template::instance()->filter("die", function () { die; });
}

############################################
############ Set global route ##############
############################################

$f3->route(['GET /', 'GET /@path', 'GET /@path/*'], function ($f3, $params) {

	// Accepted mimetypes to render as a template file
	$accepted_mimetypes = [
		"text/html",
		"text/css",
		"text/plain",
		"application/javascript",
		"application/x-javascript",
	];

	if (!$f3->FILE)
		$f3->error("404");

	$mime_type = mime_content_type2(getcwd()."/".$f3->FILE);

	if ($mime_type == "text/html")
	{
		$f3->expire(0);
	} else {
		$f3->expire(172800);
	}

	header('Content-Type: '.$mime_type.';');
	header('Content-Length: ' . filesize(getcwd()."/".$f3->FILE));

	if (in_array($mime_type, $accepted_mimetypes))
		// Render as a template file
		echo Template::instance()->render($f3->FILE, $mime_type);
	else
	{
		// Render as raw data
		echo readfile(getcwd()."/".$f3->FILE);
	}
});

$f3->run();