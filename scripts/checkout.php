<?php

class checkout extends prefab {

	private $settings;
	private $gateways;
	private $log;

	private $referenceNumber;

	function __construct($settings) {
		$f3 = base::instance();

		$this->settings = $settings;

		$paypal_defaults["provider"] = "PaypalExpress";
		$paypal_defaults["user"] = "apiusername";
		$paypal_defaults["pass"] = "apipassword";
		$paypal_defaults["signiture"] = "apisigniture";
		$paypal_defaults["endpoint"] = "sandbox or production";
		$paypal_defaults["apiver"] = "204.0";
		$paypal_defaults["return"] = "confirm_payment.html";
		$paypal_defaults["cancel"] = "cancel_payment.html";
		$paypal_defaults["receipt_template"] = "receipt_template.html";

		$paypal_button_defaults["provider"] = "PaypalButton";
		$paypal_button_defaults["email"] = "your paypal email address";

		$email_defaults["provider"] = "Email";
		$email_defaults["return"] = "confirm_payment.html";
		$email_defaults["cancel"] = "cancel_payment.html";
		$email_defaults["invoice_template"] = "invoice_template.html";

		$defaults["class"] = "checkout";
		$defaults["name"] = "checkout";
		$defaults["send_receipt_copy"] = "orders@yourwebsite.com";
		$defaults["success_page"] = "payment_success.html";

		$defaults["payment_gateways"][] = $email_defaults;
		$defaults["payment_gateways"][] = $paypal_button_defaults;
		$defaults["payment_gateways"][] = $paypal_defaults;

		check(0, (count($settings) < 3), "**Default example:**",$defaults);

		// Create and ensure json file exists
		$this->log = new \DB\Jig\Mapper(base::instance()->JIG, $settings["name"]."_payments");

		// Sort out payment gateways
		foreach ($settings["payment_gateways"] as $gateway)
		{
			check (0, !array_key_exists("provider", $gateway), "No provider field on gatway");
			check (0, !method_exists($this, "gateway_".strtolower($gateway["provider"])), "No gateway found for checkout script");

			$this->gateways[strtolower($gateway["provider"])] = $gateway;
		}

		if ($f3->exists("POST.checkout_submit")) {

			if ($f3->get("POST.checkout_submit") != $this->settings["name"])
				return; // Wrong checkout script.


			// Do any validation?

			// Check Captcha?

			// Send through payment gateway
			check (0, !array_key_exists("gateway", $f3->POST), "No gateway provided for checkout script");
			check (0, !method_exists($this, "gateway_".$f3->POST["gateway"]), "No gateway found for checkout script");
			check (0, !array_key_exists($f3->POST["gateway"], $this->gateways), "No settings for this gateway");

			$gateway = $f3->POST["gateway"];
			$methodName = "gateway_".$gateway;

			// Organize data
			unset($f3->POST["checkout_submit"]);
			unset($f3->POST["gateway"]);

			$data = $f3->POST;

			$data["reference"] = $this->generateReferenceNumber();

			$this->$methodName($data, $this->gateways[$gateway]);
		}

		$tmpl = \Template::instance();
		$tmpl->extend($this->settings["name"], 'CheckoutFormHandler::render');
		$tmpl->extend('input','\Template\Tags\Input::render');
		$tmpl->extend('textarea','\Template\Tags\Textarea::render');
		$tmpl->extend('select','\Template\Tags\Select::render');
		$tmpl->extend('option','\Template\Tags\Option::render');

		$tmpl->extend('email', 'emailTagHandler::render');
		$tmpl->extend('paypalbutton', 'paypalButtonTagHandler::render');
		$tmpl->extend('paypalexpress', 'paypalExpressTagHandler::render');
	}

	function gateway_email ($data, $settings) {
		$f3 = base::instance();

		$f3->data = $data;
		$body = \Template::instance()->render($settings["invoice_template"], null);

		// Send copy to buyer
		$options = [];
		$options["sendName"] = $data["name"];
		$options["fromName"] = $settings["sender-name"];
		$options["subject"] = Template::instance()->resolve($settings["subject"], $data);
		$options["sendto"] = $data["email"];

		$this->send_email($body, $options);

		// Send copy too seller
		$options = [];
		$options["sendName"] = $settings["sender-name"];
		$options["fromName"] = $data["name"];
		$options["subject"] = Template::instance()->resolve($settings["subject"], $data);
		$options["sendto"] = $settings["send_receipt_copy"];

		$this->send_email($body, $options);

		$f3->reroute($settings["success_page"]);		
	}

	function gateway_paypalexpress ($data, $settings) {

		k("PAYPAL Express gateway not implemented yet");
		$paypal = new PayPal;



		k($settings);

	}

	function gateway_paypalbutton ($data, $settings) {



	}

	function send_email ($renderedTemplate, $options) {

		$mailer = base::instance()->MAILER:
		$mailer->addTo($options["sendto"], $options["sendName"]);
		$mailer->setReply($options["fromAddress"] ,$options["fromName"]);
		$mailer->setHTML($renderedTemplate);

		$smtp->send($options["subject"]);
	}

	function log_payment ($log) {

		k("implement payment logged");
	}

	function generateReferenceNumber () {

		$highest = 1;

		foreach ($this->log->find() as $log) {

			$ref = (int)$log->reference;
			
			if ($ref > $highest)
				$highest = $ref;

		}

		$highest += 1;
		$highest = str_pad($highest, 4, '0', STR_PAD_LEFT);

		return $highest;
	}
}

class CheckoutFormHandler extends \Template\TagHandler {

	function build ($attr, $content)
	{
		$f3 = base::instance();

		// Always post to the same page the form is located on.
		$attr["src"] = '<?= $SCHEME."://".$HOST.$URI ?>';

		$content = $this->tmpl->build($content);

		$attr["method"] = "POST";

		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$id = "checkout";
		if (is_array($attr))
			if (array_key_exists("id", $attr))
				$id = $attr["id"];

		$hiddenInput = '<input type="hidden" name="checkout_submit" value="'.$id.'">';

		return '<form ' . $attr . '>' . $content . $hiddenInput . '</form>';
	}

}

class emailTagHandler extends \Template\TagHandler {

	function build ($attr, $content) {
		
		$attr["name"] = "gateway";
		$attr["value"] = "email";
		$attr["type"] = "submit";

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$content = $this->tmpl->build($content);

		return '<button ' . $attr . '>'. $content . '</button>';
	}
}

class paypalButtonTagHandler extends \Template\TagHandler {

	function build ($attr, $content) {
		
		$attr["name"] = "gateway";
		$attr["value"] = "paypalbutton";
		$attr["type"] = "submit";

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$content = $this->tmpl->build($content);

		return '<button ' . $attr . '>'. $content . '</button>';
	}
}

class paypalExpressTagHandler extends \Template\TagHandler {

	function build ($attr, $content) {
		
		$attr["name"] = "gateway";
		$attr["value"] = "paypalexpress";
		$attr["type"] = "submit";

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$content = $this->tmpl->build($content);

		return '<button ' . $attr . '>'. $content . '</button>';
	}
}