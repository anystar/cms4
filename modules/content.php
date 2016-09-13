<?php

// This module maps URL addresses to files in the client folder.

// Sub folders are not supported at the current time.

class content extends prefab {

	static $has_routed = false;
	static $page;

	function __construct() {
		$f3 = base::instance();

		if ($this->hasInit($f3))
			$this->routes(base::instance());
	}



	function determine_page ($f3) {

		$path = ltrim($f3->PATH, "/");

		if (is_file(getcwd()."/".$path.".html"))
			return $path;

		// Check for file
		if (is_file(getcwd()."/".$path))
			return $path;

		die;

	}


	function routes($f3) {

		// Handle generic pages
		$f3->route(['GET /', 'GET /@page'], function ($f3, $params) {

			// prevent running twice.
			if (content::$has_routed) exit;
			
			content::$has_routed = true;

			$f3->set('UI', getcwd()."/");

			if (!file_exists($params["page"]))
				$page = ($params[0]=="/") ? "index.html" : $params["page"].".html";
			else
				$page = $params["page"];

			if (file_exists($page))
				echo Template::instance()->render($page);
			else
				$f3->error("404");
		});

		// Handle sub pages.
		// 
		// Please read:
		// Sub pages are determined by folder structure. For example
		// if there is a physical folder called "products" it will attempt
		// to search for the file in that folder. If the file doesn't exsist
		// it will look for a generic.html file.
		$f3->route('GET /@page/*', function ($f3, $params) {

			// prevent running route twice.
			if (content::$has_routed) exit;		
			content::$has_routed = true;

			$folder = $params["page"];
			$page = ".".$params[0].".html";

			// Is the root part of the address a folder?
			if (!is_dir($params["page"]))
			{
				// Redirect back to login if user is trying to access admin panel
				if (!admin::$signed)
					if (preg_match("/\/admin\/(?!login)(.*)/", $f3->PATH))
					{
						$f3->reroute("/admin");
						exit;
					}

				$f3->error("404");
				return;
			}

			// Is there a page file?
			$result = false;
			if (is_file($page))
			{
				$fileToLoad = $page;
				$result = true;
			}
			else
			{
				$fileToLoad = $folder."/generic.html";
				is_file($fileToLoad);
				$result = true;
			}


			// Is there a record in the database about this page?
			//$path = ltrim($params[0], '/');
			//$result = $f3->DB->exec("SELECT id FROM pages WHERE page=?", $path)[0]["id"];

			if (!$result)
				$f3->error("404");
			else
			{
				// One last check to ensure page exsists
				if (file_exists($fileToLoad))
					echo Template::instance()->render($fileToLoad);
				else
					$f3->error("404");
			}
		});
	}




	function hasInit($f3) {
		
		return true;
	}
}