<?php

class admin extends prefab {

	static public $signed = false;

	function __construct() {
		$f3 = base::instance();

		if ($f3->CONFIG["email"] == "") $f3->CONFIG["email"] = $f3->CONFIG["global_email"];
		if ($f3->CONFIG["pass"] == "") $f3->CONFIG["pass"] = $f3->CONFIG["global_pass"];

		if ($f3->SESSION["user"] == $f3->CONFIG["email"] || $f3->SESSION["user"] == $f3->CONFIG["global_email"])
		{
			admin::$signed = true;

			if ($f3->SESSION["root"]==true)
				$f3->set("webmaster", true);

			$this->dashboard_routes($f3);

			$page = $f3->PATH;
			$page = ($page!="/") ? trim($page, "/") : "index";
			$page = explode("/", $page);

			if ($page[0] == "admin")
				$f3->set('UI', $f3->CMS."adminUI/");

			$f3->route('GET|POST /admin/login', function ($f3) {
				$f3->reroute("/admin");
			});
		}

		$f3->route('GET /admin/imgs/logo.png', function ($f3) {
			$f3->UI = $f3->CMS.'adminUI/imgs/';
			$img = new Image('logo.png');
			$img->render();
			exit;
		});

		if (!admin::$signed)
			$this->login_routes($f3);

		$f3->route('GET /admin/logout', "admin::logout");
		$f3->route('GET /admin/theme', "admin::theme");
		$f3->route('GET /admin/bootstrap.min.css', "admin::bootstrap_css");
		$f3->route('GET /admin/bootstrap.min.js', "admin::bootstrap_js");
		$f3->route("GET /cms", function ($f3) {
			$f3->reroute("/admin", true);
		});

		$f3->route('GET /remote-tools/dbhash', function ($f3) {
			echo sha1_file($f3->get("CONFIG.dbname"));
			exit;
		});
	}

	function login_routes($f3) {

		$f3->route('GET /admin', "admin::login_render");

		$f3->route("POST /admin/login", function ($f3) {
			$f3->set('UI', $f3->CMS."adminUI/");

			admin::login($f3);
		});
	}


	function dashboard_routes($f3) {

		$page = $f3->PATH;
		$page = ($page!="/") ? trim($page, "/") : "index";

		$page = explode("/", $page);

		// Admin routes
		$f3->route('GET /admin', "admin::dashboard_render");
		$f3->route('GET /admin/help', "admin::help");
		$f3->route('GET /admin/settings', "admin::settings");
		$f3->route('POST /admin/update_settings', "admin::update_settings");
	}

	static public function dashboard_render ($f3)
	{
		$f3->set('UI', $f3->CMS."adminUI/");
		echo Template::instance()->render("dashboard.html");
	}

	static public function login_render ($f3)
	{
		// Set default password for inhouse
		if ($f3->HOST == "localhost" || $f3->HOST == "dev.webworksau.com") {
			$f3->POST["email"] = $f3->CONFIG["global_email"];
			$f3->POST["pass"] = $f3->CONFIG["global_pass"];
		}
		else
		{
			$f3->CONFIG["global_email"] = "";
			$f3->CONFIG["global_pass"] = "";
		}

		$f3->set('UI', $f3->CMS."adminUI/");
		echo Template::instance()->render("login.html");
	}

	static public function login ($f3) {
		$post = $f3->get("POST");

		if ($f3->SETTINGS["admin-user"] != "") $f3->CONFIG["email"] = $f3->SETTINGS["admin-user"];
		if ($f3->SETTINGS["admin-user"] != "") $f3->CONFIG["email"] = $f3->SETTINGS["admin-user"];

		// Check global user and pass
		if ($post["user"] == $f3->get("CONFIG.global_email") && $post["pass"] == $f3->get("CONFIG.global_pass"))
		{	
			new \DB\SQL\Session($f3->DB);
			$f3->set("SESSION.user", $post["user"]);
			$f3->set("SESSION.root", true);
		}

		// Check client user and pass
		else if ($post["user"] == $f3->CONFIG["email"] && $post["pass"] == $f3->CONFIG["pass"])
		{
			new \DB\SQL\Session($f3->DB);
			$f3->set("SESSION.user", $post["user"]);
		}
		else 
		{
			admin::login_render($f3);
			return;
		}



		$f3->set('UI', $f3->CMS."adminUI/");
		if ($f3->get("POST.redirectWhere") == "live")
			$f3->reroute("/");
		else
			$f3->reroute("/admin");
	}

	static public function logout ($f3) {
		
		$f3->clear("SESSION");

		if (isset($_SERVER['HTTP_COOKIE'])) {
		    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		    foreach($cookies as $cookie) {
		        $parts = explode('=', $cookie);
		        $name = trim($parts[0]);
		        setcookie($name, '', time()-1000);
		        setcookie($name, '', time()-1000, '/');
		    }
		}
		
		admin::instance()->login_routes($f3);
		$f3->mock("GET /admin");
	}

	static public function help($f3) {
		echo Template::instance()->render("help.html");
	}

	static public function settings($f3) {

		echo Template::instance()->render("settings.html");
	}

	static public function update_settings($f3) {
		$settings = $f3->POST;

		foreach ($settings as $setting=>$value) {
			if (!empty($settings[$setting])) {
				config($setting, $value);
			}
		}

		$f3->reroute("/admin/settings");
	}

	static public function theme($f3) {
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("css/adminstyle.css", "text/css");
		$f3->UI = $tmp;
	}

	static public function bootstrap_css($f3) {
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("css/bootstrap.min.css", "text/css");
		$f3->UI = $tmp;
	}

	static public function bootstrap_js($f3) {
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("js/bootstrap.min.js", "text/javascript");
		$f3->UI = $tmp;
	}
}
