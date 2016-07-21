<?php

class wizard_creator {


	function __construct($cms, $f3) {

		// Fat free framework
		if(($f3 = include $f3) === false)
			d("Fat free framework not found at $f3_location. Please download from http://fatfreeframework.com/");

		$f3->set("CACHE", false);
		$f3->UI = $cms."adminUI/";
		
		$f3->TEMP = $cms."adminUI/wizard/tmp/";
		$f3->DEBUG = 3;

		$this->routes($f3);

		$f3->run();

		exit();
	}

	function routes($f3) {

		// TODO: Insert routes for this module
		$f3->route("GET /cms.php", function ($f3) {
			
			$f3->linuxuser = posix_getpwuid(fileowner($_SERVER["SCRIPT_FILENAME"]))["name"];
			$f3->POST["password"] = "KRXPoErYxHgmZD7o6Ky0";

			echo Template::instance()->render("wizard/step1.html");

		});

		$f3->route("POST /cms.php?step=2", function ($f3) {

			set_include_path("/home/phpseclib/");
			include('Net/SSH2.php');

			$ssh = new Net_SSH2('localhost');
			if (!$ssh->login('root', 'KRXPoErYxHgmZD7o6Ky0')) {
			    exit('Login Failed');
			}

			$dir = getcwd() . "/";			
			$user = posix_getpwuid(fileowner($_SERVER["SCRIPT_FILENAME"]))["name"];

			$ssh->exec("mkdir {$dir}/tmp");
			$ssh->exec("mkdir {$dir}/db");
			$ssh->exec("touch {$dir}/db/cmsdb");			
	
			$ssh->exec("chown www-data:www-data {$dir}/tmp");
			$ssh->exec("chown www-data:www-data {$dir}/db");						
			$ssh->exec("chown www-data:www-data {$dir}/db/cmsdb");

			d("Go check the directory now $dir");

		});

		$f3->route("GET /cms.php?css=bootstrap.min.css", function ($f3) {
			echo Template::instance()->render("css/bootstrap.min.css", "text/css");
		});

		$f3->route("GET /cms.php?css=theme", function ($f3) {
			echo Template::instance()->render("css/adminstyle.css", "text/css");
		});

		$f3->route('GET /cms.php?img=logo', function ($f3) {
			$img = new Image('imgs/logo.png');
			$img->render();
			exit;
		});
	}
}
