<?php

class contact extends prefab
{
	private $namespace;
	private $route 			= "/contact;/about";
	private $email_template = "generic_email_template.html";
	private $return_on_error_to = "/contact";
	private $contact_success = "/contact_success";
	private $submit_route	 = "/contact";

	private $smtp_server	= "127.0.0.1";
	private $port 			= 25;

	function __construct ($namespace) {
		$this->namespace = $namespace;

		$f3 = base::instance();

		setting_use_namespace($namespace);

		// load routes from settings
		if ($value = setting("routes"))
			$this->routes = $value;

		// email template location
		if ($value = setting("email_template"))
			$this->email_template = $value;

		// What smtp server?
		if ($value = setting("smtp_server"))
			$this->smtp_server = $value;

		// What port for smtp server?
		if ($value = setting("port"))
			$this->port = $value;


		setting_clear_namespace();

		// Set up routes
		$this->routes($f3);

		// Set up admin routes
		if (admin::$signed)
			$this->admin_routes($f3);

		// Only load if on particular route
		if (isroute($this->route) || isroute("/admin/{$namespace}"))
		{
			// Retreive contents of form
			$this->retreive_content();

			if ($f3->POST["actionid"] == "{$namespace}_submitted")
			{
				// Validate the form and submit email
				if ($error = $this->validate())
					$this->send_email();
				
				// Change HTTP verb so the route doesn't look like a POST route
				$f3->VERB = "GET";

				$this->set_html_snippet();
				return;
			}
			
			$this->set_html_snippet();
		}
	}

	function routes($f3) {
		$namespace = $this->namespace;

		$f3->route("GET /{$namespace}/captcha", function ($f3) {
			$this->captcha($f3);
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
		
		$f3->route("POST /admin/{$this->namespace}/update_field/@field", 
			function ($f3, $params) { $this->update_field($f3, $params); });
		
		$f3->route("POST /admin/{$this->namespace}/add_field", 			 
			function ($f3, $params) { $this->add_field($f3); });
		
		$f3->route("GET /admin/{$this->namespace}/delete_field/@field",  
			function ($f3, $params) { $this->delete_field($f3, $params); });

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

		$result = $db->exec("SELECT * FROM {$this->namespace} ORDER BY `order`");

		foreach ($result as $r) 
		{
			$r["id"] = substr(sha1($r["id"].$r["label"]), 0, 8);
			$formcompiled[$r["id"]] = $r;
		}

		$f3->set("{$this->namespace}.form", $formcompiled);
	}

	function set_html_snippet() {
		$f3 = base::instance();

		$contact = $f3->get("{$this->namespace}");

		$contact["action"] = $f3->BASE.$f3->PATH;
		$contact["actionid"] = $this->namespace."_submitted";

		// Temp hive to generate a html snippet
		$temphive = [
			"BASE" => $f3->BASE,
			"contact" => $contact, 
			"namespace" => $this->namespace
		];

		$snippet = \Template::instance()->render("/contact/contact_form.html", null, $temphive);
		$f3->set("{$this->namespace}.html", $snippet);
	}

	function validate ()
	{
		$f3 = f3::instance();
		$post = $f3->get("POST");
		$namespace = $this->namespace;

		// Temp variable that we'll falisify if we cannot send mail
		$sendemail = true;

		// Check to see if captcha is valid
		if ($post["captcha"] != $f3->SESSION["captcha_code"]) {
			$sendemail = false;
			$f3->set("{$this->namespace}.captcha_error", true);
		}

		$form = $f3->get("{$namespace}.form");

		foreach ($form as $key=>$field) 
		{
			$value = $post[$field["id"]];

			switch ($field["type"])
			{
				case "name":
					if (strlen($value) == 0)
						$form[$key]["has_error"] = true;

					$f3->set("fromName", $value);
				break;

				case "text":
					if (strlen($value) == 0)
						$form[$key]["has_error"] = true;
				break;

				case "textarea":
					if (strlen($value) < 5)
						$form[$key]["has_error"] = true;
				break;

				case "number":
					if (strlen($value) < 5)
						$form[$key]["has_error"] = true;
				break;

				case "email":
					if (!filter_var($value, FILTER_VALIDATE_EMAIL))
						$form[$key]["has_error"] = true;

					$f3->set("fromAddress", $value);
				break;
			}

			if ($form[$key]["has_error"] == true)
				$sendemail = false;
		}

		$f3->set("{$namespace}.form", $form);

		if ($sendemail)
			return true;
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

	function update_field ($f3, $params) {
		$db = Base::instance()->DB;
		$namespace = $this->namespace;
		$field = $params["field"];

		if ($f3->POST["label"])
			$db->exec("UPDATE {$namespace} SET label=? WHERE id=?", [$f3->POST["label"], $field]);

		if ($f3->POST["type"])
			$db->exec("UPDATE {$namespace} SET type=? WHERE id=?", [$f3->POST["type"], $field]);

		if ($f3->POST["error_message"])
			$db->exec("UPDATE {$namespace} SET error_message=? WHERE id=?", [$f3->POST["error_message"], $field]);

		if ($f3->POST["placeholder"])
			$db->exec("UPDATE {$namespace} SET placeholder=? WHERE id=?", [$f3->POST["placeholder"], $field]);

		if ($f3->POST["order"])
			$db->exec("UPDATE {$namespace} SET `order`=? WHERE id=?", [$f3->POST["order"], $field]);

		$f3->reroute("/admin/{$this->namespace}");
	}

	function install()
	{
		$db = f3::instance()->get("DB");
		$namespace = $this->namespace;

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->namespace}'");
		
		if (empty($result))
		{
			$db->begin();
			$db->exec("CREATE TABLE `{$this->namespace}` ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'label' TEXT, 'type' INTEGER, 'order' INTEGER, 'error_message' TEXT, 'placeholder' TEXT);");
			$db->exec("INSERT INTO `{$namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('1','Name','name','1','Please add your name.',NULL);");
			$db->exec("INSERT INTO `{$namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('2','Phone','number','2','Please add your phone number.','Landline or Mobile');");
			$db->exec("INSERT INTO `{$namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('3','Email Address','email','3','Please add a valid email address.',NULL);");
			$db->exec("INSERT INTO `{$namespace}` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('4','Message','textarea','4','Please add a message.',NULL);");
			$db->commit();
		}

		setting_use_namespace($this->namespace);
		setting("email", "joe@example.com", false);
		setting("name", "Joe", false);
		setting("subject", "Example subject line", false);
		setting_clear_namespace();

		base::instance()->reroute("/admin/".$this->namespace);
	}


	public function add_field($f3) {

		$db = base::instance()->DB;
		$p = $f3->POST;

		$db->exec("INSERT INTO {$this->namespace} (label, type, `order`, error_message, placeholder) VALUES (?, ?, ?, ?, ?)",[$p["label"], $p["type"], $p["order"], $p["error_message"], $p["placeholder"]]);

		$f3->reroute("/admin/{$this->namespace}");
	}

	public function delete_field ($f3, $params) {
		base::instance()->DB->exec("DELETE FROM {$this->namespace} WHERE id=?", $params["field"]);
		$f3->reroute("/admin/{$this->namespace}");
	}

}