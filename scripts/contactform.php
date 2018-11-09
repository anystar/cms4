<?php

class contactform {

	private $name;
	private $settings;

	private $recaptcha_public_key;
	private $recaptcha_private_key;

	private $invisible_recaptcha_public_key;
	private $invisible_recaptcha_private_key;

	function __construct($settings) {

		$f3 = base::instance();

		$this->recaptcha_public_key = $f3->CONFIG["recaptcha"]["public_key"];
		$this->recaptcha_private_key = $f3->CONFIG["recaptcha"]["private_key"];

		$this->invisible_recaptcha_public_key = $f3->CONFIG["recaptcha"]["invisible_public_key"];
		$this->invisible_recaptcha_private_key = $f3->CONFIG["recaptcha"]["invisible_private_key"]; 

		$defaults["class"] = "contactform";
		$defaults["name"] = "contactform";		
		$defaults["routes"] = "*";
		$defaults["sendto"] = "Who to send email too?";
		$defaults["subject"] = "Website Enquiry";
		$defaults["template"] = "email_template.html";
		$defaults["success"] = "?success=true";

		check(100, (count($settings) < 3), 
			"**Default example:**", $defaults, 
			'**Captcha Tag**', '`<captcha centered recaptcha="'.$this->recaptcha_public_key.'">`', 
			'**Fancy Email Template**', '`https://gist.githubusercontent.com/sevn/fbde16459a3c40fa05d2b96368915d10/raw/f5d25f50ed6bafe8f82720a090e8bc6799849b62/email_template.html`',
			'**Simple Email Template**', '`https://gist.githubusercontent.com/sevn/bc53002c6e3c8b33bf79fd6d868ce2a8/raw/a83d24410b4024c9a804b79f700c52981af57ed0/email_template.html`'
		);

		check(0, $settings["sendto"], "No `sendto` set in **".$settings["name"]."** settings");
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
		$tmpl->extend('recaptcha', 'recaptcha::render');

		// We have submitted the form
		if ($f3->exists("POST.contactform_submit"))
			// Ensure we submitting for correct form
			if ($f3->POST["contactform_submit"] == $settings["name"] || $f3->POST["contactform_submit"] == "")
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
			
			if (array_check_value($f3->POST, "invisible_recaptcha", true))
				$key = $this->invisible_recaptcha_private_key;
			else
				$key = $this->recaptcha_private_key;

			$options["method"] = "POST";
			$options["content"] = http_build_query(["secret"=> $key, "response"=>$f3->POST["g-recaptcha-response"], $f3->IP]);
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

			// Permit failed captchas if in developer mode
			if (!base::instance()->CONFIG["developer"])
			{
				// Because we are posting to the same page, change VERB
				// so F3 route will be call correctly.
				$f3->VERB = "GET";
				$f3->POST["captcha"] = "";

				return;
			}
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

		// Convert single emails to arrays
		if (!is_array($settings["sendto"]))
			$settings["sendto"] = array($settings["sendto"]);

		// Send the email
		foreach ($settings["sendto"] as $email) {
			$this->send_email($email, $f3->POST, $settings["template"], [
				"fromName" => $fromName,
				"fromAddress" => $fromAddress,
				"sendName"=>isset($settings["sendname"]) ? $settings["sendname"] : "Business owner",
				"subject" => isset($settings["subject"]) ? $settings["subject"] : "Website Enquiry"
			]);
		}


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

		$mailer->antispam = $form;

		$mailer->queue($options["subject"]);

		return true;
	}

	function renderDashboard ($f3) {

			$f3->settings = $this->settings;

			echo \Template::instance()->render("/contactform/dashboard.html");
	}

	function admin_routes () {

		base::instance()->route("GET /admin/".$this->name, function ($f3) {
			$this->renderDashboard($f3);
		});

		base::instance()->route("POST /admin/".$this->name."/save-settings", function ($f3) {

			if (filter_var($f3->POST["sendto"], FILTER_VALIDATE_EMAIL)) 
			{
				$this->settings["sendto"] = setting("scripts.".$this->name.".sendto", $f3->POST["sendto"]);
			}
			else
			{
				
			}

			$this->settings["sendname"] = setting("scripts.".$this->name.".sendname", $f3->POST["sendname"]);
			$this->settings["subject"] = setting("scripts.".$this->name.".subject", $f3->POST["subject"]);

			$this->renderDashboard($f3);
		});

		base::instance()->route("GET /admin/contactform/screenshots/gmail.jpg", function ($f3) {

			$file = $GLOBALS["ROOTDIR"]."/cms/scriptsUI/contactform/screenshots/gmail-example-contact-form.jpg";
			header('Content-Type: image/jpg');
			header("Content-length: ".filesize($file));
			header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 30))); // 1 hour
			header("Cache-Control: public"); //HTTP 1.1
			readfile($file);
			$f3->abort();
		});

		base::instance()->route("GET /admin/contactform/screenshots/thunderbird.jpg", function ($f3) {

			$file = $GLOBALS["ROOTDIR"]."/cms/scriptsUI/contactform/screenshots/thunderbird-example-contact-form.jpg";
			header('Content-Type: image/jpg');
			header("Content-length: ".filesize($file));
			header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 30))); // 1 hour
			header("Cache-Control: public"); //HTTP 1.1
			readfile($file);
			$f3->abort();
		});
	}

	static function dashboard ($settings) {

		if (isroute($settings["routes"]))
		{
			$settings["name"] = isset($settings["name"]) ? $settings["name"] : "contactform";
			$settings["label"] = isset($settings["label"]) ? $settings["label"] : "Contact Form";

			return '<a href="'.base::instance()->BASE.'/admin/'.$settings["name"].'/" class="webworkscms_button btn-fullwidth">Edit '.$settings["label"].'</a>';
		}
	}
}


class contactformHandler extends \Template\TagHandler {

	function build ($attr, $content)
	{
		$f3 = base::instance();

		// Always post to the same page the form is located on.
		$attr["src"] = '<?= $SCHEME."://".$HOST.$URI ?>';
	
		$content = $this->tmpl->build($content);

		$attr["method"] = "POST";

		if (array_key_exists("script", $attr))
			$script = $attr["script"];
		else
			$script = "contactform";

		$hiddenInput = '<input type="hidden" name="contactform_submit" value="'.$script.'">';

		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		return '<form ' . $attr . '>' . $content . $hiddenInput . '</form>';
	}
}

class recaptcha extends \Template\TagHandler {
	function build ($attr, $innerhtml) {

		if (array_check_value($attr, "type", "invisible"))
			return $this->invisible($attr, $innerhtml);
		else 
			return $this->visible($attr, $innerhtml);
	}

	function visible ($attr, $innerhtml) {
		
		// Prevent including the same script in twice for multiple captchas on same page.
		if (!base::instance()->RECAPTCHA_LOADED)
		{
			$string .= "<script src='https://www.google.com/recaptcha/api.js?onload=renderRecaptchas&render=explicit' async defer></script>".PHP_EOL;
			$string .= "<script>window.renderRecaptchas = function() {var recaptchas = document.querySelectorAll('.g-recaptcha');for (var i = 0; i < recaptchas.length; i++) {grecaptcha.render(recaptchas[i], {sitekey: recaptchas[i].getAttribute('data-sitekey')});}}</script>".PHP_EOL;
			$string .= '<style> @media screen and (max-height: 575px){ #rc-imageselect, .g-recaptcha {transform:scale(0.77);-webkit-transform:scale(0.77);transform-origin:0 0;-webkit-transform-origin:0 0;} } </style>'.PHP_EOL;
			$string .= "<style> .text-xs-right { text-align: right; } .text-xs-center { text-align: center; } .text-xs-center > .g-recaptcha { display: inline-block; } .text-xs-right > .g-recaptcha { display: inline-block; }</style>".PHP_EOL;
		}

		// Change alignement
		if (array_check_value($attr, "align", "center"))
		{
			$string .= "<style> .text-xs-center { text-align: center; } .g-recaptcha { display: inline-block; }</style>".PHP_EOL;
			$string .= '<div class="text-xs-center">';
		}
		else if (array_check_value($attr, "align", "right"))
		{
			$string .= "<style> .text-xs-right { text-align: right; } .g-recaptcha { display: inline-block; }</style>".PHP_EOL;
			$string .= '<div class="text-xs-right">';
		}

		// Include site key
		$string .= '<div class="g-recaptcha" data-sitekey="'.base::instance()->CONFIG["recaptcha"]["public_key"].'"></div>'.PHP_EOL;

		// Finish up alignement div
		if (array_check_value($attr, "align", "center") OR array_check_value($attr, "align", "right"))
			$string .= '</div>'.PHP_EOL;

		// Tag as loaded to prevent scripts being included
		base::instance()->RECAPTCHA_LOADED = true;

		return $string;
	}

	function invisible ($attr, $innerhtml) {

		unset($attr["type"]);

		// Prevent including the same script in twice for multiple captchas on same page.
		if (!base::instance()->RECAPTCHA_LOADED)
		{
			$string .= "<script src='https://www.google.com/recaptcha/api.js?onload=renderRecaptchas&render=explicit' async defer></script>".PHP_EOL;
			$string .= "<script>window.renderRecaptchas = function() {var recaptchas = document.querySelectorAll('.g-recaptcha');for (var i = 0; i < recaptchas.length; i++) {grecaptcha.render(recaptchas[i], {sitekey: recaptchas[i].getAttribute('data-sitekey')});}}</script>".PHP_EOL;
			$string .= '<style> @media screen and (max-height: 575px){ #rc-imageselect, .g-recaptcha {transform:scale(0.77);-webkit-transform:scale(0.77);transform-origin:0 0;-webkit-transform-origin:0 0;} } </style>'.PHP_EOL;
			$string .= "<style> .text-xs-right { text-align: right; } .text-xs-center { text-align: center; } .text-xs-center > .g-recaptcha { display: inline-block; } .text-xs-right > .g-recaptcha { display: inline-block; }</style>".PHP_EOL;
		}

		$hive['random'] = uniqid();
		$hive['form'] = $attr["form"]; unset($attr["form"]);
		$hive['key'] = base::instance()->CONFIG["recaptcha"]["invisible_public_key"];
		$hive['innerhtml'] = $innerhtml;

		foreach ($attr as $key=>$value) {
			if ($key=="class") {
				$hive["extra_attribs"] .= 'class="'.$value.' g-recaptcha" ';
			} else {
				$hive["extra_attribs"] .= $key.'="'.$value.'" ';
			}
		}
		
		$string .= '<script> function recaptchasubmit{{@random}}(token) { document.getElementById("{{@form}}").submit(); } </script>';
		$string .= '<input type="hidden" name="invisible_recaptcha" value="true">';
		$string .= '<button {{@extra_attribs}} type="submit" data-callback="recaptchasubmit{{@random}}" data-sitekey="{{@key}}">{{@innerhtml}}</button>';
		$string = Preview::instance()->resolve($string, $hive);

		// Tag as loaded to prevent scripts being included
		base::instance()->RECAPTCHA_LOADED = true;

		return $string;
	}
}

class captcha extends \Template\TagHandler {
	function build ($attr, $innerhtml)
	{
		$attr["src"] = base::instance()->BASE.base::instance()->PATH."?captcha";

		if ($attr["recaptcha"])
		{
			if (!base::instance()->RECAPTCHA_LOADED)
			{
				$string .= "<script src='https://www.google.com/recaptcha/api.js?onload=renderRecaptchas&render=explicit' async defer></script>".PHP_EOL;
				$string .= "<script>window.renderRecaptchas = function() {var recaptchas = document.querySelectorAll('.g-recaptcha');for (var i = 0; i < recaptchas.length; i++) {grecaptcha.render(recaptchas[i], {sitekey: recaptchas[i].getAttribute('data-sitekey')});}}</script>".PHP_EOL;
				$string .= '<style> @media screen and (max-height: 575px){ #rc-imageselect, .g-recaptcha {transform:scale(0.77);-webkit-transform:scale(0.77);transform-origin:0 0;-webkit-transform-origin:0 0;} } </style>'.PHP_EOL;
				$string .= "<style> .text-xs-right { text-align: right; } .text-xs-center { text-align: center; } .text-xs-center > .g-recaptcha { display: inline-block; } .text-xs-right > .g-recaptcha { display: inline-block; }</style>".PHP_EOL;
			}
			
			if (array_key_exists("centered", $attr))
			{
				$string .= '<div class="text-xs-center">';
			}

			if (array_key_exists("right", $attr))
			{
				$string .= '<div class="text-xs-right">';
			}

			$string .= '<div class="g-recaptcha" data-sitekey="'.$attr["recaptcha"].'"></div>'.PHP_EOL;

			if (array_key_exists("centered", $attr) OR array_key_exists("right", $attr))
			{
				$string .= '</div>'.PHP_EOL;
			}

			base::instance()->RECAPTCHA_LOADED = true;

			return $string;
		}

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		return '<img ' . $attr . '>';
	}
}