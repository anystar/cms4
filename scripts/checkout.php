<?php

class checkout extends prefab {

	public $settings;
	private $gateways;
	private $log;

	private $referenceNumber;

	function __construct($settings) {
		$f3 = base::instance();

		$this->settings = $settings;

		$paypal_defaults["provider"] = "PaypalExpress";
		$paypal_defaults["user"] = "apiusername";
		$paypal_defaults["pass"] = "apipassword";
		$paypal_defaults["signature"] = "apisigniture";
		$paypal_defaults["endpoint"] = "sandbox or production";
		$paypal_defaults["apiver"] = "204.0";
		$paypal_defaults["return"] = "confirm-payment.html";
		$paypal_defaults["cancel"] = "cancel-payment.html";
		$paypal_defaults["success"] = "payment-success.html";
		$paypal_defaults["receipt_template"] = "receipt_template.html";

		$paypal_button_defaults["provider"] = "PaypalButton";
		$paypal_button_defaults["email"] = "your paypal email address";

		$email_defaults["provider"] = "Email";
		$email_defaults["success"] = "payment-success.html";
		$email_defaults["invoice_template"] = "invoice-template.html";

		$defaults["class"] = "checkout";
		$defaults["name"] = "checkout";
		$defaults["send_receipt_copy"] = "orders@yourwebsite.com";
		$defaults["subject_line"] = "Example subject";

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
			check (0, !class_exists(strtolower($gateway["provider"])."gateway"), "No gateway found for checkout script **".strtolower($gateway["provider"])."**");

			$className = strtolower($gateway["provider"])."gateway";
			$this->gateways[strtolower($gateway["provider"])] = new $className($this, $gateway);
		}

		if ($f3->exists("POST.checkout_submit")) {

			if ($f3->get("POST.checkout_submit") != $this->settings["name"])
				return; // Wrong checkout script.

			// Do any validation?

			// Check Captcha?

			// Send through payment gateway
			check (0, !array_key_exists("gateway", $f3->POST), "No gateway provided for checkout script");
			check (0, !class_exists(strtolower($f3->POST["gateway"])."gateway"), "No gateway found for checkout script");
			check (0, !array_key_exists($f3->POST["gateway"], $this->gateways), "No settings for this gateway");

			$gateway = $this->gateways[$f3->POST["gateway"]];

			// Organize data
			unset($f3->POST["checkout_submit"]);
			unset($f3->POST["gateway"]);

			$data = $f3->POST;

			$data["reference"] = $this->generateReferenceNumber();

			$gateway->submit($data);
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

	function log ($data) {

		// TODO: Ensure it has reference

		$this->log->copyFrom($data);
		$this->log->save();
	}

	function sendmail ($renderedTemplate, $options) {
		$mailer = base::instance()->MAILER;
		$mailer->addTo($options["sendto"], $options["sendName"]);
		$mailer->setReply($options["fromAddress"] ,$options["fromName"]);
		$mailer->setHTML($renderedTemplate);

		$mailer->send($options["subject"]);
	}

	function generateReferenceNumber () {

		$highest = 1;

		$result = $this->log->find();

		if ($result==FALSE) return "0001";
		
		foreach ($result as $log) {

			if ($log->exists("reference"))
			{
				$ref = (int)$log->reference;
				
				if ($ref > $highest)
					$highest = $ref;
			}
		}

		$highest += 1;
		$highest = str_pad($highest, 4, '0', STR_PAD_LEFT);

		return $highest;
	}
}


class EmailGateway {

	private $settings;

	function __construct ($checkout, $settings) {

		$f3 = base::instance();
		$this->settings = $settings;
		$this->checkout = $checkout;
	}

	function submit ($data) {
		$f3 = base::instance();

		$f3->data = $data;
		$body = \Template::instance()->render($this->settings["invoice_template"], null);

		// Send copy to buyer
		$options = [];
		$options["sendName"] = $data["name"];
		$options["fromName"] = $this->settings["sender-name"];
		$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
		$options["sendto"] = $data["email"];

		$this->checkout->sendmail($body, $options);

		// Send copy too seller
		$options = [];
		$options["sendName"] = $this->settings["sender-name"];
		$options["fromName"] = $data["name"];
		$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
		$options["sendto"] = $this->settings["send_receipt_copy"];

		$this->checkout->sendmail($body, $options);

		$this->checkout->log($data);

		$f3->reroute($this->settings["success_page"]);
	}
}


class PaypalExpressGateway {

	private $settings;

	function __construct ($checkout, $settings) {

		$f3 = base::instance();
		$this->settings = $settings;
		$this->checkout = $checkout;

		$this->complete_payment();
	}

	function submit ($data) {
		$f3 = base::instance();

		// Need to setup a DNS record somewhere to loopback to my dev machine
		if ($this->settings["api"]["endpoint"] == "sandbox")
		{
			$this->settings["api"]["return"] = "http://paypal.darklocker.com".$f3->BASE."/".$this->settings["api"]["return"];
			$this->settings["api"]["cancel"] = "http://paypal.darklocker.com".$f3->BASE."/".$this->settings["api"]["cancel"];
		}
		else
		{
			$this->settings["api"]["return"] = $f3->SCHEME."://".$f3->HOST.$f3->BASE."/".$this->settings["api"]["return"];
			$this->settings["api"]["cancel"] = $f3->SCHEME."://".$f3->HOST.$f3->BASE."/".$this->settings["api"]["cancel"];
		}
		
		$paypal = new PayPal($this->settings["api"]);
		$result=$paypal->create("Sale", "AUD", $data["amount_due"]);

		$f3->set("SESSION.paypalexpress_data", $data);

		$f3->reroute($result['redirect']);
	}

	function complete_payment () {
	base::instance()->route("GET /".$this->settings["api"]["return"], function ($f3) {
			
			$data = $f3->get("SESSION.paypalexpress_data");

			$token = $f3->get('GET.token');
			$payerid = $f3->get('GET.PayerID');

			$paypal = new PayPal($this->settings["api"]);
			$result = $paypal->complete($token, $payerid);

			// Check the API call was successful
			if ($result['ACK'] != 'Success' && $result['ACK'] != 'SuccessWithWarning')
			{
				base::instance()->error('Paypal Express Error with API call -'.$result["L_ERRORCODE0"]);
				return;
			}

			$data["paypal_data"] = $result;
			$f3->set("data", $data);
			$body = \Template::instance()->render($this->settings["receipt_template"], null);

			// Send copy to buyer
			$options = [];
			$options["sendName"] = $data["name"];
			$options["fromName"] = "fromName";
			$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
			$options["sendto"] = $data["email"];

			$this->checkout->sendmail($body, $options);

			// Send copy too seller
			$options = [];
			$options["sendName"] = $this->settings["sender-name"];
			$options["fromName"] = $data["name"];
			$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
			$options["sendto"] = $settings["send_receipt_copy"];

			$this->checkout->sendmail($body, $options);

			$this->checkout->log($data);

			$f3->clear("SESSION.paypalexpress_data");

			$f3->reroute($this->settings["success_page"]);
	});}
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