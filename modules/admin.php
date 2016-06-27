<?php


class admin extends prefab {

	static public $signed = false;

	function __construct() {
		$f3 = base::instance();

		if ($f3->exists("COOKIE.PHPSESSID"))
		{
			admin::$signed = true;
			$this->dashboard_routes($f3);

			$page = $f3->PATH;
			$page = ($page!="/") ? trim($page, "/") : "index";
			$page = explode("/", $page);

			if ($page[0] == "admin")
				$f3->set('UI', $f3->CMS."adminUI/");
		}


		if (!admin::$signed)
			$this->login_routes($f3);

		$f3->route('GET /admin/logout', "admin::logout");
		$f3->route('GET /admin/theme', "admin::theme");
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

		$f3->route('GET /admin/theme', "admin::theme");

		$f3->route('GET /admin/help', "admin::help");
		$f3->route('GET /admin/settings', "admin::settings");
	
	}

	static public function dashboard_render ($f3)
	{
		$f3->set('UI', $f3->CMS."adminUI/");
		echo Template::instance()->render("dashboard.html");
	}

	static public function login_render ($f3)
	{
		$f3->set('UI', $f3->CMS."adminUI/");
		echo Template::instance()->render("login.html");
	}

	static public function login ($f3) {
		$post = $f3->get("POST");

		// Check global user and pass
		if ($post["user"] != $f3->get("CONFIG.global_email") && $post["pass"] != $f3->get("CONFIG.global_pass"))
		{
			// Check client user and pass
			if ($post["user"] != $f3->get("client.email") && $post["pass"] != $f3->get("client.pass"))
			{
				admin::login_render();
				return;
			}
		}

		new Session();
		$f3->set("SESSION.user", $post["user"]);

		$f3->set('UI', $f3->CMS."adminUI/");

		if ($f3->get("POST.redirecttolive"))
			$f3->reroute("/");
		else
			admin::dashboard_render($f3);
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



	static public function gallery ($f3) {
		if (gallery::exists())
			echo Template::instance()->render("gallery/gallery.html");
		else
			echo Template::instance()->render("gallery/nogallery.html");
	}

	static public function theme($f3) {
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("css/adminstyle.css", "text/css");
		$f3->UI = $tmp;
	}
}
