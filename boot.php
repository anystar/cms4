<?php
$GLOBALS['time_start'] = microtime(true); 

ignore_user_abort(true);
set_time_limit(300); // 5 minutes

GLOBAL $ROOTDIR;
$ROOTDIR = substr(__DIR__, 0, count(__DIR__)-5);

// Super useful alternative to print_r
$krumo = $ROOTDIR."/resources/krumo/class.krumo.php";

// Load tools.php Contains super useful utils.
require (__DIR__."/tools/tools.php");

// Robust Utils for handling images.
require (__DIR__."/tools/image_handler.php");

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

// Load specific configuration for this server instance
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

// Google
if (isroute("google71e58acf252a99b8.html"))
	die ("google-site-verification: google71e58acf252a99b8.html");

// Attempt to start session.
try {
   session_start();
} catch(ErrorExpression $e) {
   session_regenerate_id();
   session_start();
} 

if (array_key_exists("login", $f3->GET) || array_key_exists("show-login", $f3->SESSION))
{
	if ($f3->SESSION["show-login"] == true)
		$f3->PAGE_CACHE = false;
}
else
	$f3->PAGE_CACHE = 3600;


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

// Prevent access to .git folder
if (is_dir(getcwd()."/.git"))
	if (!is_file(getcwd()."/.git/.htaccess"))
		file_put_contents(getcwd()."/.git/.htaccess", "Deny from all");

// Ensure mail directory is created
check(0, !checkdir(".cms/mail/"), "<strong>.cms/mail</strong> folder does not exist. Please create mail folder in .cms directory.");

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
load_settings();

check (0, $f3->SETTINGS == "" || $f3->SETTINGS == null, "Syntax error in **.cms/settings.json**");

// If force-https is true redirect to secured website.
if ($f3->CONFIG["developer"] != true) {
	if (array_key_exists("force-https", $f3->SETTINGS))
		if ($f3->SETTINGS["force-https"] == true)
			if ($f3->SCHEME=="http")
				$f3->reroute("https://".$f3->HOST.$f3->BASE.$f3->PATH . (($f3->QUERY!="") ? "?".$f3->QUERY : ""));

	if (array_key_exists("force-http", $f3->SETTINGS))
		if ($f3->SETTINGS["force-http"] == true)
			if ($f3->SCHEME=="https")
				$f3->reroute("http://".$f3->HOST.$f3->BASE.$f3->PATH . (($f3->QUERY!="") ? "?".$f3->QUERY : ""));
}

// If a mailer is set in settings then use that instead
if (array_key_exists("mailer", $f3->SETTINGS))
	$f3->mailer = $f3->SETTINGS["mailer"];

// If a mailer is set in settings then use that instead
if (array_key_exists("404-handler", $f3->SETTINGS))
	$f3->handler404 = $f3->SETTINGS["404-handler"];

// Load authentication
new admin($f3->SETTINGS);

// Handle phpLiteAdmin routing.
if (admin::$signed) {

	// Turn of page caching
	$f3->PAGE_CACHE = false;

	// Clear cache
	unlink_recursive(".cms/cache", "url");
}

// Redirect any requests to the canonical address
if (array_key_exists("canonical-url", $f3->SETTINGS))
{
	if (!$f3->CONFIG["developer"])
	{
		if (filter_var($f3->SETTINGS["canonical-url"], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED))
		{
			if (($f3->SCHEME."://".$f3->HOST) != $f3->SETTINGS["canonical-url"]) {
				//header("Location: ".$f3->SETTINGS["canonical-url"].$f3->URI, true, 301);
				//exit();
			}
		}
	}
}

// Load scripts
// 	- Calls isroute($script)
// 	- Throws error if route is not specced in script
// 	- Calls each script with settings

// Core scripts that always load
new docs($f3->SETTINGS);
new unit_test($f3->SETTINGS);
new toolbar($f3->SETTINGS);
new dashboard($f3->SETTINGS);
new version_control($f3->SETTINGS);
new review($f3->SETTINGS);

if ($f3->SESSION["root"])
	new script_editor($f3->SETTINGS);

check (0, !array_key_exists("scripts", $f3->SETTINGS), "No scripts element in settings.json");

foreach ($f3->SETTINGS["scripts"] as $key=>$script) {
	// Load script when on set route
	if (isroute($script["routes"]) || !isset($script["routes"]) || isroute("/admin/".$script["name"]) || isroute("/admin/".$script["name"]."/*"))
	{
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
	$keys = ['CLI', 'MIME', 'FORMATS', 'MB', 'SEED', 'HEADERS', 'GET', 'POST', 'COOKIE', 'REQUEST', 'SESSION', 'FILES', 'SERVER', 'ENV', 'CMS', 'ACE', 'DB', 'SETTINGS', 'AJAX','AGENT','ALIAS','ALIASES','AUTOLOAD','BASE','BITMASK','BODY','CACHE','CASELESS','CONFIG','CORS','DEBUG','DIACRITICS','DNSBL','EMOJI','ENCODING','ERROR','ESCAPE','EXCEPTION','EXEMPT','FALLBACK','FRAGMENT','HALT','HIGHLIGHT','HOST','IP','JAR','LANGUAGE','LOCALES','LOGS','ONERROR','ONREROUTE','PACKAGE','PARAMS','PATH','PATTERN','PLUGINS','PORT','PREFIX','PREMAP','QUERY','QUIET','RAW','REALM','RESPONSE','ROOT','ROUTES','SCHEME','SERIALIZER','TEMP','TIME','TZ','UI','UNLOAD','UPLOADS','URI','VERB','VERSION','XFRAME', 'JIG', 'CDN', 'webmaster', 'admin'];
	foreach ($hive as $key => $value) if (in_array($key, $keys)) unset($hive[$key]);

	// Show debug screen
	if ($f3->exists("GET.debug"))
		k($hive);

	if ($f3->exists("GET.phpinfo"))
		{ phpinfo(); die; }

	if ($f3->exists("GET.git"))
	{
		ob_start('ob_gzhandler') OR ob_start();
		echo Template::instance()->render("/revision-control/git-status.html");
		$f3->abort();
	}
}

$f3->route(['GET /', 'GET /@path', 'GET /@path/*'], function ($f3, $params) {

	// Accepted mimetypes to render as a template file
	$accepted_mimetypes = [
		"text/html",
		"text/plain"
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

		if ($f3->MIME != "text/html")
			$f3->expire(604800);

		header('Content-Type: '.$f3->MIME);
		header('Content-Length: '.strlen($out));
		header('Connection: close');
		session_commit();
		echo $out;
		flush();
		if (function_exists('fastcgi_finish_request'))
			fastcgi_finish_request();

		$out = null; // clear memory
		unset($out); // Kill the variable
	}
	else
	{
		// Render as a template file
		ob_start('ob_gzhandler') OR ob_start();

		if (admin::$signed && in_array($f3->MIME, $nocache_mimetypes))
		{
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
			$f3->expire(0);
		}
		else
			$f3->expire(604800);
	
		$out = gzencode(file_get_contents(getcwd()."/".$f3->FILE));

		header('Content-Type: '.$f3->MIME);
		header('Content-Encoding: gzip');
		header('Content-Length: '.strlen($out));
		header('Connection: close');
		session_commit();
		echo $out;
		flush();
		if (function_exists('fastcgi_finish_request'))
			fastcgi_finish_request();

		$out = null; // clear memory
		unset($out); // Kill the variable
	}
}, $f3->PAGE_CACHE);

$f3->route('GET /cms-cdn/*', function ($f3) {
	$ROOTDIR = substr(__DIR__, 0, count(__DIR__)-5);
	if (is_file($file = $ROOTDIR."/cdn/".substr($f3->PATH, 9)))
	{
		$f3->expire(604800);
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

// Process mail queue
if (isset(Mailer::$queue))
{
	if (count(Mailer::$queue) != 0)
	{	
		foreach (Mailer::$queue as $mailer)
		{
			if ($mailer->subject == "")
				$mailer->subject = "Email from your website";

			if (array_check_value($f3->CONFIG, "developer", true))
			{
				file_put_contents("developer_mail_cache.html", $mailer->message["html"]);
			}
			else
			{
				// Lets run the Antispam Filter
				// if (isset($mailer->antispam))
				// {
				// 	require_once $ROOTDIR."/cms/tools/sblam_test_post.php";
				// 	$result = sblamtestpost($mailer->antispam);
				// }

				$mailer->send($mailer->subject);
				file_put_json(".cms/mail/mail.".time().".".substr(uniqid(), 0, 4).".html", $mailer);

				// // Passed sblam test
				// if ($result < 1)
				// {

				// }
				// // Failed sblam test
				// else
				// {
				// 	// This code here is temporary just so we can see how well
				// 	// this sblam filter works.
				// 	$mailer->recipients = null;
				// 	$mailer->addTo("errors@webworksau.com", "Web Works");
				// 	$mailer->send("SBLAM Returning mail");
				// }
			}
		}
	}
}

new stats ($f3->SETTINGS);

// Delete Error Log
if (file_exists(realpath("error_log")))
{
	try  {
		unlink(realpath("error_log"));
	} catch(Exception $e) { }
}