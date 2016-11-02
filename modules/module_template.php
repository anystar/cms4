<?php

class module_name extends prefab {

	private $routes;
	private $namespace;

	function __construct($namespace) {
		$this->namespace = $namespace;
		$this->routes = setting($namespace."_routes");

		if (!$this->install_check())
		{
			$this->setup_routes;
			return;
		}

		if (admin::$signed)
			$this->admin_routes(base::instance());


		$this->routes(base::instance());
	}

	function routes($f3) {
		// Insert routes for this module

	}

	function admin_routes($f3) {
		
		// Render admin panel
		//$f3->route('GET /admin/module_name', '');


		// $f3->route("GET /admin/module_name/documentation", function ($f3) {
		// 	echo Template::instance()->render("/module_name/documentation.html");
		// });
	}

	function setup_routes ($f3) {

		$f3->route('GET /admin/'.$this->namespace.'/setup', function ($f3) {
			
			echo Template::instance()->render("/module_name/setup.html");
		});

		$f3->route('POST /admin/'.$this->namespace.'/setup', function ($f3) {
			
			$this->install();

			$f3->reroute('/admin/'.$this->namespace."/setup");
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/module_name/test_file.html", "text/html"); });
	}

	function install () {


	}

	function install_check () {

	}
}