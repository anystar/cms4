<?php

class repeat extends prefab {

	private $routes;
	private $namespace;

	function __construct($namespace) {
		$this->namespace = $namespace;
		$this->routes = setting($namespace."_routes");

		if (admin::$signed)
		{
			$this->setup_routes(base::instance());
			$this->admin_routes(base::instance());
		}


		$this->routes(base::instance());
	}

	function routes($f3) {
		// Insert routes for this module

	}

	function admin_routes($f3) {

		$f3->route('GET /admin/'.$this->namespace, function ($f3) {
			$f3->namespace = $this->namespace;

			echo Template::instance()->render("/repeat/repeat.html");
		});

	}

	function setup_routes ($f3) {

		$f3->route('GET /admin/'.$this->namespace.'/setup', function ($f3) {
			
			echo Template::instance()->render("/repeat/setup.html");
		});

		$f3->route('POST /admin/'.$this->namespace.'/setup', function ($f3) {
			
			$this->install();

			$f3->reroute('/admin/'.$this->namespace."/setup");
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/repeat/test_file.html", "text/html"); });
	}

	function install () {


	}

}