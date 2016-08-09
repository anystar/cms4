<?php

// This module maps URL addresses to files in the client folder.

// Sub folders are not supported at the current time.

class pages extends prefab {

	static $has_routed = false;

	function __construct() {
		$f3 = base::instance();

		if ($this->hasInit($f3))
			$this->routes(base::instance());
	}

	function routes($f3) {

		// Handle generic pages
		$f3->route(['GET /', 'GET /@page'], function ($f3, $params) {

			// prevent running twice.
			if (pages::$has_routed) exit;
			pages::$has_routed = true;

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


		// Handle sub pages
		$f3->route('GET /@page/*', function ($f3, $params) {
		
			// prevent running twice.
			if (pages::$has_routed) exit;		
			pages::$has_routed = true;

				$page = $params["page"] . ".html";
				$subpage = ltrim($params[0], '/');

				$result = $f3->DB->exec("SELECT id FROM pages WHERE page=?", $subpage)[0]["id"];

				$f3->set('UI', getcwd()."/");

				if (!$result)
					$f3->error("404");
				else
				{
					if (file_exists($page))
						echo Template::instance()->render($page);
					else
						$f3->error("404");
				}
		});
	}

	function hasInit($f3) {

		$result = $f3->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='pages'");

		if (!$result) {
			$f3->DB->exec("CREATE TABLE 'pages' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'page' TEXT NOT NULL, 'settings' TEXT);");
		}
		
		return true;
	}
}