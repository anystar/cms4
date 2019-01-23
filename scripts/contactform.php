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

		// Remap naming convention
		if ($settings["template"]) $settings["delivery-template"] = $settings["template"];
		if ($settings["sendto"]) 
		{
			if (is_array($settings["sendto"])) {
				$settings["delivery-to"] = implode(", ", $settings["sendto"]);
			} else {
				$settings["delivery-to"] = $settings["sendto"];
			}
		}

		if ($settings["subject"]) $settings["delivery-subject"] = $settings["subject"];
		if ($settings["success"]) $settings["success-address"] = $settings["success"];
		if ($settings["spam"]) $settings["check-for-spam"] = $settings["spam"];

		$this->name = $settings["name"];
		$this->settings = $settings;

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

		// Permit failed captchas if in developer mode
		if (base::instance()->CONFIG["developer"] == "1")
			$captcha_passed = true;


		// Captcha Failed!
		if (!$captcha_passed) {

			// Because we are posting to the same page, change VERB
			// so F3 route will be call correctly.
			$f3->VERB = "GET";
			$f3->POST["captcha"] = "";

			return;
		}

		// Does 'email' exist in the form
		$emailFromForm = "";
		if (filter_var($f3->POST["email"], FILTER_VALIDATE_EMAIL))
		{
			// Does a name field exist
			$emailFromForm = $f3->POST["email"];
			if (array_check_value($f3->POST, "name"))
				$emailFromForm = $f3->POST["name"] . ' <' . $f3->POST["email"] . ">";
		}

		// Check if confirmation-template is set
		if (array_check_value($settings, "confirmation-template"))

		// Confirmation is actually set
		if ($settings["confirmation-template"] != "")

		// Confirmation is actually a file
		if (file_exists(getcwd()."/".$settings["confirmation-template"]))
		
		// 'email' field is valid email
		if ($emailFromForm != "")
		{
			// Send the 'confirmation' email
			$this->send_email(
				$emailFromForm, // Form user Name and Email
				$settings["confirmation-replyto"],
				$settings["confirmation-subject"],
				$settings["confirmation-template"],
				$f3->POST,
				null, // Files to attach
				$settings["check-for-spam"]
			);

			$replyTo = $to;
		}

		// Convert single emails to arrays
		if (!is_array($settings["delivery-to"]))
			$settings["delivery-to"] = array($settings["delivery-to"]);

		// Send email to 'owner'
		foreach ($settings["delivery-to"] as $email) {
			$this->send_email(
				$email,
				$emailFromForm,
				$settings["delivery-subject"],
				$settings["delivery-template"],
				$f3->POST,
				$f3->FILES,
				$settings["check-for-spam"]
			);
		}
	
		redirect($settings["success-address"]);
	}

	/**
	 *
	 * Send email
	 *
	 * @param string $to
	 * @param string $replyto
	 * @param string $subject Email subject
	 * @param string $body Filename for template to render
	 * @param array $attachfiles Array of $FILES to attach
	 * @param boolean $spam Check for spam? 
	 * @param array $hive Used for rendering within the template
	 */
	function send_email ($to, $replyTo, $subject, $body, $hive, $files=null, $spam = true)
	{
		// Use custom email template from client directory
		$body = \Template::instance()->render($body, null, $hive);

		$mailer = new \Mailer();

		// Attach any files
		if ($files != null)
		{
			foreach ($files as $file)
			{
				if ($file["tmp_name"] != "")
					if (file_exists($file["tmp_name"]))
						$mailer->attachFile($file["tmp_name"], $file["name"]);
			}
		}
				
		$to = parse_email($to);
		foreach ($to as $email=>$name)
			$mailer->addTo($email, $name);

		$replyTo = parse_email($replyTo);
		$mailer->setReply(key($replyTo), reset($replyTo));

		$mailer->setHTML($body);


		if ($spam !== "off")
		{
			if ($spam !== false)
			{	
				$mailer->antispam = $hive;
			}
		}
		
		$mailer->queue($subject);
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
		$hive['form'] = $attr["form-id"]; unset($attr["form-id"]);
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