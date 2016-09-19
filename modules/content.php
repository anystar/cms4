<?php

// This module maps URL addresses to files in the client folder.

// Sub folders are not supported at the current time.

class content extends prefab {

	static $has_routed = false;
	static $page;
	static $file;



	function __construct() {
		$f3 = base::instance();

		content::$page = $this->determine_page($f3);


		$f3->route(['GET /', 'GET /@page', 'GET /@page/*'], function ($f3, $params) {

			d($f3->UI);

			// Render as a template file
			if (mime_content_type(getcwd()."/".content::$page) == "text/plain") {
				echo Template::instance()->render(content::$page);
			} 
		});
	}



	function determine_page ($f3) {

		$path = ltrim($f3->PATH, "/");
		$cwd = getcwd();

		if ($path == "") {
			if (is_file($cwd."/index.html"))
				return "index.html";

			if (is_file($cwd."/index.htm"))
				return "index.html";

			return;
		}

		// Check if file is html
		if (is_file($cwd."/".$path.".html"))
			return $path;

		// Check for file
		if (is_file($cwd."/".$path))
			return $path;

		// Check for a generic file
		if (is_file($cwd."/".dirname($path)."/generic.html"))
			return dirname($path)."/generic.html";
	}

	function load_template () {

	}
}