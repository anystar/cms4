<?php

class blog extends prefab {

	public $namespace;

	function __construct($namespace) {
		$this->namespace = $namespace;

		$this->routes(base::instance());

		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {


	}

	function admin_routes($f3) {

		// Insert routes for this module
		$f3->route("GET /admin/".$this->namespace, function ($f3) {
			$f3->namespace = $this->namespace;

			echo Template::instance()->render("/blog/blog.html");
		});

		// Render admin panel
		//$f3->route('GET /admin/blog', '');

		// Render install page
		$f3->route('POST /admin/'.$this->namespace.'/install', 'blog::install');

		$f3->route('GET /admin/'.$this->namespace.'/documentation', function ($f3) {
			echo Template::instance()->render("/blog/documentation.html");
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/blog/test_file.html", "text/html"); });
	}

}