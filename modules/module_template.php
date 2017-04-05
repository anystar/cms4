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
}