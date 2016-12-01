<?php

class gallery extends prefab {

	private $namespace;
	private $routes;

	private $upload_path;
	private $thumb_path;
	private $thumb_prefix = "thumb_";

	function __construct($namespace) {
		$this->namespace = $namespace;
		$this->routes = setting($namespace."_routes");

		$f3 = base::instance();

		// Load admin routes if signed in
		if (admin::$signed)
			$this->admin_routes($f3);

		if (!$this->install_check())
			return;

		// Load asset routes
		$this->asset_routes($f3);

		// Set an upload path from settings or leave as default
		if ($value = setting($namespace."_directory"))
			$this->upload_path = $value;

		// Set a thumb path from settings or leave as default
		if ($value = setting($namespace."_directory_thumb"))
			$this->thumb_path = $value;

		// Which route to load on
		if ($value = setting($namespace."_route"))
			$this->route = $value;

		// Retreive contents based on route
		if (isroute($this->routes))
			$this->retreiveContent();
	}

	function admin_routes($f3) {

		$f3->route("GET /admin/{$this->namespace}", function ($f3) {

			if (!$this->install_check())
				$f3->reroute("/admin/".$this->namespace."/setup");

			$this->retreiveContent();

			$f3->set("max_upload_size", file_upload_max_size());
			
			$f3->namespace = $this->namespace;
			$f3->module_name = base::instance()->DB->exec("SELECT name FROM licenses WHERE namespace=?", [$this->namespace])[0]["name"];

			$temp_hive = $f3->hive();
			$temp_hive["gallery"] = $f3->get($this->namespace);

			echo Template::instance()->render("/gallery/gallery.html", "text/html", $temp_hive);
		});

		$f3->route("POST /admin/{$this->namespace}/dropzone", function ($f3) {
			$this->upload($f3);
			exit;
		});

		$f3->route("POST /admin/{$this->namespace}/update-caption", function ($f3) {

			$f3->DB->exec("UPDATE {$this->namespace} SET caption=? WHERE id=?", [$f3->POST['caption'], $f3->POST['id']]);

		});

		$f3->route("GET /admin/{$this->namespace}/setup", function ($f3) {
			$f3->namespace = $this->namespace;

			// Get settings
			setting_use_namespace($this->namespace);
			$f3->gallery["routes"] = setting("routes");
			$f3->gallery["image_directory"] = setting("image_directory");
			$f3->gallery["thumb_directory"] = setting("thumb_directory");
			$f3->gallery["image_size"] = setting("image_size");
			$f3->gallery["thumb_size"] = setting("thumb_size");
			setting_clear_namespace();

			echo Template::instance()->render("/gallery/setup.html");
		});

		$f3->route("POST /admin/{$this->namespace}/setup", function ($f3) {
			
			// Set settings
			setting_use_namespace($this->namespace);
			setting("routes", $f3->POST["routes"]);
			setting("image_directory", $f3->POST["image_directory"]);
			setting("thumb_directory", $f3->POST["thumb_directory"]);
			setting("image_size", $f3->POST["image_size"]);
			setting("thumb_size", $f3->POST["thumb_size"]);
			setting_clear_namespace();

			$this->install();

			$f3->reroute("/admin/{$this->namespace}/setup");
		});

		$f3->route("GET /admin/{$this->namespace}/delete/@id [ajax]", function ($f3, $params) {
			$this->ajaxDelete($f3, $params["id"]);			
		});

		$f3->route("GET /admin/{$this->namespace}/delete/@id", function ($f3, $params) {
			$this->delete($f3, $params["id"]);
		});

		$f3->route("POST /admin/{$this->namespace}/upload_settings", function ($f3) {
			$this->update_settings($f3->POST);
			$f3->reroute("/admin/".$this->namespace);
		});
	}

	function asset_routes($f3) {
		
		if (!admin::$signed) return;

		$f3->route("GET /admin/gallery/css/gallery.css", function ($f3) {
			echo View::instance()->render("/gallery/css/gallery.css", "text/css");
		});
	}

	function ajaxDelete($f3, $id) {
		$db = $f3->DB;
		$result = $db->exec("SELECT * FROM {$this->namespace} WHERE id=?", $id)[0];

		if (file_exists(getcwd()."/".$this->upload_path . "/" . $result["filename"]))
			unlink(getcwd() . "/" . $this->upload_path . "/" . $result["filename"]);

		if (file_exists($this->thumb_path . "/" . $this->thumb_prefix.$result["filename"]))
			unlink(getcwd()."/".$this->thumb_path . "/" . $this->thumb_prefix.$result["filename"]);

		$db->exec("DELETE FROM {$this->namespace} WHERE id=?", $id);

		echo true;
		exit;
	}

	function delete($f3, $id, $ajax=false) {
		$db = $f3->DB;
		$result = $db->exec("SELECT * FROM {$this->namespace} WHERE id=?", $id)[0];

		if (!$result)
		{
			$f3->redirect("/admin/{$this->namespace}");
			return;
		}

		if (file_exists($this->upload_path . $result["filename"]))
			unlink($this->upload_path . $result["filename"]);
		else
			$f3->redirect("/admin/{$this->namespace}");

		if (file_exists($this->thumb_path . $result["thumb"]))
			unlink($this->thumb_path . $result["thumb"]);
		else
			$f3->redirect("/admin/{$this->namespace}");

		$db->exec("DELETE FROM {$this->namespace} WHERE id=?", $id);

		$f3->redirect("/admin/{$this->namespace}");
	}

	function upload($f3) {

		$upload_path = setting($this->namespace."_image_directory");
		$thumb_path = setting($this->namespace."_thumb_directory");

		// Temp image path
		$temp_image = $f3->FILES["file"]["tmp_name"];

		// New name
		$new_name = str_replace(' ', '_', $f3->FILES["file"]["name"]);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

		// Where to save the full image too
		$save_to_full = getcwd()."/".$upload_path."/".$new_name;

		// Where to save the thumb too
		$save_to_thumb = getcwd()."/".$thumb_path."/".$this->thumb_prefix.$new_name;

		// Get settings for image size
		$image_size = setting($this->namespace."_image_size");
		$thumb_size = setting($this->namespace."_thumb_size");

		$image_size = explode("x", $image_size);
		$thumb_size = explode("x", $thumb_size);

		// Resize full image and save
		if ($image_size[0] > 0 && $image_size[1] > 0)
			$this->resize_image($temp_image, $image_size[0], $image_size[1], $save_to_full);
		// If no image size set, just move image
		else
			copy($temp_image, $save_to_full);

		// Resize thumbnail image and save
		if ($thumb_size[0] > 0 && $thumb_size[1] > 0)
			$this->resize_image($temp_image ,$thumb_size[0], $thumb_size[1], $save_to_thumb);
		
		// If thumbnail settings are not set just resize as image size
		else if ($image_size[0] > 0 && $image_size[1] > 0)
			$this->resize_image($temp_image, $image_size[0], $image_size[1], $save_to_thumb);

		// If image settings not set lets just copy the raw file
		else
			copy($temp_image, $save_to_thumb);
	
		// Record into database
		$f3->DB->exec("INSERT INTO {$this->namespace} (filename, `order`, caption) 
					   VALUES (?, ?, ?)", [$new_name, 0, '']);

	}

	function resize_image ($image, $x, $y, $save_as) {

		// Pull image off the disk into memory
		$temp_image = new Image($image, false, "/");

		// Resize image using F3's image plugin
		$temp_image->resize($x, $y, false, false);
		
		// Save image	
		imagejpeg($temp_image->data(), $save_as);
	}

	function retreiveContent() {
		$f3 = base::instance();
		$db = $f3->DB;

		if (!$this->install_check())
			return;

		$upload_path = setting($this->namespace."_image_directory");
		$thumb_path = setting($this->namespace."_thumb_directory");

		$result = $db->exec("SELECT * FROM {$this->namespace}");

		foreach ($result as $key=>$image)
		{
			$result[$key]["url"] = $f3->BASE . rtrim($upload_path, "/") . "/" . $image["filename"];
			$result[$key]["thumb"] = $f3->BASE . rtrim($thumb_path, "/") . "/". $this->thumb_prefix.$image["filename"];

			// If the file does not exist lets remove it from the DB
			if (!file_exists(getcwd()."/".$upload_path."/".$image["filename"]))
			{
				$db->exec("DELETE FROM {$this->namespace} WHERE id=?", $image["id"]);
				unset($result[$key]);
			}
		}

		$f3->set("{$this->namespace}.images", $result);

		// Load html snippet
		if (isroute($this->routes)) {
			$temp_hive["gallery"] = $f3->get($this->namespace);
			$temp_hive["BASE"] = $f3->get("BASE");

			$snippet = Template::instance()->render("/gallery/gallery_snippet.html", "text/html", $temp_hive);
			$f3->set("{$this->namespace}.html", $snippet);
		}
	}

	function install() {

		$db = base::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS '{$this->namespace}' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'filename' TEXT, 'order' INTEGER, 'caption' TEXT)");

		$upload_path = setting($this->namespace."_image_directory");
		$thumb_path = setting($this->namespace."_thumb_directory");

		// Create gallery folder
		if ($this->upload_path)
			if (!is_dir(getcwd()."/".$upload_path))
				mkdir(getcwd()."/".$upload_path, 0755, true);

		// Create gallery folder
		if ($thumb_path)
			if (!is_dir(getcwd()."/".$thumb_path))
				mkdir(getcwd()."/".$thumb_path, 0755, true);
	}

	function install_check() {
		
		if (!extension_loaded("gd")) return false;

		$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->namespace}'");

		if (empty($result))
			return false;

		if (!setting("{$this->namespace}_image_directory"))
			return false;

		return true;
	}
}