<?php

class checkout extends prefab {

	function __construct($settings) {

		$paypal_defaults["provider"] = "Paypal";
		$paypal_defaults["user"] = "apiusername";
		$paypal_defaults["pass"] = "apipassword";
		$paypal_defaults["signiture"] = "apisigniture";
		$paypal_defaults["endpoint"] = "sandbox or production";
		$paypal_defaults["apiver"] = "204.0";
		$paypal_defaults["return"] = "confirm_payment.html";
		$paypal_defaults["cancel"] = "cancel_payment.html";

		$email_defaults["provider"] = "Email";
		$email_defaults["return"] = "confirm_payment.html";
		$email_defaults["cancel"] = "cancel_payment.html";

		$defaults["send_receipt_copy"] = "orders@yourwebsite.com";
		$defaults["invoice_template"] = "invoice_template.html";
		$defaults["success_page"] = "payment_success.html";

		$defaults["payment_gateways"][] = $email_defaults;
		$defaults["payment_gateways"][] = $paypal_defaults;
	
		check(0, (count($settings) < 3), "**Default example:**",$defaults);

	}

	function gateway_email ($settings) {


	}

	function gateway_paypal ($settings) {

	}
}