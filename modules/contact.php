<?php

class contact extends prefab
{

	static $email_template = "website-enquiry.html";

	function __construct() {
		$f3 = base::instance();

		$default = $f3->exists("CONFIG[contact.page]") ? $f3->get("CONFIG[contact.page]") : "/contact";
		$f3->set("CONFIG[contact.page]", $default);

		if ($f3->exists("CONFIG[contact.email_template]"))
			contact::$email_template = $f3->get("CONFIG[contact.email_template]");

		if (contact::hasInit())
		{
			$this->routes($f3);

			$pageToLoadOn = $f3->get("CONFIG[contact.page]");

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
				$f3->mock("GET /contact_success");
			}
			else
				$f3->mock("GET ".$mock);
		});
	}

	function admin_routes ($f3)
	{
		$f3->route('GET /admin/contact', "contact::admin");
		$f3->route('GET /admin/contact/generate', "contact::generate");
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
		
		$toAddress = $db->exec("SELECT `value` FROM settings WHERE setting='contact-email'")[0]["value"];
		$toName = $db->exec("SELECT `value` FROM settings WHERE setting='contact-name'")[0]["value"];
		$subject = $db->exec("SELECT `value` FROM settings WHERE setting='contact-subject'")[0]["value"];

 		$fromName = $f3->get("fromName");
		$fromAddress = $f3->get("fromAddress");

		$smtp = new SMTP("127.0.0.1", "25", "", "", "");

		$smtp->set('To', '"'.$toName.'" <'.$toAddress.'>');
		$smtp->set('From', '"'.$fromName.'" <'.$fromAddress.'>');
		$smtp->set('Subject', $subject);
		$smtp->set('Content-Type', 'text/html');

		$f3->set("contact_subject", $db->exec("SELECT `value` FROM settings WHERE setting='contact-subject'")[0]["value"]);

		$body = Template::instance()->render(contact::$email_template);

		$smtp->send($body);

		return true;
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

		if (!file_exists(contact::$email_template)) {
			d("Fatel Error in contact module: Email template does not exsist. Please create a html file named ".contact::$email_template);
		}

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

		f3::instance()->mock('GET /admin/contact');
	}

	static public function admin ($f3) {
		if (contact::hasInit())
			echo Template::instance()->render("contact_form/contact.html");
		else
			echo Template::instance()->render("contact_form/nocontact.html");
	}

}