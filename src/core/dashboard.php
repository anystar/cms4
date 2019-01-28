<?php

class dashboard extends prefab {

	private $settings;

	function __construct($settings) {
		$f3 = Base::instance();

		$this->settings = $settings;

		if (isset($f3->GET["settings"]))
		{
			// Load buttons from scripts
			foreach ($f3->SETTINGS["scripts"] as $script) 
			{
				if (method_exists($script["class"], "dashboard"))
				{
					$methodChecker = new ReflectionMethod($script["class"], "dashboard");

					check (0, !$methodChecker->isStatic(), "Dashboard class is not static for **".$script["class"]."**");

					$f3->script_buttons[] = $script["class"]::dashboard($script);
				}
			}

			$f3->ROOT = false;
			if ($f3->SESSION["root"])
				$f3->ROOT = true;

			echo \Template::instance()->render("/dashboard/settings-navigation.html", "text/html");
			exit;
		}

		if (admin::$signed)
			$this->routes($f3);
	}

	function routes($f3) {

		$f3->route("GET /admin/seo-settings", function ($f3) {

			$page = getcwd()."/".$f3->GET["page"];

			check (0, !file_exists($page), "Page parsed to settings does not exist");
			check (0, mime_content_type2($f3->GET["page"]) != "text/html", "Page parsed to settings is not a html page");

			$contents = file_get_contents($page);

			if(preg_match("/<title>(.+)<\/title>/i", $contents, $matches))
			     $f3->TITLE = $matches[1];
			else
			     $f3->TITLE = null;

			$f3->DESCRIPTION = parseDescription($contents);
			$f3->CANONICAL = $this->settings["canonical-url"];

			if (file_exists(getcwd()."/sitemap.txt"))
				$f3->SITEMAP = file_get_contents(getcwd()."/sitemap.txt");

			echo \Template::instance()->render("/dashboard/seo-settings.html", "text/html");
		});

		$f3->route("POST /admin/seo-settings", function ($f3) {
			
			$page = getcwd()."/".$f3->POST["page"];

			check (0, !file_exists($page), "Page parsed to settings does not exist");
			check (0, mime_content_type2($f3->POST["page"]) != "text/html", "Page parsed to settings is not a html page");

			$contents = file_get_contents($page);

			$contents = preg_replace("/<title>(.+)<\/title>/i", "<title>".$f3->POST["page-title"]."</title>", $contents);
			$return = parseDescription($contents, preg_replace( "/\r|\n/", "", $f3->POST["page-description"]));

			if ($return !== null)
				$contents = $return;

			check (0, strlen($contents) == 0, "Critial: Stopping settings manager from writing blank data!");

			file_put_contents($page, $contents);

			// Update sitemap
			if ($f3->POST["sitemap"] != "") {
				file_put_contents(getcwd()."/sitemap.txt", $f3->POST["sitemap"]);
			}
			else
			{
				if (file_exists(getcwd()."/sitemap.txt"))
					unlink(getcwd()."/sitemap.txt");
			}

			// Update canonical address
			setting("canonical-url", rtrim($f3->POST["canonical"]), "/");

			$f3->reroute("/admin/seo-settings?alert=updated&page=".$f3->POST["page"]);

		});

		$f3->route("GET /admin/login-settings", function ($f3) {

			$f3->set("website_login_address", $this->settings["user"]);
			$f3->set("website_login_password", $this->settings["pass"]);
			$f3->set("contact_email", $this->settings["contact-email"]);

			echo \Template::instance()->render("/dashboard/login-settings.html", "text/html");
		});

		$f3->route("POST /admin/login-settings", function ($f3) {

			setting("user", $f3->POST["website-login-address"]);
			setting("pass", $f3->POST["website-login-password"]);

			$f3->reroute("/admin/login-settings?alert=updated");
		});


		$f3->route("GET /admin/dashboard/script.js", function ($f3) {

			echo \Template::instance()->render("/dashboard/script.js", "application/javascript");
			$f3->abort();
		});

	}
}