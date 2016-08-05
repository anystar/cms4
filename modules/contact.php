<?php

class contact extends prefab
{

	static $email_template = "website-enquiry.html";
	static $port = 25;


	function __construct() {
		$f3 = base::instance();

		$default = $f3->exists("CONFIG[contact.page]") ? $f3->get("CONFIG[contact.page]") : "/contact";
		$f3->set("CONFIG[contact.page]", $default);

		if ($f3->exists("CONFIG[contact.email_template]")) contact::$email_template = $f3->get("CONFIG[contact.email_template]");
		if ($f3->exists("CONFIG[contact.port]")) contact::$port = $f3->get("CONFIG[contact.port]");

		if (contact::hasInit())
		{
			$this->routes($f3);

			$pageToLoadOn = $f3->get("CONFIG[contact.page]");

			if ($f3->POST["return"] == null) {
				$f3->set("contact_return_page", $f3->PATH);
			} else {
				$f3->set("contact_return_page", $f3->POST["return"]);
			}


			if ($pageToLoadOn == $f3->PATH || $pageToLoadOn == "all") {			
				$this->load();
			}
		}

		if (admin::$signed)
			$this->admin_routes($f3);
	}

	function routes($f3) {

		$f3->route("POST /contact", function ($f3, $params) {

			if ($f3->get("CONFIG[contact.page]") == "all")
				$mock = $f3->get("POST.return");
			else
				$mock = $f3->get("CONFIG[contact.page]");

			$result = contact::validate();

			if ($result)
			{
				if (file_exists("contact_success.html")) {
					$f3->reroute("/contact_success");
				}
				else
				{
					$tmp = $f3->UI;
					$f3->UI = $f3->CONFIG["paths"]["cms"]."/adminUI/";
					echo Template::instance()->render("contact_form/contact_success.html");
				}
			}
			else
				$f3->mock("GET ". $mock);
		});
	}

	function admin_routes ($f3)
	{
		$f3->route('GET /admin/contact', "contact::admin");
		$f3->route('POST /admin/contact/generate', "contact::generate");
		$f3->route('GET /admin/contact/email_template', function ($f3) {
			contact::load($f3);
			$tmp = $f3->UI;
			$f3->UI = $f3->CONFIG["paths"]["cms"]."/adminUI/";
			echo Template::instance()->render("contact_form/email_template/generic_email_template.html");
		});

		$f3->route('POST /admin/contact/settings', "contact::update_settings");		
		$f3->route('POST /admin/contact/update_field/@field', "contact::update_field");
		$f3->route('POST /admin/contact/add_field', "contact::add_field");
		$f3->route('GET /admin/contact/delete_field/@field', "contact::delete_field");
	}

	static public function contact ($f3) {
		if (contact::exists())
			echo Template::instance()->render("contactForm/contact.html");
		else
			echo Template::instance()->render("contactForm/nocontact.html");
	}

	static function load()
	{
		$f3 = f3::instance();
		$db = $f3->get("DB");

		$result = $db->exec("SELECT * FROM contact_form ORDER BY `order`");

		foreach ($result as $r) 
			$formcompiled[$r["id"]] = $r;

		$f3->set("form", $formcompiled);

		$result = $db->exec("SELECT value FROM settings WHERE setting=?", "contact-custom_html")[0]["value"];

		// Use template snippet
		if ($result == 0) {
			
			$tmp = $f3->UI; $f3->UI = $f3->CMS;
			$snippet = \Template::instance()->render("template_snippets/contactform.html");
			$f3->UI = $tmp;

			$f3->set("contact_form", $snippet);
		}
	}

	static function validate ()
	{
		contact::load();

		$f3 = f3::instance();
		$post = $f3->get("POST");

		$sendemail = true;

		foreach ($post as $key => $value)
		{
			$matches = array();
			preg_match('#(\d+)$#', $key, $matches);
			$id = $matches[0];

			if ($id == null) break;

			$field = &$f3->ref("form.".$id);

			$field["value"] = $value;

			switch ($field["type"])
			{
				case "name":
					if (strlen($value) == 0)
						$field["error"] = true;

					$f3->set("fromName", $value);
				break;

				case "text":
					if (strlen($value) == 0)
						$field["error"] = true;
				break;

				case "textarea":
					if (strlen($value) < 5)
						$field["error"] = true;
				break;

				case "number":
					if (strlen($value) < 5)
						$field["error"] = true;
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
			contact::submit();
			return true;
		}
		else
			return false;
	}

	static function submit()
	{
		$f3 = f3::instance();
		$db = $f3->get("DB");
			
		if ($f3->POST["sendto"])
			$toAddress = $f3->POST["sendto"];
		else
			$toAddress = $db->exec("SELECT `value` FROM settings WHERE setting='contact-email'")[0]["value"];

		$toName = $db->exec("SELECT `value` FROM settings WHERE setting='contact-name'")[0]["value"];
		$subject = $db->exec("SELECT `value` FROM settings WHERE setting='contact-subject'")[0]["value"];

 		$fromName = $f3->get("fromName");

 		if ($f3->exists("fromAddress"))
			$fromAddress = $f3->get("fromAddress");
		else
			$fromAddress = $db->exec("SELECT `value` FROM settings WHERE setting='contact-from_address'")[0]["value"];

		// Worst case just set it to admin@webworksau.com...
		if (!$fromAddress) $fromAddress = "admin@webworksau.com";

		$smtp = new SMTP("127.0.0.1", contact::$port, "", "", "");

		$smtp->set('To', '"'.$toName.'" <'.$toAddress.'>');
		$smtp->set('From', '"'.$fromName.'" <'.$fromAddress.'>');
		$smtp->set('Subject', $subject);
		$smtp->set('Content-Type', 'text/html');

		$f3->set("contact_subject", $db->exec("SELECT `value` FROM settings WHERE setting='contact-subject'")[0]["value"]);

		if (file_exists(contact::$email_template)) {
			$body = Template::instance()->render(contact::$email_template);
		} else {
			$tmp = $f3->UI;
			$f3->UI = $f3->CONFIG["paths"]["cms"]."/adminUI/";
			$body = Template::instance()->render("contact_form/email_template/generic_email_template.html");
			$f3->UI = $tmp;
		}

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

		$f3->reroute("/admin/contact");
	}

	static function hasInit()
	{	
		$db = f3::instance()->get("DB");
		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='contact_form'");
		
		if (empty($result)) 
			return false;

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
		if (empty($result)) 
			return false;

		// if (!file_exists(contact::$email_template)) {
		// 	d("Fatel Error in contact module: Email template does not exsist. Please create a html file named".contact::$email_template);
		// }

		return true;
	}

	static function generate()
	{
		$db = f3::instance()->get("DB");

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='contact_form'");
		
		if (empty($result))
		{
			$db->begin();
			$db->exec("CREATE TABLE `contact_form` ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'label' TEXT, 'type' INTEGER, 'order' INTEGER, 'error_message' TEXT, 'placeholder' TEXT);");
			$db->exec("INSERT INTO `contact_form` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('1','Name','name','1','Please add your name.',NULL);");
			$db->exec("INSERT INTO `contact_form` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('2','Phone','number','2','Please add your phone number.','Landline or Mobile');");
			$db->exec("INSERT INTO `contact_form` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('3','Email Address','email','3','Please add a valid email address.',NULL);");
			$db->exec("INSERT INTO `contact_form` (`id`,`label`,`type`,`order`,`error_message`,`placeholder`) VALUES ('4','Message','textarea','4','Please add a message.',NULL);");
			$db->commit();
		}

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");

		if (empty($result))
		{
			$db->begin();
			$db->exec("CREATE TABLE 'settings' ('setting' TEXT, 'value' TEXT);");
			$db->exec("INSERT INTO settings VALUES ('contact-email', 'joe@example.com');");
			$db->exec("INSERT INTO settings VALUES ('contact-name', 'Joe');");
			$db->exec("INSERT INTO settings VALUES ('contact-subject', 'Email subject line');");
			$db->commit();
		}
		else 
		{
			$result = $db->exec("SELECT setting FROM settings WHERE setting='contact-email'");
			if (empty($result))
				$db->exec("INSERT INTO settings VALUES ('contact-email', 'joe@example.com')");

			$result = $db->exec("SELECT setting FROM settings WHERE setting='contact-name'");
			if (empty($result))
				$db->exec("INSERT INTO settings VALUES ('contact-name', 'Joe')");

			$result = $db->exec("SELECT setting FROM settings WHERE setting='contact-subject'");
			if (empty($result))
				$db->exec("INSERT INTO settings VALUES ('contact-subject', 'Email subject line')");
		}

		base::instance()->reroute('/admin/contact');
	}

	static public function admin ($f3) {
		if (contact::hasInit())
		{
			contact::load();

			// Get configurables
			$f3->set("contact_email", config("contact-email"));
			$f3->set("contact_name", config("contact-name"));
			$f3->set("contact_subject", config("contact-subject"));

			$f3->contact_fields = base::instance()->DB->exec("SELECT * FROM contact_form ORDER BY `order`");

			echo Template::instance()->render("contact_form/contact.html");
		}
		else
			echo Template::instance()->render("contact_form/nocontact.html");
	}

	static public function update_settings($f3) {
		$db = base::instance()->DB;

		if ($f3->POST["email"])
			$db->exec("UPDATE settings SET value=? WHERE setting='contact-email'", $f3->POST["email"]);
		if ($f3->POST["name"])
			$db->exec("UPDATE settings SET value=? WHERE setting='contact-name'", $f3->POST["name"]);
		if ($f3->POST["subject"])
			$db->exec("UPDATE settings SET value=? WHERE setting='contact-subject'", $f3->POST["subject"]);

		$f3->reroute("/admin/contact");
	}

	static public function add_field($f3) {

		$db = base::instance()->DB;
		$p = $f3->POST;

		$db->exec("INSERT INTO contact_form (label, type, `order`, error_message, placeholder) VALUES (?, ?, ?, ?, ?)",[$p["label"], $p["type"], $p["order"], $p["error_message"], $p["placeholder"]]);

		$f3->reroute("/admin/contact");
	}

	static public function delete_field ($f3, $params) {
		base::instance()->DB->exec("DELETE FROM contact_form WHERE id=?", $params["field"]);
		$f3->reroute("/admin/contact");
	}

}