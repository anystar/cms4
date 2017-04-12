<?php

class contact
{
	private $namespace;
	private $routes 			= "/contact";
	private $email_template = "generic_email_template.html";
	private $return_on_error_to = "/contact";
	private $contact_success = "/contact_success";
	private $submit_route	 = "/contact";

	private $smtp_server	= "127.0.0.1";
	private $port 			= 25;

	function __construct ($namespace) {
		$f3 = base::instance();

		$this->namespace = $namespace;
		$this->routes = setting($namespace."_routes");

		if (isroute("/admin/".$namespace)) {

			if (!$this->check_install())
				$f3->reroute("/admin/".$this->namespace."/setup");
		}

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
		if (isroute($this->routes) || isroute("/admin/".$namespace))
		{
			// Ensure smtp server is available to send mail to
			if (!$this->check_smtp_server())
			{
				$f3->set($this->namespace.".html", "Form temporarily unavailable");
				return;
			}

			// Retreive contents of form
			$this->retreive_content();

			if ($f3->POST["actionid"] == "{$namespace}_submitted")
			{
				// Validate the form and submit email
				if ($error = $this->validate())
				{
					$this->send_email();

					// Should we reroute to a success page?
					if ($value = setting("{$namespace}_success"))
						$f3->reroute($value);
				}
				
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
			$namespace = $this->namespace;
			$img = new Image();
			$img->captcha("/contact/Abel-Regular.ttf", 16, 5,"SESSION.{$namespace}_captcha_code");
			$img->render();
		});
	}

	function admin_routes ($f3)
	{
		$f3->route("GET /admin/{$this->namespace}", function ($f3) {
			$f3->namespace = $this->namespace;

			$this->retreive_content();

			$f3->set("contact", $f3->get($this->namespace));

			// Get settings
			setting_use_namespace($this->namespace);
			$f3->set("contact.email", setting("email"));
			$f3->set("contact.name", setting("name"));
			$f3->set("contact.subject", setting("subject"));
			setting_clear_namespace();

			$f3->module_name = base::instance()->DB->exec("SELECT name FROM licenses WHERE namespace=?", [$this->namespace])[0]["name"];

			$f3->set("contact.enable_editting", setting($this->namespace."_enable_editting"));

			// Get archived messages
			$result = $f3->DB->exec("SELECT contents FROM `{$this->namespace}_archived` ORDER BY `date` DESC");
			if ($result)
				foreach ($result as $r)
					$archived[] = json_decode($r["contents"]);

			if ($archived)
				foreach ($archived[0] as $key=>$x)
					$archived_labels[] = $key;

			$f3->set("archived_labels", $archived_labels);
			$f3->set("archived", $archived);

			echo Template::instance()->render("/contact/contact.html");
		});

		$f3->route("GET /admin/{$this->namespace}/setup", function ($f3) {
			$f3->namespace = $this->namespace;	

			$this->check_smtp_server();

			setting_use_namespace($this->namespace);
			$f3->set("contact.smtp_server_check", $f3->get($this->namespace.".smtp_server_check"));
			$f3->set("contact.routes", $this->routes);
			$f3->set("contact.smtp_server", setting("smtp_server"));
			$f3->set("contact.port", setting("port"));
			$f3->set("contact.success", setting("success"));
			$f3->set("contact.enable_editting", setting("enable_editting"));
			$f3->set("contact.file_upload_folder", setting("file_upload_folder"));
			setting_clear_namespace();

			$f3->module_name = base::instance()->DB->exec("SELECT name FROM licenses WHERE namespace=?", [$this->namespace])[0]["name"];

			echo Template::instance()->render("/contact/setup.html");
		});

		$f3->route("POST /admin/{$this->namespace}/setup", function ($f3) {
			$f3->clear($this->namespace.".smtp_server_check");

			setting_use_namespace($this->namespace);
			setting("routes", $f3->POST["routes"]);
			setting("smtp_server", $f3->POST["smtp_server"]);
			setting("port", $f3->POST["port"]);
			setting("success", $f3->POST["contact_success"]);

			if ($f3->devoid("POST.enable_editting"))
				setting("enable_editting", "false");
			else
				setting("enable_editting", "true");
	
			if (checkdir($f3->POST["file_upload_folder"])) 
				setting("file_upload_folder", $f3->POST["file_upload_folder"]);

			setting_clear_namespace();

			$this->install();

			$f3->reroute("/admin/{$this->namespace}/setup");
		});


		$f3->route("GET /admin/{$this->namespace}/email_template", function ($f3) {
			$this->retreive_content();

			$f3->set("contact", $f3->get("{$this->namespace}"));

			echo Template::instance()->render("/contact/email_template/generic_email_template.html");
		});

		$f3->route("GET /admin/{$this->namespace}/documentation", function ($f3) {
			$f3->set("contact", $f3->get($this->namespace));
			$f3->module_name = base::instance()->DB->exec("SELECT name FROM licenses WHERE namespace=?", [$this->namespace])[0]["name"];
			
			echo Template::instance()->render("/contact/documentation.html");
		});
		
		$f3->route("POST /admin/{$this->namespace}/update_field/@field", function ($f3, $params) { 
			$this->update_field($f3, $params);
			$f3->reroute("/admin/{$this->namespace}");
		});

		$f3->route("POST /admin/{$this->namespace}/update_field/@field [ajax]", function ($f3, $params) {

			parse_str($_POST["data"], $_POST);

			$this->update_field($f3, $params);
			echo "success";
		});
		
		$f3->route("POST /admin/{$this->namespace}/add_field", 			 
			function ($f3, $params) { $this->add_field($f3); });
		
		$f3->route("GET /admin/{$this->namespace}/delete_field/@field",  
			function ($f3, $params) { $this->delete_field($f3, $params); });

		$f3->route("POST /admin/{$this->namespace}/settings", function ($f3) {
			$post = $f3->POST;

			setting_use_namespace($this->namespace);
			if (filter_var($post["email"], FILTER_VALIDATE_EMAIL))
				setting("email", $f3->POST["email"]);

			if (strlen($post["name"]) > 3)
				setting("name", $f3->POST["name"]);

			if (strlen($post["subject"]) > 3)
				setting("subject", $f3->POST["subject"]);

			setting_clear_namespace();

			$f3->reroute("/admin/{$this->namespace}");
		});

		$f3->route("POST /admin/{$this->namespace}/update_order [ajax]", function ($f3) {
			$orders = json_decode($f3->POST["fields_order"]);

			$db = $f3->DB;

			$db->begin();
			foreach ($orders as $order=>$id) {
				$db->exec("UPDATE `{$this->namespace}` SET `order`=? WHERE id=?", [$order, $id]);
			}
			$db->commit();
		});

	}

	function retreive_content()
	{	
		$f3 = base::instance();
		$db = $f3->get("DB");

		$result = $db->exec("SELECT * FROM `{$this->namespace}` ORDER BY `order` ASC");

		foreach ($result as $r) 
		{
			$r["name"] = substr(sha1($this->namespace.$r["id"].$r["label"]), 0, 12);
			$r["name"] = strtolower(str_replace(" ", "_", $r["label"]));

			$formcompiled[$r["name"]] = $r;

			if ($value = $f3->POST[$r["name"]])
			{
				$formcompiled[$r["name"]]["value"] = $value;
			}

			if ($r["type"]=="select") {
				$formcompiled[$r["name"]]["options"] = $f3->split($r["placeholder"]);
			}

			if ($r["type"]=="file") {
				if ($value = $f3->FILES[$r["name"]])
					$formcompiled[$r["name"]]["value"] = $value;
			}
		}

		$f3->set("{$this->namespace}.form", $formcompiled);
		$f3->set("{$this->namespace}.action", $f3->BASE.$f3->PATH);
		$f3->set("{$this->namespace}.actionid", $this->namespace."_submitted");
		$f3->set("{$this->namespace}.captcha", $f3->BASE."/".$this->namespace."/captcha");
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
		if ($post["captcha"] != $f3->SESSION["{$namespace}_captcha_code"]) {
			$sendemail = false;
			$f3->set("{$this->namespace}.captcha_error", true);
		}

		$form = $f3->get("{$namespace}.form");

		foreach ($form as $key=>$field) 
		{
			$value = $field["value"];

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

				case "select":

				break;

				case "file":
					// Upload method from
					// https://fatfreeframework.com/3.6/web#receive

					$folder = setting($this->namespace."_file_upload_folder");
					
					checkdir($folder);

					$name = $field["value"]["name"];

					while (file_exists($folder."/".$name))
						$name = uniqid()."_".$name;

					move_uploaded_file($field["value"]["tmp_name"], getcwd()."/".$folder."/".$name);

					$form[$key]["value"]["url"] = $f3->SCHEME . "://" . $f3->HOST . $f3->BASE . "/" . rtrim(ltrim($folder, "/"), "/")."/".$name;
					$form[$key]["value"]["name"] = $name;

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
		$f3 = base::instance();
		$db = $f3->get("DB");

		setting_use_namespace($this->namespace);
			
		if ($f3->POST["sendto"])
			$toAddress = $f3->POST["sendto"];
		else
			$toAddress = setting("email");

		$toName = setting("name");
		$subject = setting("subject");

 		if ($f3->exists("fromAddress"))
			$fromAddress = $f3->get("fromAddress");
		else
			$fromAddress = setting("from_address");

 		if ($f3->exists("fromAddress"))
	 		$fromName = $f3->get("fromName");
	 	else
	 		$fromName = "Website visitor";

		setting_clear_namespace();

		$smtp = new SMTP($this->smtp_server, $this->port, "", "", "");

		$smtp->set('To', '"'.$toName.'" <'.$toAddress.'>');
		$smtp->set('From', '"'.$fromName.'" <admin@webworksau.com>');
		$smtp->set('Reply-To', '"'.$fromName.'" <'.$fromAddress.'>');
		$smtp->set('Subject', $subject);
		$smtp->set('Content-Type', 'text/html');

		$f3->set($this->namespace.".subject", $subject);

		if (file_exists(getcwd()."/".$this->email_template))

			// Use custom email template from client directory
			$body = Template::instance()->render($this->email_template);
		else
		{
			// Temp hive to generate a html snippet
			$temphive = [
				"contact" => $f3->get($this->namespace),
			];

			// Use our generic email template
			$body = Template::instance()->render("/contact/email_template/generic_email_template.html", null, $temphive);
		}
	
		$contents = $f3->POST;
		unset($contents["actionid"]);
		unset($contents["sendto"]);
		unset($contents["captcha"]);

		$f3->DB->exec("INSERT INTO `{$this->namespace}_archived` (contents, `date`) VALUES (?, ?)", [json_encode($contents), time()]);
		
		$smtp->send($body);

		return true;
	}

	function update_field ($f3, $params) {

		$db = Base::instance()->DB;
		$namespace = $this->namespace;
		$field = $params["field"];

		if ($f3->POST["label"])
			$db->exec("UPDATE `{$namespace}` SET label=? WHERE id=?", [$f3->POST["label"], $field]);

		if ($f3->POST["type"])
			$db->exec("UPDATE `{$namespace}` SET type=? WHERE id=?", [$f3->POST["type"], $field]);

			$db->exec("UPDATE `{$namespace}` SET error_message=? WHERE id=?", [$f3->POST["error_message"], $field]);
			$db->exec("UPDATE `{$namespace}` SET placeholder=? WHERE id=?", [$f3->POST["placeholder"], $field]);

		if ($f3->POST["order"])
			$db->exec("UPDATE `{$namespace}` SET `order`=? WHERE id=?", [$f3->POST["order"], $field]);
	}

	function add_field($f3) {

		$db = base::instance()->DB;
		$p = $f3->POST;

		$db->exec("INSERT INTO `{$this->namespace}` (label, type, `order`, error_message, placeholder) VALUES (?, ?, ?, ?, ?)",[$p["label"], $p["type"], $p["order"], $p["error_message"], $p["placeholder"]]);

		$f3->reroute("/admin/{$this->namespace}");
	}

	function delete_field ($f3, $params) {
		base::instance()->DB->exec("DELETE FROM `{$this->namespace}` WHERE id=?", $params["field"]);
		$f3->reroute("/admin/{$this->namespace}");
	}

	function check_smtp_server () {

		// Use cached result
		if (base::instance()->get($this->namespace.".smtp_server_check"))
			return true;

		$f = @fsockopen($this->smtp_server, $this->port);
		
		if ($f !== false) 
		{
		    $res = fread($f, 1024);
		    if (strlen($res) > 0 && strpos($res, '220') === 0)
		    {
		    	base::instance()->set($this->namespace.".smtp_server_check", true, 1800); // Cache for 30 minutes
				return true;
		    }
	
			fclose($f);
		}

		return false;
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
	}

	function check_install() {
		$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->namespace}'");

		if (empty($result))
			return false;

		$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->namespace}_archived'");

		if (empty($result))
			base::instance()->DB->exec("CREATE TABLE '{$this->namespace}_archived' ('id' INTEGER PRIMARY KEY NOT NULL, 'contents' TEXT, 'date' DATETIME);");

		if (!$this->check_smtp_server())
			return false;

		if (!setting("{$this->namespace}_smtp_server"))
			return false;

		if (!setting("{$this->namespace}_port"))
			return false;
			
		return true;
	}
}