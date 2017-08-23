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
		$defaults["name"] = "enquiryform";
		$defaults["label"] = "contact";
		$defaults["sendto"] = "joe@example.com";
		$defaults["sendname"] = "Website Enquiry";
		$defaults["template"] = "email_template.html";
		$defaults["success"] = "?success=true";

		check(0, (count($settings) < 3), "**Default example:**",$defaults);

		check(0, $settings["label"], "No `label` set in **".$settings["name"]."** settings");
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
		if ($f3->exists("POST.contactform_submit")) {
			$id = $f3->get("POST.contactform_submit");

			// We've used it, lets unset it.
			unset($f3->POST["contactform_submit"]);

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

			if ($settings["testing"]) $captcha_passed = true;

			if (array_key_exists("captcha", $f3->POST))
			{
				if ($f3->POST["captcha"] == $f3->SESSION["captcha_code"])
					$captcha_passed = true;
			}
			
			if ($captcha_passed) {
				if (isset($f3->POST["name"]))
					$fromName = $f3->POST["name"];

				if (isset($f3->POST["email"]))
					$fromAddress = $f3->POST["email"];

				// email to send to, data, template, options
				$this->send_email($settings["sendto"], $f3->POST, $settings["template"], [
					"fromName" => $fromName,
					"fromAddress" => $fromAddress,
					"sendName"=>isset($settings["sendname"]) ? $settings["sendname"] : "Business owner",
					"subject" => isset($settings["subject"]) ? $settings["subject"] : "Website Enquiry"
				]);

				$f3->reroute($settings["success"]);
			}
			else {
				$f3->VERB = "GET";
				$f3->POST["captcha"] = "";
			}
		}
	}

	function send_email ($sendto, $form, $template, $options)
	{
		//$fromAddress = $fromName;

 		// if ($f3->exists("fromAddress"))
	 	// 	$fromName = $f3->get("fromName");
	 	// else
	 	// 	$fromName = "Website visitor";

		$config = base::instance()->CONFIG["mailer"];
		$smtp = new SMTP(
						$config["smtp.host"],
						$config["smtp.port"],
						$config["smtp.scheme"],
						$config["smtp.user"],
						$config["smtp.pw"]
					);

		$smtp->set('To', '"'.$options["sendName"].'" <'.$sendto.'>');
		$smtp->set('From', '"'.$options["fromName"].'" <'.$config["smtp.from_mail"].'>');
		$smtp->set('Reply-To', '"'.$options["fromName"].'" <'.$options["fromAddress"].'>');
		$smtp->set('Subject', $options["subject"]);

		if (file_exists(getcwd()."/".$template))
		{
			$mime = mime_content_type2(getcwd()."/".$template);

			// Use custom email template from client directory
			$body = \Template::instance()->render($template, null, $form);

		} else {

			// Use generic email template
			$body = \Template::instance()->render("/contactform/generic_email_template.html", null, $form);
		}

		if ($this->settings["testing"]) {			
			echo $body;
			die;
			return;
		}

		$smtp->set('Content-Type', "text/html");
		$smtp->send($body);

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

		// if ($attr == null)
		// 	$f3->error(1,'&lt;contactform&gt; has no attributes.'.$documentation);

		// // Register contact form module
		// if (array_key_exists("id", $attr))
		// 	$id = $attr["id"];
		// else
		// 	$id = 0;

		// if (array_key_exists("src", $attr))
		// 	$settings["postPath"] = $attr["src"];
		// else
		// 	$f3->error(1, "No post path provided! Please add src='/contact.html' to &lt;contactform&gt; tag.".$documentation);

		// if (array_key_exists("template", $attr))
		// { $settings["template"] = $attr["template"]; unset($attr["template"]); }
		// else
		// 	$f3->error(1, "No email template provided! Please add template='email_template.html' to &lt;contactform&gt; tag".$documentation);

		// if (array_key_exists("success", $attr))
		// 	{ $settings["successPage"] = $attr["success"]; unset($attr["success"]); }
		// else
		// 	$f3->error(1, "No success redirect page provided. Please add succes='/success_page.html' to &lt;contactform&gt; tag".$documentation);

		// if (array_key_exists("sendto", $attr))
		// 	{ $settings["sendto"] = $attr["sendto"]; unset($attr["sendto"]); }
		// else
		// 	$f3->error(1, "No email address provided! Please add sendto='joe@example.com' to &lt;contactform&gt; tag".$documentation);

		// if (array_key_exists("subject", $attr))
		// 	{ $settings["subject"] = $attr["subject"]; unset($attr["subject"]); }

		// if (array_key_exists("sendname", $attr))
		// 	{ $settings["sendname"] = $attr["sendname"]; unset($attr["sendname"]); }

		// Always post to the same page the form is located on.
		$attr["src"] = $f3->SCHEME."://".$f3->HOST.$f3->URI;

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