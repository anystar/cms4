<?php

class blog extends prefab {

	public $path;

	function __construct($path) {
		$this->path = $path;

		$this->routes(base::instance());

		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {


	}

	function admin_routes($f3) {

		// Insert routes for this module
		$f3->route("GET /admin/".$this->path, function ($f3) {
			d("hit");
		});

		// Render admin panel
		//$f3->route('GET /admin/blog', '');

		// Render install page
		$f3->route('POST /admin/'.$this->path.'/install', 'blog::install');

		$f3->route('GET /admin/'.$this->path.'/documentation', function ($f3) {
			echo Template::instance()->render("/blog/documentation.html");
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/blog/test_file.html", "text/html"); });
	}

}