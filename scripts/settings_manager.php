<?php

class settings_manager extends prefab {

	private $settings;

	function __construct($settings) {

		$this->settings = $settings;

		if (admin::$signed)
			$this->routes(base::instance());
	}

	function routes($f3) {

		$f3->route("GET /admin/settings", function ($f3) {

			echo Template::instance()->render("/settings-manager/settings-navigation.html", "text/html");
		});

		$f3->route("GET /admin/seo-settings", function ($f3) {

			$page = getcwd()."/".$f3->GET["page"];

			check (0, !file_exists($page), "Page parsed to settings does not exist");
			check (0, mime_content_type2($f3->GET["page"]) != "text/html", "Page parsed to settings is not a html page");

			$contents = file_get_contents($page);

			if(preg_match("/<title>(.+)<\/title>/i", $contents, $matches))
			     $f3->TITLE = $matches[1];
			else
			     $f3->TITLE = "";

			$f3->DESCRIPTION = parseDescription($contents);

			echo Template::instance()->render("/settings-manager/seo-settings.html", "text/html");
		});

		$f3->route("POST /admin/seo-settings", function ($f3) {
			
			$page = getcwd()."/".$f3->POST["page"];

			check (0, !file_exists($page), "Page parsed to settings does not exist");
			check (0, mime_content_type2($f3->POST["page"]) != "text/html", "Page parsed to settings is not a html page");

			$contents = file_get_contents($page);

			$contents = preg_replace("/<title>(.+)<\/title>/i", "<title>".$f3->POST["page-title"]."</title>", $contents);
			$contents = parseDescription($contents, $f3->POST["page-description"]);

			check (0, strlen($contents) == 0, "Critial: Stopping settings manager from writing blank data!");

			file_put_contents($page, $contents);

			$f3->reroute("/admin/seo-settings?page=".$f3->POST["page"]);

		});

		$f3->route("GET /admin/login-settings", function ($f3) {

			$f3->set("website_login_address", $this->settings["user"]);
			$f3->set("website_login_password", $this->settings["pass"]);
			$f3->set("contact_email", $this->settings["contact-email"]);

			echo Template::instance()->render("/settings-manager/login-settings.html", "text/html");
		});

		$f3->route("POST /admin/login-settings", function ($f3) {

			$config = json_decode($f3->read(getcwd()."/.cms/settings.json"), true);

			unset($config["user"]);
			unset($config["pass"]);
			unset($config["email-contact"]);

			$temp["user"] = $f3->POST["website-login-address"];
			$temp["pass"] = $f3->POST["website-login-password"];
			$temp["contact-email"] = $f3->POST["contact-email"];

			$config = array_merge($temp, $config);

			$f3->write(getcwd()."/.cms/settings.json", json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

			$f3->reroute("/admin/login-settings");
		});

	}
}