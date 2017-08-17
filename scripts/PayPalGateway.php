<?php

class PayPalGateway extends prefab {

	function __construct($settings) {
		GLOBAL $ROOTDIR;

		require_once ($ROOTDIR."/resources/F3-PYPL/lib/PayPal.php");

		$paypal = new PayPal($settings);
	}
}