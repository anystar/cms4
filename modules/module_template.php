<?php

class module_name extends prefab {

	function __construct() {

		if ($this->installed())
			$this->routes(base::instance());

		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {

		// Insert routes for this module

	}

	function admin_routes($f3) {
		
		// Render admin panel
		//$f3->route('GET /admin/module_name', '');

		// Render install page
		$f3->route('POST /admin/module_name/install', 'module_name::install');

		$f3->route("GET /admin/module_name/documentation", function ($f3) {
			echo Template::instance()->render("/module_name/documentation.html");
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/module_name/test_file.html", "text/html"); });
	}
}