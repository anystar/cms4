<?php

class contact extends prefab
{
	private $namespace;

	static $moduleName = "Contact Form";
	static $email_template = "generic_email_template.html";
	static $port = 25;
	static $systemID = null;

	function __construct ($namespace) {
		$this->namespace = $namespace;

		$f3 = base::instance();

		// Sets the page for which this module loads on
		$default = $f3->exists("SETTINGS[contact.page]") ? $f3->get("SETTINGS[contact.page]") : "/contact";
		$f3->set("SETTINGS[contact.page]", $default);

		// Sets the default email template
		if ($f3->exists("SETTINGS[contact.email_template]")) contact::$email_template = $f3->get("SETTINGS[contact.email_template]");
		if ($f3->exists("SETTINGS[contact.port]")) contact::$port = $f3->get("SETTINGS[contact.port]");

		$this->routes($f3);

		$pageToLoadOn = $f3->get("SETTINGS[contact.page]");

		if ($f3->POST["return"] == null) {
			$f3->set("contact_return_page", $f3->PATH);
		} else {
			$f3->set("contact_return_page", $f3->POST["return"]);
		}

		// Load contact form on this page.
		if ($pageToLoadOn == $f3->PATH || $pageToLoadOn == "all") {			
			$this->load();
		}

		if (admin::$signed)
			$this->admin_routes($f3);
	}

	function routes($f3) {

		$f3->route("GET /captcha", function ($f3) {
			$this->captcha($f3);
		});

		$f3->route("POST /contact", function ($f3, $params) {

			if ($f3->get("SETTINGS[contact.page]") == "all") {
				if ($f3->POST["return"])
					$mock = $f3->get("POST.return");
				else
					$mock = "/contact";
			}
			else
				$mock = $f3->get("SETTINGS[contact.page]");

			$result = contact::validate();

			if ($result)
			{
				if (file_exists("contact_success.html")) {
					$f3->reroute("/contact_success");
				}
				else
					echo Template::instance()->render("/contact/contact_success.html");
			}
			else
			{
				$snippet = \Template::instance()->render("/contact/contact_form.html");

				$f3->set("contact_form", $snippet);
				$f3->set("contact.html", $snippet);

				$f3->mock("GET ". $mock);
				die;
			}
		});

	}

	function admin_routes ($f3)
	{
		$f3->route("GET /admin/{$this->namespace}", function ($f3) {
			$this->retreive_content();

			// Get settings
			setting_use_namespace($this->namespace);
			$f3->set("contact.email", setting("email"));
			$f3->set("contact.name", setting("name"));
			$f3->set("contact.subject", setting("subject"));
			setting_clear_namespace();

			$f3->contact["fields"] = base::instance()->DB->exec("SELECT * FROM {$this->namespace} ORDER BY `order`");

			echo Template::instance()->render("/contact/contact.html");
		});

		$f3->route("GET /admin/{$this->namespace}/install", function () {
			$this->install();
		});

		$f3->route("GET /admin/{$this->namespace}/email_template", function () {
			$this->retreive_content();

			echo Template::instance()->render("/contact/email_template/generic_email_template.html");
		});

		$f3->route("GET /admin/{$this->namespace}/documentation", function ($f3) {
			echo Template::instance()->render("/contact/documentation.html");
		});

		$f3->route("POST /admin/{$this->namespace}/settings", function ($f3) {
			$db = $f3->DB;

			setting_use_namespace($this->namespace);

			setting("email", $f3->POST["email"]);
			setting("name", $f3->POST["name"]);
			setting("subject", $f3->POST["subject"]);

			setting_clear_namespace();

			$f3->reroute("/admin/".$this->namespace);
		});		
		$f3->route("POST /admin/{$this->namespace}/update_field/@field", "contact::update_field");
		$f3->route("POST /admin/{$this->namespace}/add_field", "contact::add_field");
		$f3->route("GET /admin/{$this->namespace}/delete_field/@field", "contact::delete_field");
	}


	function captcha($f3) {

		$img = new Image();

		$img->captcha("/contact/Abel-Regular.ttf", 16, 5,"SESSION.captcha_code");

		$img->render();
	}

	function retreive_content()
	{	
		$f3 = base::instance();
		$db = $f3->get("DB");

		if (!$f3->exists("{$this->namespace}.form"))
		{
			$result = $db->exec("SELECT * FROM contact_form ORDER BY `order`");

			foreach ($result as $r) 
				$formcompiled[$r["id"]] = $r;

			$f3->set("{$this->namespace}.form", $formcompiled);
		}

		$snippet = \Template::instance()->render("/contact/contact_form.html");

		$f3->set("{$this->namespace}.html", $snippet);
	}

	function validate ()
	{
		$this->retreive_content();

		$f3 = f3::instance();
		$post = $f3->get("POST");

		$sendemail = true;

		if ($post["captcha"] != $f3->SESSION["captcha_code"]) {
			$sendemail = false;
			$f3->set("{$this->namespace}.captcha_error", true);
		}

		foreach ($post as $key => $value)
		{
			$matches = array();
			preg_match("#(\d+)$#", $key, $matches);
			$id = $matches[0];

			if ($id == null) break;

			$field = &$f3->ref("form.".$id);

			$field["value"] = $value;

			switch ($field["type"])
			{
				case "name":
					if (strlen($value) == 0)
						$field["has_error"] = "true";

					$f3->set("fromName", $value);
				break;

				case "text":
					if (strlen($value) == 0)
						$field["has_error"] = "true";
				break;

				case "textarea":
					if (strlen($value) < 5)
						$field["has_error"] = "true";
				break;

				case "number":
					if (strlen($value) < 5)
						$field["has_error"] = "true";
				break;

				case "email":
					if (!filter_var($value, FILTER_VALIDATE_EMAIL))
						$field["error"] = true;

					$f3->set("fromAddress", $value);
				break;
			}

			if ($field["error"] == true)
				$sendemail = false;
		}

		if ($sendemail)
		{
			$this->send_email();
			return true;
		}
		else
			return false;
	}

	function send_email ()
	{
		$f3 = f3::instance();
		$db = $f3->get("DB");

		setting_use_namespace($this->namespace);
			
		if ($f3->POST["sendto"])
			$toAddress = $f3->POST["sendto"];
		else
			setting("email");

		$toName = setting("name");
		$subject = setting("value");

 		$fromName = $f3->get("fromName");

 		if ($f3->exists("fromAddress"))
			$fromAddress = $f3->get("fromAddress");
		else
			$fromAddress = setting("from_address");

		// Worst case just set it to admin@webworksau.com...
		$fromAddress = "admin@webworksau.com";

		$smtp = new SMTP("127.0.0.1", contact::$port, "", "", "");

		$smtp->set('To', '"'.$toName.'" <'.$toAddress.'>');
		$smtp->set('From', '"'.$fromName.'" <'.$fromAddress.'>');
		$smtp->set('Subject', $subject);
		$smtp->set('Content-Type', 'text/html');

		$f3->set("contact.subject", $subject);

		if (file_exists(contact::$email_template))
			$body = Template::instance()->render(contact::$email_template);
		else
			error::log("No email template found!");

		setting_clear_namespace();

		$smtp->send($body);

		return true;
	}

	static function update_field ($f3, $params) {
		$db = Base::instance()->DB;
		$field = $params["field"];

		if ($f3->POST["label"])
			$db->exec("UPDATE contact_form SET label=? WHERE id=?", [$f3->POST["label"], $field]);

		if ($f3->POST["type"])
			$db->exec("UPDATE contact_form SET type=? WHERE id=?", [$f3->POST["type"], $field]);

		if ($f3->POST["error_message"])
			$db->exec("UPDATE contact_form SET error_message=? WHERE id=?", [$f3->POST["error_message"], $field]);

		if ($f3->POST["placeholder"])
			$db->exec("UPDATE contact_form SET placeholder=? WHERE id=?", [$f3->POST["placeholder"], $field]);

		if ($f3->POST["order"])
			$db->exec("UPDATE contact_form SET `order`=? WHERE id=?", [$f3->POST["order"], $field]);

		$f3->reroute("/admin/{$this->namespace}");
	}

	function install()
	{
		$db = f3::instance()->get("DB");

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->namespace}'");
		
		if (empty($result))
		{
			$db->begin();
			$db->exec("CREATE TABLE `{$this->namespace}` ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'label' TEXT, 'type' INTEGER, 'order' INTEGER, 'error_message' TEXT, 'placeholder' TEXT);");
			$db->exec("INSERT INTO `{$this->namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('1','Name','name','1','Please add your name.',NULL);");
			$db->exec("INSERT INTO `{$this->namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('2','Phone','number','2','Please add your phone number.','Landline or Mobile');");
			$db->exec("INSERT INTO `{$this->namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('3','Email Address','email','3','Please add a valid email address.',NULL);");
			$db->exec("INSERT INTO `{$this->namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('4','Message','textarea','4','Please add a message.',NULL);");
			$db->commit();
		}

		setting_use_namespace($this->namespace);
		setting("email", "joe@example.com", false);
		setting("name", "Joe", false);
		setting("subject", "Example subject line", false);
		setting_clear_namespace();

		base::instance()->reroute("/admin/".$this->namespace);
	}


	static public function add_field($f3) {

		$db = base::instance()->DB;
		$p = $f3->POST;

		$db->exec("INSERT INTO contact_form (label, type, `order`, error_message, placeholder) VALUES (?, ?, ?, ?, ?)",[$p["label"], $p["type"], $p["order"], $p["error_message"], $p["placeholder"]]);

		$f3->reroute("/admin/{$this->namespace}");
	}

	static public function delete_field ($f3, $params) {
		base::instance()->DB->exec("DELETE FROM contact_form WHERE id=?", $params["field"]);
		$f3->reroute("/admin/{$this->namespace}");
	}

}