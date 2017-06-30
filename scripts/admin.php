<?php

class admin {

	static public $signed = false;

	static public $webmasterEmail;
	static public $webmasterPass; 

	static public $clientEmail;
	static public $clientPass;

	function __construct($settings) {
		$f3 = base::instance();

		admin::$webmasterEmail = $GLOBALS["config"]["global_user"];
		admin::$webmasterPass = $GLOBALS["config"]["global_pass"];

		if (isset($settings["user"]))
			admin::$clientEmail = $settings["user"];
		else
			admin::$clientEmail = admin::$webmasterEmail;

		if (isset($settings["pass"]))
			admin::$clientPass = $settings["pass"];
		else
			admin::$clientPass = admin::$webmasterPass;

		unset($GLOBALS["config"]["user"], $GLOBALS["config"]["pass"], $settings["user"], $settings["pass"]);

		if (admin::$clientEmail == null || admin::$clientPass == null)
			base::instance()->error(0, "There are no login credentials available whatsoever. Please check config.ini in cms folder or settings.json for client folder.");

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

		}

		// Expose login screen
		if (!admin::$signed)
		{
			if (isroute("/admin/sendmagiclink")) {

				$mail = new \Mailer();

				k($f3->hive());

			}

			if (isroute("/admin")) {
				$f3->SESSION["show-login"] = true;

				$f3->reroute("/");
			}

			$this->login_routes($f3);
		}
	}


	function login_routes($f3) {

		$f3->route('GET /admin', function ($f3) {
			echo Template::instance()->render("/admin/login.html");
		});

		$f3->route('GET /admin/*', function ($f3) {
			$f3->reroute("/admin");
		});

		$f3->route("POST /admin/login", function ($f3) {
			admin::login($f3);
		});
	}


	function dashboard_routes($f3) {

		// Admin routes
		$f3->route('GET /admin', function ($f3) {

			$f3->reroute("/");
		});

		$f3->route('GET /admin/logout', "admin::logout");
	}


	static public function login ($f3) {
		$post = $f3->get("POST");

		$failure_attempts = Cache::instance()->get("login_failure_attempts");

		if ($failure_attempts > 5)
			sleep(2);

		// Check global user and pass
		if ($post["user"] == admin::$webmasterEmail && $post["pass"] == admin::$webmasterPass)
		{	
			$f3->set("SESSION.user", admin::$webmasterEmail);
			$f3->set("SESSION.root", true);

			$f3->reroute("/");
			return;
		}

		// Check email address similarity
		$percentage = 0;
		similar_text(strtolower($post["user"]), strtolower(admin::$clientEmail), $percentage);

		$emailPassed = false;
		$passPassed = false;
		if ($percentage > 70)
			$emailPassed = true;

		if (strtolower($post["pass"]) == strtolower(admin::$clientPass))
			$passPassed = true;

		if ($emailPassed && $passPassed)
		{
			$f3->set("SESSION.user", $post["user"]);

			$f3->reroute("/");
		}
		else 
		{
			if (!$emailPassed)
				$f3->set("login.email_error", true);

			if (!$passPassed)
				$f3->set("login.pass_error", true);

			Cache::instance()->set("login_failure_attempts", $failure_attempts+1, 3600);

			if ($failure_attempts > 5) {
				Cache::instance()->set("login_failure_attempts", 4, 3600);
				check(2, true, "We see your having issues trying to login. We have sent you a magic link to ".admin::$clientEmail." so you can login through that. If you cannot reach that email contact us on 5446 3371 and ask for Michael or Alan.");
			}

			$f3->set("user", $post["user"]);

			$f3->mock("GET /admin");
			return;
		}
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
		
		if ($f3->exists["GET.previous"])
			$f3->reroute($f3->GET["previous"]);
		else
			$f3->reroute("/");
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
