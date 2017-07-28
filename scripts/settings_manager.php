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

			$f3->set("website_login_address", $this->settings["user"]);
			$f3->set("website_login_password", $this->settings["pass"]);
			$f3->set("contact_email", $this->settings["contact-email"]);

			echo Template::instance()->render("/settings-manager/settings-manager.html", "text/html");
		});

		$f3->route("POST /admin/settings", function ($f3) {

			$config = json_decode($f3->read(getcwd()."/.cms/settings.json"), true);

			unset($config["user"]);
			unset($config["pass"]);
			unset($config["email-contact"]);

			$temp["user"] = $f3->POST["website-login-address"];
			$temp["pass"] = $f3->POST["website-login-password"];
			$temp["contact-email"] = $f3->POST["contact-email"];

			$config = array_merge($temp, $config);

			$f3->write(getcwd()."/.cms/settings.json", json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

			$f3->reroute("/admin/settings");
		});

	}
}