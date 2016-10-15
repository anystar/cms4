<?php

// This module maps URL addresses to files in the client folder.

// Sub folders are not supported at the current time.

class content extends prefab {

	static $has_routed = false;
	static $path;
	static $file;

	function __construct() {
		$f3 = base::instance();

		$this->installed();

		// Determines wether a file matches the route
		$this->determine_path();

		// Lets get the special "all" content for all paths and then override
		// if there are unique entries for current path
		$result = $f3->DB->exec("SELECT * FROM contents WHERE path='all'");
		foreach ($result as $content)
			$f3->set($content["name"], $content["content"]);

		// Get content for this current path
		$result = $f3->DB->exec("SELECT * FROM contents WHERE path=?", $f3->PATH);
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
			{
				content::$path = "index";
				content::$file = "index.html";
			}
			else if (is_file($cwd."/index.htm"))
			{
				content::$path = "index";
				content::$file = "index.htm";
			}
		}

		// No extension detected
		if (!preg_match("/\.[^\.]+$/i", $path, $ext))
		{
			if (is_file($cwd."/".$path.".html"))
			{
				content::$file = $path.".html";
				content::$path = $path;
			}

			else if (is_file($cwd."/".$path.".htm"))
			{
				content::$file = $path.".html";
				content::$path = $path;
			}
		} 
		else 
		{	
			$ext = $ext[0];

			if ($ext == ".html" || $ext == ".htm")
				$path = basename($path, $ext);

				if (is_file($cwd."/".$path.$ext))
				{
					content::$file = $path.$ext;
					content::$path = $path;
				}
		}

		if (is_file($path))
			content::$file = content::$path = $path;
	}

	function routes ($f3) {

		$f3->route(['GET /', 'GET /@path', 'GET /@path/*'], function ($f3, $params) {

			// Accepted mimetypes to render as a template file
			$accepted_mimetypes = [
				"text/html",
				"text/css",
				"text/plain",
				"application/javascript",
				"application/x-javascript",
			];

			if (!content::$file)
				$f3->error("404");
	
			$mime_type = mime_content_type2(getcwd()."/".content::$file);

			if (in_array($mime_type, $accepted_mimetypes))
				
				// Render as a template file
				echo Template::instance()->render(content::$file, $mime_type);
			else
			{
				// Render as raw data
				header('Content-Type: '.$mime_type.';');
				echo readfile(content::$file);
			}
		});

		$f3->route("GET /admin/content", function ($f3) {
			$this->load_sitemap($f3);

			echo Template::instance()->render("/content/content.html");
		});

		$f3->route("POST /admin/content/load-file [ajax]", function ($f3) {

			$file = $f3->POST["path"];

			if (file_exists($file)) {
				echo file_get_contents($file);
				exit;
			}
		});

		$f3->route("POST /admin/content/save-file [ajax]", function ($f3) {
			$file = $f3->POST["path"];
			$data = $f3->POST["contents"];

			if (file_exists($file))
				file_put_contents($file, $data);
		});

		$f3->route("POST /admin/content/delete-file [ajax]", function ($f3) {
			$file = $f3->POST["path"];

			if (file_exists($file))
				unlink($file);
		});
	}

	function load_sitemap ($f3) {
		
		$directory = $this->scan(new DirectoryIterator(getcwd()));

		$directory = ["root"=>$directory];
		$directory = json_encode($directory);

		$f3->set("content.site_map", $directory);
	}

	function scan( DirectoryIterator $dir )
	{
	  $data = array();
	  foreach ( $dir as $node )
	  {
	  	if ($node->getFilename()[0] == "." || $node->getFilename() == "tmp" || $node->getFilename() == "cms.php")
	  		continue;

	    if ( $node->isDir() && !$node->isDot() )
	    {
	    	$data["directories"][$node->getFilename()] = $this->scan( new DirectoryIterator( $node->getPathname() ) );
	    }
	    else if ( $node->isFile() )
	    {
	    	$data["files"][] = [ "filename" => $node->getFilename(), "path" => $node->getPath()."/".$node->getFilename() ];
	    }
	  }

	  if ($data["files"])
		  sort($data["files"]);

	  return $data;
	}


	function installed () {

		if (!base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='contents'"))
			$this->install();

		base::instance()->set("content.init", true);
		return true;
	}

	function install () {
		base::instance()->DB->exec("CREATE TABLE 'contents' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'name' TEXT, 'path' TEXT, 'content' TEXT)");
	}
}