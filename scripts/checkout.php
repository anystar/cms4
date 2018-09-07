<?php

class checkout extends prefab {

	public $settings;
	private $gateways;
	public $log;

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

		$paypal_creditcard_defaults["provider"] = "PaypalExpress";
		$paypal_creditcard_defaults["user"] = "apiusername";
		$paypal_creditcard_defaults["pass"] = "apipassword";
		$paypal_creditcard_defaults["signature"] = "apisigniture";
		$paypal_creditcard_defaults["endpoint"] = "sandbox or production";
		$paypal_creditcard_defaults["apiver"] = "204.0";
		$paypal_creditcard_defaults["return"] = "confirm-payment.html";
		$paypal_creditcard_defaults["cancel"] = "cancel-payment.html";
		$paypal_creditcard_defaults["success"] = "payment-success.html";
		$paypal_creditcard_defaults["receipt_template"] = "receipt_template.html";

		$paypal_button_defaults["provider"] = "PaypalButton";
		$paypal_button_defaults["email"] = "your paypal email address";

		$email_defaults["provider"] = "Email";
		$email_defaults["success"] = "payment-success.html";
		$email_defaults["invoice_template"] = "invoice-template.html";

		$defaults["class"] = "checkout";
		$defaults["name"] = "checkout";
		$defaults["send_receipt_copy"] = "orders@yourwebsite.com";
		$defaults["subject"] = "Example subject";

		$defaults["payment_gateways"][] = $email_defaults;
		$defaults["payment_gateways"][] = $paypal_button_defaults;
		$defaults["payment_gateways"][] = $paypal_defaults;
		$defaults["payment_gateways"][] = $paypal_creditcard_defaults;

		check(0, (count($settings) < 3), "**Default example:**",$defaults);

		// Create and ensure json file exists
		$this->log = new \DB\Jig\Mapper(base::instance()->JIG, $settings["name"]."_payments");

		// Sort out payment gateways
		foreach ($settings["payment_gateways"] as $gateway)
		{
			check (0, !class_exists(strtolower($gateway["provider"])."gateway"), "No gateway found for checkout script **".strtolower($gateway["provider"])."**");

			$className = strtolower($gateway["provider"])."gateway";
			$this->gateways[strtolower($gateway["provider"])] = new $className($this, $gateway);
		}

		if ($f3->exists("POST.checkout_submit")) {

			if ($f3->get("POST.checkout_submit") != $this->settings["name"])
				return; // Wrong checkout script.

			// Do any validation?
			if (!array_key_exists("recaptcha_privatekey", $settings))
				$captcha_passed = true;
			else
				$captcha_passed = false;

			// Check Captcha?
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

			if (!$captcha_passed)
			{
				$f3->POST["error"] = "Please check I'm not a robot";
				$f3->VERB = "GET";
				return;
			}

			check (0, !array_key_exists("amount_due", $f3->POST), "No ``amount_due`` hidden input field was supplied");

			// A simple check to ensure an amount due was generated
			if ($f3->POST["amount_due"] <= 0)
				return;

			// Send through payment gateway
			check (0, !array_key_exists("gateway", $f3->POST), "No gateway provided for checkout script");
			check (0, !class_exists(strtolower($f3->POST["gateway"])."gateway"), "No gateway found for checkout script");
			check (0, !array_key_exists($f3->POST["gateway"], $this->gateways), "No settings for this gateway".$f3->POST['gateway']);

			$gateway = $this->gateways[$f3->POST["gateway"]];

			// Organize data
			unset($f3->POST["checkout_submit"]);
			unset($f3->POST["gateway"]);

			$data = $f3->POST;

			$data["reference"] = $this->generateReferenceNumber();

			$gateway->submit($data);

			$f3->VERB = "GET";
		}

		$tmpl = \Template::instance();
		$tmpl->extend($this->settings["name"], 'CheckoutFormHandler::render');
		$tmpl->extend('input','\Template\Tags\Input::render');
		$tmpl->extend('textarea','\Template\Tags\Textarea::render');
		$tmpl->extend('select','\Template\Tags\Select::render');
		$tmpl->extend('option','\Template\Tags\Option::render');
		$tmpl->extend('captcha', 'checkout_captcha::render');

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

		$mailer = new \Mailer();

		$mailer->addTo($options["sendto"], $options["sendName"]);
		$mailer->setReply($options["fromAddress"], $options["fromName"]);
		$mailer->setHTML($renderedTemplate);

		$mailer->queue($options["subject"]);
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


// https://stackoverflow.com/questions/6322247/dynamic-paypal-button-generation-isnt-it-very-insecure
class PaypalButtonGateway {

	private $settings;

	function __construct ($checkout, $settings) {

		$f3 = base::instance();
		$this->settings = $settings;
		$this->checkout = $checkout;
		$this->return();
	}

	function submit($data) {

		$f3 = base::instance();

		$f3->data = $data;

		$data["pending_id"] = uniqid();

		$this->checkout->log($data);

		setcookie("pending", $data["pending_id"]);

		redirect($data['redirect']);
	}

	function return() {
		
		base::instance()->route("GET /".$this->settings["return"], function ($f3) {

			k("Not working");

			if (!array_key_exists("pending", $f3->COOKIE))
				$f3->error(500, "Transaction ID not found!");

			$jig = $this->checkout->log->find(array("@pending_id=?", $f3->COOKIE["pending"]));

			if (count($jig) == 0) 
				$f3->error(500, "Transaction ID not found!");
			
			$record = $jig[0];	
			$f3->data = $record->cast();

			$body = \Template::instance()->render($this->settings["invoice_template"], null);

			// Send copy to buyer
			$options = [];
			$options["sendName"] = $f3->data["name"];
			$options["fromName"] = $this->settings["sender-name"];
			$options["subject"] = Template::instance()->resolve($this->settings["subject"], $f3->data);
			$options["sendto"] = $f3->data["email"];

			$this->checkout->sendmail($body, $options);

			if (array_key_exists("send_receipt_copy", $this->settings)) {
				foreach ($this->settings["send_receipt_copy"] as $email)
				{
					// Send copy too seller
					$options = [];
					$options["sendName"] = $this->settings["sender-name"];
					$options["fromName"] = $f3->data["name"];
					$options["subject"] = Template::instance()->resolve($this->settings["subject"], $f3->data);
					$options["sendto"] = $email;

					$this->checkout->sendmail($body, $options);
				}
			}

			$record->clear("pending_id");
			$record->save();

			redirect($this->settings["success"]);
		});
	}
}

class EmailGateway {

	private $settings;

	function __construct ($checkout, $settings) {

		$f3 = base::instance();
		$this->settings = $settings;
		$this->checkout = $checkout;

		if (!is_array($this->settings["send_receipt_copy"]))
			$this->settings["send_receipt_copy"] = array($this->settings["send_receipt_copy"]);
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

		if (array_key_exists("send_receipt_copy", $this->settings)) {
			foreach ($this->settings["send_receipt_copy"] as $email)
			{
				// Send copy too seller
				$options = [];
				$options["sendName"] = $this->settings["sender-name"];
				$options["fromName"] = $data["name"];
				$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
				$options["sendto"] = $email;

				$this->checkout->sendmail($body, $options);
			}
		}

		$this->checkout->log($data);

		redirect($this->settings["success"]);
	}
}

class PaypalExpress_CreditCardGateway {

	private $settings;

	function __construct ($checkout, $settings) {

		$this->settings = $settings;
		$this->complete_payment();
	}

	function submit ($data) {

		$f3 = base::instance();

		if ($this->settings["endpoint"] == "sandbox")
		{				
			$this->settings["user"] = $f3->CONFIG["paypal_express_sandbox"]["user"];
			$this->settings["pass"] = $f3->CONFIG["paypal_express_sandbox"]["pass"];
			$this->settings["signature"] = $f3->CONFIG["paypal_express_sandbox"]["signature"];
		}

		$action = "Sale";
		$currency = "AUD";
		$cardtype = "VISA";
		$number = $f3->POST["cc_number"];

			// Parse the expiry date
			$exp = explode("/", $f3->POST["cc_expiry"]);
			$month = DateTime::createFromFormat('!m', $exp[0]);
			$month = $month->format('m');

			$year = DateTime::createFromFormat('!y', $exp[1]);
			$year = $year->format('Y');

			$day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
			$expiry = DateTime::createFromFormat("dmY", $day.$month.$year);

		$expiry = $month.$year;
		$cvc = $f3->POST["cc_cvc"];
		$amount_due = $f3->POST["amount_due"];
		$ipaddress = $f3->IP;

		$paypal = new PayPal($this->settings);

		$result = $paypal->dcc($action, $currency, $amount_due, $cardtype, $number, $expiry, $cvc, $ipaddress);


		if ($result['ACK'] != 'Success' && $result['ACK'] != 'SuccessWithWarning') {
			$f3->POST["error"] = $result["L_LONGMESSAGE0"];
		}
		else
		{
			redirect($this->settings["success"]);
		}
	}

	function complete_payment () {

		base::instance()->route("GET /".$this->settings["return"], function ($f3) {
			
			redirect($this->settings["success"]);
		});

	}

}

class PaypalExpressGateway {

	private $settings;

	function __construct ($checkout, $settings) {

		$this->settings = $settings;
		$this->checkout = $checkout;

		check(0, !array_check_value($settings, "provider"), "No `provider` field set on gateway", "Default: PaypalExpress", $settings);
		check(0, !array_check_value($settings, "user"), "No `user` field set on gateway", "Default: paypal api user", $settings);
		check(0, !array_check_value($settings, "pass"), "No `pass` field set on gateway", "Default: paypal api pass", $settings);
		check(0, !array_check_value($settings, "signature"), "No `signature` field set on gateway", "Default: paypal api signature", $settings);
		check(0, !array_check_value($settings, "endpoint"), "No `endpoint` field set on gateway", "Default: sandbox/production", $settings);
		check(0, !array_check_value($settings, "apiver"), "No `apiver` field set on gateway", "Default: 204.0", $settings);
		check(0, !array_check_value($settings, "return"), "No `return` field set on gateway", "Default: return.html", $settings);
		check(0, !array_check_value($settings, "cancel"), "No `cancel` field set on gateway", "Default: cancel.html", $settings);
		check(0, !array_check_value($settings, "success"), "No `success` field set on gateway", "Default: success.html", $settings);

		check(0, !array_check_value($settings, "send_name"), "No `send_name` field set on gateway", "Default: Business Name", $settings);
		check(0, !array_check_value($settings, "send_receipt_copy"), "No `send_receipt_copy` field set on gateway", "Default: Email or list of emails", $settings);
		check(0, !array_check_value($settings, "subject"), "No `subject` field set on gateway", "Default: Email subject title", $settings);
		check(0, !array_check_value($settings, "receipt_template"), "No `receipt_template` field set on gateway", "Default: receipt_template.html", $settings);

		$this->complete_payment();
	}

	function submit ($data) {
		$f3 = base::instance();

		if ($f3->CONFIG["developer"] == '1')
		{
			$this->settings["return"] = "http://paypal2.darklocker.com".$f3->BASE."/".$this->settings["return"];
			$this->settings["cancel"] = "http://paypal2.darklocker.com".$f3->BASE."/".$this->settings["cancel"];

			$default_sandbox = array();
			$default_sandbox["user"] = "paypal user";
			$default_sandbox["pass"] = "paypal pass";
			$default_sandbox["signature"] = "paypal signature";

			check (500, !array_check_value($f3->CONFIG, "paypal_express_sandbox"), "Please set paypal_express_sandbox array in config.ini file", $default_sandbox);
			
			$this->settings["user"] = $f3->CONFIG["paypal_express_sandbox"]["user"];
			$this->settings["pass"] = $f3->CONFIG["paypal_express_sandbox"]["pass"];
			$this->settings["signature"] = $f3->CONFIG["paypal_express_sandbox"]["signature"];
			$this->settings["endpoint"] = "sandbox";
		}
		else
		{
			$this->settings["return"] = $f3->SCHEME."://".$f3->HOST.$f3->BASE."/".$this->settings["return"];
			$this->settings["cancel"] = $f3->SCHEME."://".$f3->HOST.$f3->BASE."/".$this->settings["cancel"];
		}

		$paypal = new PayPal($this->settings);
		
		$result = $paypal->create("Sale", "AUD", $data["amount_due"]);

		$f3->set("SESSION.paypalexpress_data", $data);

		redirect($result['redirect']);
	}

	function complete_payment () {
	base::instance()->route("GET /".$this->settings["return"], function ($f3) {

			$data = $f3->get("SESSION.paypalexpress_data");

			if (!$f3->exists("SESSION.paypalexpress_data")) {
				$f3->error(404);
				return;
			}

			if ($f3->CONFIG["developer"] == '1')
			{				
				$this->settings["user"] = $f3->CONFIG["paypal_express_sandbox"]["user"];
				$this->settings["pass"] = $f3->CONFIG["paypal_express_sandbox"]["pass"];
				$this->settings["signature"] = $f3->CONFIG["paypal_express_sandbox"]["signature"];
				$this->settings["endpoint"] = "sandbox";
			}

			$token = $f3->get('GET.token');
			$payerid = $f3->get('GET.PayerID');

			$paypal = new PayPal($this->settings);
			$result = $paypal->complete($token, $payerid);

			// Check the API call was successful
			if ($result['ACK'] != 'Success' && $result['ACK'] != 'SuccessWithWarning')
			{
				if (array_check_value($this->settings, "error"))
				{
					$f3->set("paypal", $result);
					$body = \Template::instance()->render($this->settings["error"], null);
				}
				else
				{
					switch ($result["L_ERRORCODE0"])
					{
						case "10486":
							check(100, true, "We're sorry, but your transaction couldn't be completed using the selected card because it has been denied by the card issuer.");
						break;
						
						default:
							if ($f3->CONFIG["email_errors"])
							{
								$email  = "<h1>Paypal Express Error</h1>";
								$email .= "<p>";
								$email .=   markdown::instance()->convert($ERROR["text"]);
								$email .=   "<br>";
								$email .=   "<pre><code>".json_encode($result, JSON_PRETTY_PRINT)."</code></pre>";
								$email .=   "<br>";
								$email .=   "<pre><code>".json_encode($this->settings, JSON_PRETTY_PRINT)."</code></pre>";
								$email .=   "<br>";
								$email .=   "<pre><code>".$ERROR["trace"]."</code></pre>";
								$email .= "</p>";
							
								$mailer = new Mailer();
								$mailer->addTo("darklocker@gmail.com");
								$mailer->setHTML($email);
								$mailer->send("Paypal Express Checkout Error message");
								unset($mailer);
							}

							$f3->error("We're sorry but there was a problem with the checkout process.");
						break;
					}
				}

				return;
			}

			$data["paypal_data"] = $result;

			$f3->set("data", $data);
			$body = \Template::instance()->render($this->settings["receipt_template"], null);

			// Send copy to buyer
			$options = [];
			$options["sendName"] = $data["name"];
			$options["fromName"] = $this->settings["send_name"];;

			$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
			$options["sendto"] = $data["email"];

			$this->checkout->sendmail($body, $options);

			// Send copy too seller
			$options = [];
			$options["sendName"] = $this->settings["send_name"];
			$options["fromName"] = $data["name"];
			$options["subject"] = Template::instance()->resolve($this->settings["subject"], $data);
			$options["sendto"] = $this->settings["send_receipt_copy"];

			$this->checkout->sendmail($body, $options);

			$this->checkout->log($data);

			$f3->clear("SESSION.paypalexpress_data");

			redirect($this->settings["success"]);
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

		$redirect_element = '<input type="hidden" name="redirect" value="'.$attr["redirect"].'" />';

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$content = $this->tmpl->build($content);

		return $redirect_element . '<button ' . $attr . '>'. $content . '</button>';
	}
}

class paypalExpressTagHandler extends \Template\TagHandler {

	function build ($attr, $content) {

		$attr["name"] = "gateway";

		if ($attr["gateway"] == "creditcard")
			$attr["value"] = "paypalexpress_creditcard";
		else
			$attr["value"] = "paypalexpress";

		unset($attr["gateway"]);

		$attr["type"] = "submit";

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$content = $this->tmpl->build($content);

		return '<button ' . $attr . '>'. $content . '</button>';
	}
}

class checkout_captcha extends \Template\TagHandler {
	function build ($attr, $content)
	{

		$centered = "";
		if (array_key_exists("centered", $attr))
			$centered = ' style="display: inline-block" ';

		$attr["src"] = base::instance()->BASE.base::instance()->PATH."?captcha";

		if ($attr["recaptcha"])
		{
			$string .= "<script src='https://www.google.com/recaptcha/api.js'></script>".PHP_EOL;
			$string .= '<div class="g-recaptcha" data-sitekey="'.$attr["recaptcha"].'"'.$centered.'></div>'.PHP_EOL;

			return $string;
		}

		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		return '<img ' . $attr . '>';
	}
}