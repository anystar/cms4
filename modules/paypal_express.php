<?php

// https://github.com/kreative/F3-PYPL


class paypal_express extends prefab {

	private $routes;
	private $namespace;

	function __construct($namespace) {
		$this->namespace = $namespace;
		$this->routes = setting($namespace."_routes");

		$f3 = base::instance();

		$this->setup_routes(base::instance());

		if (!$this->install_check())
		{
			if (!$f3->mask("/admin/paypal_express/setup"))
				if ($f3->mask("/admin/paypal_express") || $f3->mask("/admin/paypal_express/*")) { $f3->reroute("/admin/paypal_express/setup"); }
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
		$f3->route('GET /admin/paypal_express', function ($f3) {
			echo Template::instance()->render("/paypal_express/paypal_express.html");
		});

		// $f3->route("GET /admin/paypal_express/documentation", function ($f3) {
		// 	echo Template::instance()->render("/paypal_express/documentation.html");
		// });
	}

	function setup_routes ($f3) {

		$f3->route('GET /admin/paypal_express/setup', function ($f3) {
			
			echo Template::instance()->render("/paypal_express/setup.html");
		});

		$f3->route('POST /admin/paypal_express/setup', function ($f3) {

			$this->install();

			$f3->reroute('/admin/paypal_express/setup');
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/paypal_express/test_file.html", "text/html"); });
	}

	function install () {

		setting("paypal_express_setup", true);

	}

	function install_check () {

		if (!setting("paypal_express_setup"))
		{
			return false;
		}

		return true;
	}
}