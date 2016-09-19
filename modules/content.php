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

		$f3->route(['GET /', 'GET /@page', 'GET /@page/*'], function ($f3, $params) {

			d($f3->UI);
	
			// Render as a template file
			if (mime_content_type(getcwd()."/".content::$page) == "text/plain") {
				echo Template::instance()->render(content::$page);
			} 
		});
	}

	function hasInit($f3) {
		base::instance()->set("content.init", true);
		return true;
	}
}