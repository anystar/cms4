<?php

// https://github.com/kreative/F3-PYPL


class paypal_gateway extends prefab {

	private $routes;
	private $namespace;

	function __construct($namespace) {
		$this->namespace = $namespace;
		$this->routes = setting($namespace."_routes");

		$f3 = base::instance();

		$this->setup_routes(base::instance());

		if (!$this->install_check())
		{
			if (!$f3->mask("/admin/paypal_gateway/setup"))
				if ($f3->mask("/admin/paypal_gateway") || $f3->mask("/admin/paypal_gateway/*")) { $f3->reroute("/admin/paypal_gateway/setup"); }
		}




		// user - Your PayPal API Username
		// pass - Your PayPal API Password
		// signature - Your PayPal API Signature
		// endpoint - API Endpoint, values can be 'sandbox' or 'production'
		// apiver - API Version current release is 124.0
		// return - The URL that PayPal redirects your buyers to after they have logged in and clicked Continue or Pay
		// cancel - The URL that PayPal redirects the buyer to when they click the cancel & return link
		// log - logs all API requests & responses to paypal.log



		// user=b1amlan-facilitator_api1.gmail.com
		// pass=4T6KGDCAM9AZQVLY
		// signature=AiBPvxZf3Acqu3ecaqBTCTB8CcTNAqcuLVnXuvyzza86Tq8ZwrAUry67
		// endpoint=sandbox
		// apiver=124.0
		// return=http://webw.dev/test/
		// cancel=http://webw.dev/test/
		// log=0







		if (admin::$signed)
			$this->admin_routes(base::instance());


		$this->routes(base::instance());
	}

	function routes($f3) {
		// Insert routes for this module

	}

	function admin_routes($f3) {
		
		// Render admin panel
		$f3->route('GET /admin/paypal_gateway', function ($f3) {
			echo Template::instance()->render("/paypal_gateway/paypal_gateway.html");
		});

		// $f3->route("GET /admin/paypal_gateway/documentation", function ($f3) {
		// 	echo Template::instance()->render("/paypal_gateway/documentation.html");
		// });
	}

	function setup_routes ($f3) {

		$f3->route('GET /admin/paypal_gateway/setup', function ($f3) {
			
			echo Template::instance()->render("/paypal_gateway/setup.html");
		});

		$f3->route('POST /admin/paypal_gateway/setup', function ($f3) {

			$this->install();

			$f3->reroute('/admin/paypal_gateway/setup');
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/paypal_gateway/test_file.html", "text/html"); });
	}

	function install () {

		setting("paypal_gateway_setup", true);

	}

	function install_check () {

		if (!setting("paypal_gateway_setup"))
		{
			return false;
		}

		return true;
	}
}