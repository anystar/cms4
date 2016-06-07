<?php

if ($_SERVER["DOCUMENT_ROOT"] == "/home/alan/www/")
{
	// Local machine (Alans Dell)
	$cms_location = "/home/alan/www/killackeyCMS/";
	$f3_location  = "/home/alan/www/f3/lib/base.php";
	$ckeditor_location = "<script src="http://localhost/ckeditor/ckeditor.js"></script>";
}
else
{
	// Webworks Server
	$cms_location = "/home/cms/";
	$f3_location  = "/home/f3/lib/base.php";
	$ckeditor_location = "<script src="http://webworksau.com/ckeditor/ckeditor.js"></script>";
}

// Fat free framework
$f3 = include($f3_location);

$f3->set("client", $config);
$f3->set("CMS", $cms_location);
$f3->set("ckeditor", $ckeditor_location);

//if (!@mkdir("/tmp/", 0700)) { die("failed to make tmp directory. Please create tmp directory in client folder."); }

// Killackey CMS
$f3->set('AUTOLOAD', $cms_location);
$f3->set('UI', getcwd()."/");
$f3->set('CACHE', getcwd() . "/tmp/");
$f3->set('ESCAPE',FALSE);
$f3->set('DEBUG', 1);

// Connect to DB
$f3->set('DB', new DB\SQL('sqlite:db/cmsdb'));

$f3->route("GET /mkdir", function () {

	echo exec("whoami");
	die;
});


// CMS routes
$f3->route(array('GET /', 'GET /@page'), function ($f3, $params) {

	page::render($f3, $params);
});

$f3->route("GET /contact", function ($f3, $params) {

	if (page::exists("contact")) {
		if (!contact::$isLoaded)
			contact::load();
	}

	page::render($f3, array("page"=>"contact"));
});

$f3->route("POST /contact", function ($f3, $params) {

	$result = contact::validate();

	if ($result)
		page::render($f3, array("page"=>"contact_success"));
	else
		$f3->mock("GET /contact");
});


// Admin routes
$f3->route('GET /admin/theme', "admin::theme");

$f3->route("POST /admin/login", function ($f3) {
	$f3->set('UI', $f3->CMS."adminUI/");
	admin::login($f3);
});


$f3->route('POST /admin/page/save', function ($f3, $params) {
	if (!admin::$signed) { return; }

	page::save_inline($f3);
});

$f3->route("GET /cms", function ($f3) {
	$f3->reroute("/admin", true);
});



$f3->route(array("GET /admin", "GET /admin/*"), function ($f3) {

	$f3->set('UI', $f3->CMS."adminUI/");

	// If admin is not logged in, pull up login page.
	if (!admin::$signed) 
	{
		admin::login_render();
		return;
	}

	// Admin routes
	$f3->route('GET /admin', "admin::dashboard_render");
	$f3->route('GET /admin/logout', "admin::logout");

	$f3->route('GET /admin/theme', "admin::theme");

	$f3->route('GET /admin/help', "admin::help");
	$f3->route('GET /admin/settings', "admin::settings");
	
	$f3->route('GET /admin/contact', "admin::contact");
	$f3->route('GET /admin/contact/generate', "contact::generate");

	// Admin content blocks
	$f3->route('GET /admin/pages', 'admin::pages_admin_render');
	$f3->route('GET /admin/page/edit/@page', "admin::page_edit_render");

	$f3->route('GET /admin/ckeditor_config.js', "page::ckeditor");

	$f3->run();
});


$f3->run();

function d($e=null)
{
	echo "<pre>";
	print_r($e);
	echo "</pre>";
	die;
}
