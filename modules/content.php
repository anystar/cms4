<?php

// This module maps URL addresses to files in the client folder.

// Sub folders are not supported at the current time.

class content extends prefab {

	static $has_routed = false;
	static $page;

	function __construct() {
		$f3 = base::instance();


		$path = $this->determine_path();


		// Lets get the special "all" content for all paths and then override
		// if there are unique entries for current path
		$result = $f3->DB->exec("SELECT * FROM contents WHERE path='all'");
		foreach ($result as $content)
			$f3->set($content["name"], $content["content"]);

		// Get content for this current path
		$result = $f3->DB->exec("SELECT * FROM contents WHERE path=?", $path);
		foreach ($result as $content)
			$f3->set($content["name"], $content["content"]);

		$this->routes(base::instance());
	}

	static function set ($name, $path, $content) {
		$db = base::instance()->DB;

		// Do we update or insert?
		if ($id = $db->exec("SELECT id FROM contents WHERE name=? AND path=?", [$name, $path])[0]["id"])
		{
			$db->exec("UPDATE contents SET content=? WHERE id=?", [$content, $id]);
		} else {
			$db->exec("INSERT INTO contents (name, path, content) VALUES (?, ?, ?)", [$name, $path, $content]);
		}

		return;
	}

	function determine_path () {

		$path = ltrim(base::instance()->PATH, "/");
		$cwd = getcwd();

		if ($path == "") {
			if (is_file(getcwd()."/index.html"))
				return "index";
			else if (is_file($cwd."/index.htm"))
				return "index";
		}

		if (is_file(getcwd()."/".$path.".html"))
			return $path;

		// Check for file
		if (is_file(getcwd()."/".$path))
			return $path;


	}


	function routes($f3) {

		// Handle generic pages
		$f3->route(['GET /', 'GET /@page'], function ($f3, $params) {

			$f3->set('UI', getcwd()."/");
			
			$path = $this->determine_path();

			if (file_exists($path))
				echo Template::instance()->render($path);
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
		base::instance()->set("content.init", true);
		return true;
	}
}