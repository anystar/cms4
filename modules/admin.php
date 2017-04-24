<?php

class admin extends prefab {

	static public $signed = false;

	static public $webmasterEmail;
	static public $webmasterPass; 

	static public $clientEmail;
	static public $clientPass;

	function __construct() {
		$f3 = base::instance();

		$this->load_settings($f3);
		
		// Are we logged in?
		if ($f3->SESSION["user"] == admin::$clientEmail || $f3->SESSION["user"] == admin::$webmasterEmail)
		{

			admin::$signed = true;

			// Load dashboard routes
			$this->dashboard_routes($f3);

			// Lets redirect away from login screen
			$f3->route('GET|POST /admin/login', function ($f3) {
				$f3->reroute("/admin");
			});

			if ($f3->SESSION["root"]==true)
				$f3->set("webmaster", true);

			// Load admin scripts
			$inlinecode = Template::instance()->render("/admin/toolbar.html", "text/plain");
		
			if (headers_sent()) {
				k("hit");
			}

			$f3->concat("admin", $inlinecode);

			echo Template::instance()->render("/ckeditor/css/toolbar.css", "text/css"); 
die;
		}

		// Expose login screen
		if (!admin::$signed)
			$this->login_routes($f3);
	}


	function login_routes($f3) {

		$f3->route('GET /admin', "admin::login_render");

		$f3->route('GET /admin/*', function ($f3) {
			$f3->reroute("/admin");
		});

		$f3->route("POST /admin/login", function ($f3) {
			admin::login($f3);
		});
	}


	function dashboard_routes($f3) {

		$page = $f3->PATH;
		$page = ($page!="/") ? trim($page, "/") : "index";

		$page = explode("/", $page);

		// Admin routes
		$f3->route('GET /admin', "admin::dashboard_render");
		$f3->route('GET /admin/webmaster', function () {
			echo Template::instance()->render("/admin/webmaster.html");
		});

		$f3->route('GET /admin/help', "admin::help");

		if (isroute('/admin/settings') || isroute('/admin'))
		{
			//$this->load_settings($f3);

			$pass = setting("admin_pass");
			if (strlen($pass) > 0)
				$pass = str_repeat("*", strlen($pass));

			$f3->website_mode = true;
			if (setting("developer_mode_permanently_off"))
				$f3->website_mode = false;

			$f3->set("password", $pass);
		}

		$f3->route('POST /admin/update_settings', "admin::update_settings");

		$f3->route('POST /admin/developer_mode', function ($f3) {

			if ($f3->POST['permanentlyoff']=="yes")
			{
				setting("developer_mode_permanently_off", true);
				setting("developer_mode", false);
			}
			else
				setting("developer_mode", !$f3->developer);

			$f3->reroute("/admin/");
		});

		$f3->route('GET /admin/template', function () {
			echo Template::instance()->render("/admin/admin_template.html");
		});

		$f3->route('GET /admin/logout', "admin::logout");
	}

	function load_settings ($f3) {
		admin::$webmasterEmail = $f3->SETTINGS["webmaster_email"];
		admin::$webmasterPass = $f3->SETTINGS["webmaster_pass"];

		if (isset($f3->SETTINGS["email"]))
			admin::$clientEmail = $f3->SETTINGS["email"];
		else
			admin::$clientEmail = admin::$webmasterEmail;

		if (isset($f3->SETTINGS["pass"]))
			admin::$clientPass = $f3->SETTINGS["pass"];
		else
			admin::$clientPass = admin::$webmasterPass;

		if ($f3->SETTINGS["admin_email"])
			admin::$clientEmail = $f3->SETTINGS["admin_email"];

		if ($f3->SETTINGS["admin_pass"])
			admin::$clientPass = $f3->SETTINGS["admin_pass"];
	
		unset($f3->SETTINGS["webmaster_email"], $f3->SETTINGS["webmaster_pass"], $f3->SETTINGS["pass"],$f3->SETTINGS["admin_pass"]);

		if (admin::$clientEmail == null || admin::$clientPass == null)
		{
			echo "Warning, no email or password set to be able to login to admin panel.";
			die;
		}
	}

	static public function dashboard_render ($f3)
	{
		echo Template::instance()->render("/admin/dashboard.html");
	}

	static public function login_render ($f3)
	{
		// Set default password for inhouse
		if ($f3->HOST == $f3->SETTINGS["dev_host"]) {
			//$f3->POST["user"] = admin::$webmasterEmail;
			//$f3->POST["pass"] = admin::$webmasterPass;
		}

		echo Template::instance()->render("/admin/login.html");
	}

	static public function login ($f3) {
		$post = $f3->get("POST");

		// Check global user and pass
		if ($post["user"] == admin::$webmasterEmail && $post["pass"] == admin::$webmasterPass)
		{	
			new \DB\SQL\Session($f3->DB);
			$f3->set("SESSION.user", admin::$webmasterEmail);
			$f3->set("SESSION.root", true);
		}

		// Check client user and pass
		else if ($post["user"] == admin::$clientEmail && $post["pass"] == admin::$clientPass)
		{
			new \DB\SQL\Session($f3->DB);
			$f3->set("SESSION.user", $post["user"]);
		}
		else 
		{
			if ($post["user"] != admin::$clientEmail && $post["user"] != admin::$webmasterEmail)
				$f3->set("login.email_error", true);

			if ($post["pass"] != admin::$clientPass && $post["pass"] != admin::$webmasterPass)
				$f3->set("login.pass_error", true);

			$f3->mock("GET /admin");
			return;
		}

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
		
		$f3->reroute("/admin");
	}

	static public function help($f3) {
		echo Template::instance()->render("/admin/help.html");
	}

	static public function settings($f3) {

		echo Template::instance()->render("/admin/settings.html");
	}

	static public function update_settings($f3) {
		$settings = $f3->POST;

		foreach ($settings as $setting=>$value) {
			if (!empty($settings[$setting])) {
				setting($setting, $value);
			}
		}

		$f3->reroute("/admin");
	}
}
