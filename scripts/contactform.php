<?php
/**
 *  FooForms - a collection of Form related HTML-Tag handlers
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *		@version: 0.2.0
 *		@date: 14.07.2015
 *
 **/

class contactform extends \Prefab {

	private $name;
	private $settings;

	function __construct($settings) {

		$f3 = base::instance();

		$defaults["class"] = "contactform";
		$defaults["routes"] = "contact-us.html";
		$defaults["sendto"] = "joe@example.com";
		$defaults["sendname"] = "Website Enquiry";
		$defaults["template"] = "email_template.html";
		$defaults["success"] = "?success=true";
		$defaults["recaptcha_privatekey"] = "6LfF9yUUAAAAAFFt9sajMnKFGlmYbVKPsDx9n7wm";


		check(0, (count($settings) < 3), "**Default example:**",$defaults);

		check(0, $settings["sendto"], "No `sendto` set in **".$settings["name"]."** settings");
		check(0, $settings["sendname"], "No `sendname` set in **".$settings["name"]."** settings");
		check(0, $settings["template"], "No `template` set in **".$settings["name"]."** settings");
		check(0, $settings["success"], "No `success` set in **".$settings["name"]."** settings");

		$this->name = $settings["name"];
		$this->settings = $settings;

		// Load admin routes if signed in
		if (admin::$signed)
		{
			if (!isroute("/admin/*")) {
				$f3->contactform_toolbar = $settings;

				$f3->clear("contactform_toolbar");
			} else {
				$this->admin_routes($f3);

			}
		}

		if (array_key_exists("captcha", $f3->GET)) {
			$img = new Image();
			$img->captcha('/contactform/captcha4.ttf', 20, 4, 'SESSION.captcha_code');
			$img->render();
		}

		// Only process html routes
		if (mime_content_type2($f3->FILE) != "text/html")
			return;

		$tmpl = \Template::instance();
		$tmpl->extend($this->name, 'contactformHandler::render');
		$tmpl->extend('input','\Template\Tags\Input::render');
		$tmpl->extend('textarea','\Template\Tags\Textarea::render');
		$tmpl->extend('select','\Template\Tags\Select::render');
		$tmpl->extend('option','\Template\Tags\Option::render');
		$tmpl->extend('captcha', 'captcha::render');

		// We have submitted the form
		if ($f3->exists("POST.contactform_submit"))
			$this->check_form($settings);
	}


	function check_form ($settings) {
		$f3 = base::instance();

		// Get the form ID
		$id = $f3->get("POST.contactform_submit");

		// We've used it, lets unset it.
		unset($f3->POST["contactform_submit"]);

		// Check Recaptcha
		$captcha_passed = false;
		if (array_key_exists("g-recaptcha-response", $f3->POST))
		{
			$recaptcha_response = $f3->POST["g-recaptcha-response"];
	
			$options["method"] = "POST";
			$options["content"] = http_build_query(["secret"=>$settings["recaptcha_privatekey"], "response"=>$f3->POST["g-recaptcha-response"], $f3->IP]);

			$response = Web::instance()->request("https://www.google.com/recaptcha/api/siteverify", $options);
			$response = json_decode($response["body"], 1);

			if ($response["success"] == TRUE)
				$captcha_passed = true;

			unset($f3->POST["g-recaptcha-response"]);
		}


		// Check basic text captcha
		if ($settings["testing"]) $captcha_passed = true;
		if (array_key_exists("captcha", $f3->POST))
		{
			if ($f3->POST["captcha"] == $f3->SESSION["captcha_code"])
				$captcha_passed = true;
		}

		// Captcha Failed!
		if (!$captcha_passed) {

			// Because we are posting to the same page, change VERB
			// so F3 route will be call correctly.
			$f3->VERB = "GET";
			$f3->POST["captcha"] = "";

			//return;
		}

		// Get name
		if (array_key_exists("name", $f3->POST))
			$fromName = $f3->POST["name"];
		else
			$fromName = "Website Visitor";

		// Get email
		if (array_key_exists("email", $f3->POST))
			$fromAddress = $f3->POST["email"];
		else
			$fromAddress = "noreply@webworksau.com";

		// Send the email !!
		$this->send_email($settings["sendto"], $f3->POST, $settings["template"], [
			"fromName" => $fromName,
			"fromAddress" => $fromAddress,
			"sendName"=>isset($settings["sendname"]) ? $settings["sendname"] : "Business owner",
			"subject" => isset($settings["subject"]) ? $settings["subject"] : "Website Enquiry"
		]);

		redirect($settings["success"]);
	}

	function send_email ($sendto, $form, $template, $options)
	{	
		// Use custom email template from client directory
		$body = \Template::instance()->render($template, null, $form);

		$mailer = new \Mailer();

		// Attach any files
		if (base::instance()->FILES != null)
			foreach (base::instance()->FILES as $file)
			{
				if ($file["tmp_name"] != "")
					if (file_exists($file["tmp_name"]))
						$mailer->attachFile($file["tmp_name"], $file["name"]);
			}

		$mailer->addTo($sendto, $options["sendName"]);
		$mailer->setReply($options["fromAddress"], $options["fromName"]);
		$mailer->setHTML($body);

		if ($this->settings["testing"]) {			
			echo $body;
			die;
		}

		$mailer->queue($options["subject"]);

		return true;
	}

	function admin_routes () {

		base::instance()->route("GET /admin/".$this->name, function ($f3) {

			echo \Template::instance()->render("/contactform/contact.html");
		});

	}

	function toolbar () {
		return "<a href='".base::instance()->BASE."/admin/".$this->name."' class='button'>Edit contact settings</a>";
	}

}


class contactformHandler extends \Template\TagHandler {

	function build ($attr, $content)
	{
		$f3 = base::instance();

		$documentation = '<h5 style="padding-top:20px">Example:</h5>
					<p>'.$f3->highlight('<contactform sendto="joe@example.com" sendname="Joe Smith" template="email_template" src="/contact.html" success="success_page.html">').'</p>
					<ul style="font-size:15px">
						<li>sendto:<p>Email address to submit the form to.</p></li>
						<li>template:<p>html file which is used to send. Located in client directory.</p></li>
						<li>src:<p>Address to submit too. Use src="*" to post to any page. This provides the ability for contact form to be placed on many pages.</p></li>
						<li>success: <p>Page to redirect to on success.</p></li>
						<li>subject: <p>Subject line of email. Default: Website Enquiry</p></li>
						<li>sendname: <p>Website owners name. Default: Business owner</p></li>
					</ul>
				';


		// Always post to the same page the form is located on.
		$attr["src"] = '<?= $SCHEME."://".$HOST.$URI ?>';

		$content = $this->tmpl->build($content);

		$attr["method"] = "POST";

		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$hiddenInput = '<input type="hidden" name="contactform_submit" value="'.$id.'">';

		cache::instance()->set("contactforms[".$id."]", $settings, 0);

		return '<form ' . $attr . '>' . $content . $hiddenInput . '</form>';
	}

}


class captcha extends \Template\TagHandler {
	function build ($attr, $content)
	{
		$attr["src"] = base::instance()->BASE.base::instance()->PATH."?captcha";

		if ($attr["recaptcha"])
		{
			$string .= "<script src='https://www.google.com/recaptcha/api.js'></script>".PHP_EOL;
			$string .= '<div class="g-recaptcha" data-sitekey="'.$attr["recaptcha"].'"></div>'.PHP_EOL;

			return $string;
		}

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		return '<img ' . $attr . '>';
	}
}