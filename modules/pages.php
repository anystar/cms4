<?php

class pages extends prefab {

	static $has_routed = false;

	function __construct() {

		$this->routes(base::instance());
	}

	function routes($f3) {


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
	}
}