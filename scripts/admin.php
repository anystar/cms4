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

			// Capture the last 6 characters and see if it is "/admin"
			if (substr($f3->PATH, -6, strlen($f3->PATH)) == "/admin") {
				$path = substr($f3->PATH, 0, strlen($f3->PATH)-6);

				if ($path=="")
					$f3->reroute("/");
				else
					$f3->reroute($path);
			}

			// Capture the last 6 characters and see if it is "/logout"
			if (substr($f3->PATH, -7, strlen($f3->PATH)) == "/logout") {
				admin::logout();
				$path_without_logout = substr($f3->PATH, 0, strlen($f3->PATH)-7);

				if ($path_without_logout == "")
					$f3->reroute("/");
				else
					$f3->reroute($path_without_logout);
			}

			// Provide logout route
			$f3->route('GET /admin/logout', "admin::logout");

			// Lets redirect away from login screen
			$f3->route('GET|POST /admin/login', function ($f3) {
				$f3->reroute("/admin");
			});

			if ($f3->SESSION["root"]==true)
				$f3->set("webmaster", true);

		}

		// Initilize stats prematurely
		if (admin::$signed)
		{
			$f3->ADMIN = true;
			new stats ($settings);
		}

		// Expose login screen
		if (!admin::$signed)
		{
			if (isroute("/admin/sendmagiclink")) {
				$this->sendMagicLink();
			}

			if (array_key_exists("login", $f3->GET))
				$f3->SESSION["show-login"] = true;				

			if (isroute("/admin")) {
				$f3->SESSION["show-login"] = true;

				$f3->reroute("/?login");
			}

			if (substr($f3->PATH, -6, strlen($f3->PATH)) == "/login") {
				
				if ($f3->VERB == "POST") {
					admin::login($f3);

					$f3->reroute(substr($f3->PATH, 0, strlen($f3->PATH)-6));
				}
			}

			// Capture the last 6 characters and see if it is "/admin"
			if (substr($f3->PATH, -6, strlen($f3->PATH)) == "/admin") {
				$f3->SESSION["show-login"] = true;

				$f3->reroute(substr($f3->PATH, 0, strlen($f3->PATH)-6));
			}

			$this->login_routes($f3);
		}


		base::instance()->route("GET /admin/logo_xs.png", function ($f3) {

			$file = $GLOBALS["ROOTDIR"]."/cms/scriptsUI/admin/logo_xs.png";
			header('Content-Type: image/png');
			header("Content-length: ".filesize($file));
			header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 30))); // 1 hour
			header("Cache-Control: public"); //HTTP 1.1
			echo readfile($file);
			$f3->abort();
		});

		base::instance()->route("GET /admin/logo_sm.png", function ($f3) {

			$file = $GLOBALS["ROOTDIR"]."/cms/scriptsUI/admin/logo_sm.png";
			header('Content-Type: image/png');
			header("Content-length: ".filesize($file));
			header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 30))); // 1 hour
			header("Cache-Control: public"); //HTTP 1.1
			echo readfile($file);
			$f3->abort();
		});

		base::instance()->route("GET /admin/logo_md.png", function ($f3) {

			$file = $GLOBALS["ROOTDIR"]."/cms/scriptsUI/admin/logo_md.png";
			header('Content-Type: image/png');
			header("Content-length: ".filesize($file));
			header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 30))); // 1 hour
			header("Cache-Control: public"); //HTTP 1.1
			echo readfile($file);
			$f3->abort();
		});

		base::instance()->route("GET /admin/logo_lg.png", function ($f3) {

			$file = $GLOBALS["ROOTDIR"]."/cms/scriptsUI/admin/logo_lg.png";
			header('Content-Type: image/png');
			header("Content-length: ".filesize($file));
			header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 30))); // 1 hour
			header("Cache-Control: public"); //HTTP 1.1
			echo readfile($file);
			$f3->abort();
		});

		base::instance()->route("GET /admin/styles.css", function ($f3) {

			echo \Template::instance()->render("/admin/styles.css", "text/css");
			$f3->abort();
		});

	}


	function login_routes($f3) {

		$f3->route('GET /admin', function ($f3) {
			echo Template::instance()->render("/admin/login.html");
			$f3->abort();
		});

		$f3->route('GET /admin/*', function ($f3) {
			$f3->reroute("/admin");
		});

		$f3->route('GET /admin/login', function ($f3) {

			if ($f3->exists("GET.key"))
			{
				if (cache::instance()->get("login.hash")) {
					if ($f3->get("GET.key") == cache::instance()->get("login.hash"))
					{
						$f3->set("SESSION.user", admin::$clientEmail);

						$f3->reroute("/");
						die;
					}
				}
			}

			$f3->SESSION["show-login"] = true;
			$f3->reroute("/");

		});
	}

	static public function login ($f3) {
		$post = $f3->get("POST");

		$f3->clear("SESSION.login.email_error");
		$f3->clear("SESSION.login.pass_error");
		$f3->set("SESSION.login.user", "");

		$failure_attempts = Cache::instance()->get("login_failure_attempts");

		if ($failure_attempts > 4)
			sleep(2);

		// Check global user and pass
		if ($post["user"] == admin::$webmasterEmail && $post["pass"] == admin::$webmasterPass)
		{	
			$f3->set("SESSION.user", admin::$webmasterEmail);
			$f3->set("SESSION.root", true);

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
			$f3->set("SESSION.user", admin::$clientEmail);

			return;
		}
		else 
		{
			if (!$emailPassed)
				$f3->set("SESSION.login.email_error", true);

			if (!$passPassed)
				$f3->set("SESSION.login.pass_error", true);

			Cache::instance()->set("login_failure_attempts", $failure_attempts+1, 3600);

			if ($failure_attempts > 4) {

				admin::sendMagicLink();

				Cache::instance()->set("login_failure_attempts", 4, 3600);
				check(2, true, "We see your having issues trying to login. We have sent you a magic link to ".admin::$clientEmail." so you can login through that. If you cannot reach that email contact us on 5446 3371 and ask for Michael or Alan.");
			}

			$f3->set("SESSION.login.user", $post["user"]);

			$f3->SESSION["show-login"] = true;

			return;
		}
	}

	static public function sendMagicLink () {

		if (!cache::instance()->exists("login.hash"))
			cache::instance()->set("login.hash", str_replace(".", "/", uniqid("", true).uniqid("", true).uniqid("", true).uniqid("", true)));

		$hive["CDN"] = base::instance()->CDN;
		$hive["URL"] = base::instance()->get("SCHEME")."://".base::instance()->get("HOST").base::instance()->get("BASE")."/admin/login?key=".cache::instance()->get("login.hash");
		$hive["HOST"] = base::instance()->get("HOST");
		$hive["SCHEME"] = base::instance()->get("SCHEME");

		$body = \Template::instance()->render("/admin/magiclink.html", null, $hive);

		$mailer = new \Mailer();
		$mailer->addTo(admin::$clientEmail);
		$mailer->setHTML($body);
		$mailer->send("Login Link");
		unset($mailer);
	}

	static public function logout () {
		
		base::instance()->clear("SESSION");

		if (isset($_SERVER['HTTP_COOKIE'])) {
		    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		    foreach($cookies as $cookie) {
		        $parts = explode('=', $cookie);
		        $name = trim($parts[0]);
		        setcookie($name, '', time()-1000);
		        setcookie($name, '', time()-1000, '/');
		    }
		}
	}
}
