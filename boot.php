<?php




ignore_user_abort(true);
set_time_limit(300); // 5 minutes

GLOBAL $ROOTDIR;
$ROOTDIR = substr(__DIR__, 0, count(__DIR__)-5);

// Super useful alternative to print_r
$krumo = $ROOTDIR."/resources/krumo/class.krumo.php";

// Load tools.php Contains super useful utils.
require_once(__DIR__."/tools/tools.php");

// Robust Utils for handling images.
require_once(__DIR__."/tools/image_handler.php");

// Path to F3, download at http://fatfreeframework.com/
// Load F3 and Setup
$fatfree = $ROOTDIR."/resources/fatfree-core/base.php";
(file_exists($fatfree)) ? $f3 = include $fatfree : d("Fat Free Framework not found at '".$fatfree."'. Please download from http://fatfreeframework.com/");

// Set ROOTDIR for usage in F3
$f3->ROOTDIR = $ROOTDIR;

// Used to skip the ->run command
$f3->REDIRECTING = false;

// Setup framework configuration
$f3->config($ROOTDIR."/cms/constants.ini", true);

// Load specific configuratoin for this server instance
$f3->CONFIG = $GLOBALS["config"] = parse_ini_file($ROOTDIR."/config.ini", true);

// Setup Krumo for use in Templates
Template::instance()->filter("krumo", function ($array) {
	if (!isset($GLOBALS["krumo"])) check(0, 'Krumo path not set in config.ini. Please download Krumo from <a href="https://github.com/mmucklo/krumo">GitHub</a>');
	if (!is_file($GLOBALS["krumo"])) check(0, 'Krumo path incorrectly set in config.ini. Please download Krumo from <a href="https://github.com/mmucklo/krumo">GitHub</a>');
	require_once $GLOBALS["krumo"];
	krumo($array);
});

// Setup Mailer
$f3->mailer = $f3->CONFIG["mailer"];

// $f3->set("mailer.on.failure", function ($error) {
	
// });

// Set up error handler
$f3->ONERROR = function ($f3) { 

	if ($f3->ERROR["code"] == "405")
		return;

	if ($f3->ERROR["code"] == "404" && $f3->handler404 != "")
	{
		header("HTTP/1.0 ".$f3->ERROR["code"]." ".$f3->ERROR["status"]);
		echo Template::instance()->render($f3->handler404);
	}

	else if ($f3->AJAX)
	{	
		$temp = $f3->ERROR;
		$temp["trace"] = explode("\n", $temp["trace"]);
		j($temp);
	}
	else
	{
		header("HTTP/1.0 ".$f3->ERROR["code"]." ".$f3->ERROR["status"]);
		echo Template::instance()->render("admin/error.html");
	}

	$f3->abort();

	$ERROR = $f3->ERROR;

	$email  = "<h1>CMS3 Error</h1>";
	$email .= "<h2>".$ERROR["status"]." (".$ERROR["code"].")"."</h2>";
	$email .= "<p>".$f3->SCHEME."://".$f3->HOST.$f3->BASE.$f3->PATH."</p>";
	$email .= "<p>";
	$email .=   markdown::instance()->convert($ERROR["text"]);
	$email .=   "<br>";
	$email .=   "<pre><code>".$ERROR["trace"]."</code></pre>";
	$email .= "</p>";

	if ($f3->ERROR["code"] != "404" && $f3->ERROR["code"] != "405")
	{
		if (array_key_exists("email_errors", $f3->CONFIG))
		{
			if ($f3->CONFIG["email_errors"])
			{
				$mailer = new Mailer();
				$mailer->addTo("errors@webworksau.com");
				$mailer->setHTML($email);
				$mailer->send("CMS3 Error message");
				unset($mailer);
			}
		}
	}
};

if (array_key_exists("login", $f3->GET))
	$f3->PAGE_CACHE = false;
else
	$f3->PAGE_CACHE = 3600;

// k(mime_content_type2("http://webserver/test_image_without_extension"));

// Require apache rewriting
if (function_exists("apache_get_modules"))
	check(0, !in_array('mod_rewrite', apache_get_modules()), "Please enable mod_rewrite for apache!");

// Required php extension gd for image operations
check(0, !extension_loaded("gd"), "GD extention not loaded!");

// Ensure we can write to client folder
check(0, !writable(getcwd()), "Cannot write to client folder");
check(0, !checkdir(".cms/"), ".cms/ folder does not exist and cannot be created");

// Require folders for operation
check(0, !checkdir(".cms/tmp/"), "<strong>tmp</strong> folder does not exist. Please create tmp folder in client folder.");

// Ensure htaccess is set for rewriting
checkhtaccess(".htaccess");

// Turn off web access to .cms folder
if (!file_exists(".cms/.htaccess"))
	file_put_contents(".cms/.htaccess", "Deny from all");

// Redirect away from
if (isroute("cms.php"))
{
	header("Location: ".$f3->SCHEME."://".$f3->HOST.$f3->BASE."/");
	die;
}

// Check if .cms/settings.json exists
// 	- If it does not, copy default settings
// 	- Check to ensure is valid json
if (!is_file(".cms/settings.json")) {
	
	// Looks like settings.json does not exist
	$default_settings["user"] = "alan@webworksau.com";
	$default_settings["pass"] = "nopass1000";
	$default_settings["version-control"] = "true";
	$default_settings["404-handler"] = "";

	$default_settings["scripts"][] = [
		"class"=>"ckeditor",
		"routes"=>"*",
		"skin"=>"flat"
	];

	$default_settings["scripts"][] = [
		"class"=> "dropimg",
		"routes"=> "*"
	];

	$f3->write(".cms/settings.json", json_encode($default_settings, JSON_PRETTY_PRINT));
}

determine_path();

// Check json directory
checkdir(".cms/json/");

$f3->JIG = new \DB\Jig(".cms/json/", \DB\Jig::FORMAT_JSON);

// Loads settings from .cms/settings.json
// 	- This is the only place settings are configured
// 	- This contains all the script settings
$settings = json_decode($f3->read(".cms/settings.json"), 1);

check (0, $settings == "" || $settings == null, "Syntax error in **.cms/settings.json**");

// If a mailer is set in settings then use that instead
if (array_key_exists("mailer", $settings))
	$f3->mailer = $settings["mailer"];

// If a mailer is set in settings then use that instead
if (array_key_exists("404-handler", $settings))
	$f3->handler404 = $settings["404-handler"];

// Load authentication
new admin($settings);

// Handle phpLiteAdmin routing.
if (admin::$signed) {

	// Turn of page caching
	$f3->PAGE_CACHE = false;

	// Clear cache
	unlink_recursive(".cms/cache", "url");
}

// Load scripts
// 	- Calls isroute($script)
// 	- Throws error if route is not specced in script
// 	- Calls each script with settings

// Core scripts that always load
new toolbar($settings);
new settings_manager($settings);
new version_control($settings);
new review($settings);

check (0, !array_key_exists("scripts", $settings), "No scripts element in settings.json");

foreach ($settings["scripts"] as $script) {

	check(0, !array_key_exists("class", $script) || $script["class"]=="", "Misconfigured script in .cms/settings.ini<br>The class property is missing.", $script);

	check(0, !class_exists($script["class"]), "script ".$f3->highlight($script["class"])." does not exist in .cms/settings.ini", $f3->highlight(json_encode($script, JSON_PRETTY_PRINT)));

	$script["name"] = (array_key_exists("name", $script)) ? $script["name"] : $script["class"];

	if (isroute($script["routes"]) || !isset($script["routes"]) || isroute("/admin/".$script["name"]) || isroute("/admin/".$script["name"]."/*"))
	{
		$f3->set("SETTINGS.".$script["name"], $script);

		if (is_subclass_of($script["class"], "prefab"))
			$f3->set($script["name"], $script["class"]::instance($script));
		else
			$f3->set($script["name"], new $script["class"]($script));
	}
}

// Populate Debug Variable
// - Tack ?debug on the end of a address to output hive data (provided your login to admin)
if (admin::$signed) {
	$hive = $f3->hive();	

	// Clear out all unnesccery elements
	$keys = ['CLI', 'FILE', 'MIME', 'FORMATS', 'MB', 'SEED', 'HEADERS', 'GET', 'POST', 'COOKIE', 'REQUEST', 'SESSION', 'FILES', 'SERVER', 'ENV', 'CMS', 'ACE', 'DB', 'SETTINGS', 'AJAX','AGENT','ALIAS','ALIASES','AUTOLOAD','BASE','BITMASK','BODY','CACHE','CASELESS','CONFIG','CORS','DEBUG','DIACRITICS','DNSBL','EMOJI','ENCODING','ERROR','ESCAPE','EXCEPTION','EXEMPT','FALLBACK','FRAGMENT','HALT','HIGHLIGHT','HOST','IP','JAR','LANGUAGE','LOCALES','LOGS','ONERROR','ONREROUTE','PACKAGE','PARAMS','PATH','PATTERN','PLUGINS','PORT','PREFIX','PREMAP','QUERY','QUIET','RAW','REALM','RESPONSE','ROOT','ROUTES','SCHEME','SERIALIZER','TEMP','TIME','TZ','UI','UNLOAD','UPLOADS','URI','VERB','VERSION','XFRAME', 'JIG', 'CDN', 'webmaster', 'admin'];
	foreach ($hive as $key => $value) if (in_array($key, $keys)) unset($hive[$key]);

	// Show debug screen
	if ($f3->exists("GET.debug"))
		k($hive);

	if ($f3->exists("GET.phpinfo"))
		{ phpinfo(); die; }

	if ($f3->exists("GET.docs") || $f3->exists("GET.doc") || $f3->exists("GET.help"))
	{
		echo Template::instance()->render("/admin/help.html");
		$f3->abort();
	}

	if ($f3->exists("GET.git"))
	{
		echo Template::instance()->render("/revision-control/git-status.html");
		$f3->abort();
	}
}

$f3->route(['GET /', 'GET /@path', 'GET /@path/*'], function ($f3, $params) {

	// Accepted mimetypes to render as a template file
	$accepted_mimetypes = [
		"text/html",
		"text/css",
		"text/plain",
		"application/javascript",
		"application/x-javascript",
	];

	$nocache_mimetypes = [
		"image/png",
		"image/jpeg",
		"image/gif",
		"image/bmp",
		"image/webp"
	];

	if (!$f3->FILE)
	{
		$f3->error("404");
	}
	
	$f3->MIME = mime_content_type2(getcwd()."/".$f3->FILE);

	if ($f3->MIME == "text/html")
		$f3->expire(0);

	if (in_array($f3->MIME, $accepted_mimetypes))
	{
		// Render as a template file

		ob_start('ob_gzhandler') OR ob_start();

		echo Template::instance()->render($f3->FILE, $f3->MIME);
		
		if (!headers_sent() && session_status()!=PHP_SESSION_ACTIVE)
			session_start();
		$out='';
		while (ob_get_level())
			$out=ob_get_clean().$out;

		header('Content-Type: '.$f3->MIME);
		header('Content-Length: '.strlen($out));
		header('Connection: close');
		session_commit();
		echo $out;
		flush();
		if (function_exists('fastcgi_finish_request'))
			fastcgi_finish_request();

	}
	else
	{
		if (admin::$signed && in_array($f3->MIME, $nocache_mimetypes))
		{
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
		}

		header('Content-Type: '.$f3->MIME.';');
		header("Content-length: ".getcwd()."/".filesize($f3->FILE).';');

		// Render as raw data
		readfile(getcwd()."/".$f3->FILE);

		$f3->abort();
	}
}, $f3->PAGE_CACHE);

$f3->route('GET /cms-cdn/*', function ($f3) {
	$ROOTDIR = substr(__DIR__, 0, count(__DIR__)-5);
	if (is_file($file = $ROOTDIR."/cdn/".substr($f3->PATH, 9)))
	{
		$f3->expire(172800);
		header('Content-Type: '.mime_content_type2($file).';');
		header("Content-length: ".filesize($file).';');
		readfile($file);
		$f3->abort();
	} else {
		$f3->error("404");
	}
});

if (!$f3->REDIRECTING)
	$f3->run();

Mailer::processQueue();
new stats ($settings);