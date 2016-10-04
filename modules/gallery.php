<?php


class gallery extends prefab {

	private $namespace;
	private $route = "/gallery";

	private $upload_path;
	private $thumb_path;
	private $thumb_prefix = "thumb_";

	function __construct($namespace) {
		$this->namespace = $namespace;
		$f3 = base::instance();

		// Load asset routes
		$this->asset_routes($f3);

		// Set an upload path from settings or leave as default
		if ($value = setting($namespace."_upload_path"))
			$this->upload_path = $value;
		else
			$this->upload_path = "uploads/{$namespace}";

		// Set a thumb path from settings or leave as default
		if ($value = setting($namespace."_thumb_path"))
			$this->thumb_path = $value;
		else
			$this->thumb_path = "uploads/{$namespace}/thumbs/";

		// Which route to load on
		if ($value = setting($namespace."_route"))
			$this->route = $value;

		// Retreive contents based on route
		if ($this->route == $f3->PATH || $this->route == "all")
			$this->retreiveContent();

		// Load admin routes if signed in
		if (admin::$signed)
			$this->admin_routes($f3);
	}

	function admin_routes($f3) {

		$f3->route("GET /admin/{$this->namespace}", function ($f3) {

			$this->retreiveContent();
			$this->retreiveSettings();

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

		$f3->route("GET /admin/{$this->namespace}/install", function ($f3) {
			$this->install();

			$f3->reroute("/admin/{$this->namespace}");
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

		///
		// Determine the file paths
		///

		// Temp image path
		$temp_image = $f3->FILES["file"]["tmp_name"];

		// New name
		$new_name = str_replace(' ', '_', $f3->FILES["file"]["name"]);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

		// Where to save the full image too
		$save_to_full = getcwd()."/".$this->upload_path."/".$new_name;

		// Where to save the thumb too
		$save_to_thumb = getcwd()."/".$this->thumb_path."/".$this->thumb_prefix.$new_name;

		// Get settings for image size
		$image_size = setting($this->namespace."_default_image_size");
		$thumb_size = setting($this->namespace."_default_thumb_size");

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

	function update_settings($data) {

		$db = base::instance()->DB;

		$setTo = $data["thumb_size_x"] . "x" . $data["thumb_size_y"];
		setting($this->namespace."_default_thumb_size", $setTo);

		$setTo = $data["image_size_x"] . "x" . $data["image_size_y"];
		setting($this->namespace."_default_image_size", $setTo);

		base::instance()->reroute("/admin/".$this->namespace);
	}

	function retreiveContent() {
		$f3 = base::instance();
		$db = $f3->DB;

		$result = $db->exec("SELECT * FROM {$this->namespace}");

		foreach ($result as $key=>$image)
		{
			$result[$key]["url"] = $f3->BASE ."/". rtrim($this->upload_path, "/") . "/" . $image["filename"];
			$result[$key]["thumb"] = $f3->BASE. "/" . rtrim($this->thumb_path, "/") . "/". $this->thumb_prefix.$image["filename"];

			// If the file does not exist lets remove it from the DB
			if (!file_exists($this->upload_path."/".$image["filename"]))
			{
				$db->exec("DELETE FROM {$this->namespace} WHERE id=?", $image["id"]);
				unset($result[$key]);
			}
		}

		base::instance()->set("{$this->namespace}.images", $result);
	}


	function retreiveSettings () {
		$f3 = base::instance();
		$db = base::instance()->get("DB");

		$result = setting($this->namespace."_default_thumb_size");
		$result = explode("x", $result);

		$f3->set($this->namespace.".settings.thumb_size_x", $result[0]);
		$f3->set($this->namespace.".settings.thumb_size_y", $result[1]);

		$result = setting($this->namespace."_default_image_size");
		$result = explode("x", $result);

		$f3->set($this->namespace.".settings.image_size_x", $result[0]);
		$f3->set($this->namespace.".settings.image_size_y", $result[1]);

		$f3->set("max_upload_size", file_upload_max_size());
	}

	function installCheck()
	{	

		if (!extension_loaded("gd")) return false;

		$db = base::instance()->get("DB");
		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name=?", $this->namespace);

		if (empty($result)) 
			return false;

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->upload_path))
			return false;

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->thumb_path))
			return false;

		return true;
	}

	function install() {

		if ($this->installCheck())
			return;

		$db = base::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS '{$this->namespace}' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'filename' TEXT, 'order' INTEGER, 'caption' TEXT)");

		// Make sure uploads folder exists
		if (!is_dir(getcwd()."/uploads"))
			mkdir(getcwd()."/uploads");

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->upload_path))
			mkdir(getcwd()."/".$this->upload_path);

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->thumb_path))
			mkdir(getcwd()."/".$this->thumb_path);

		setting($this->namespace."_default_image_size", "1000x1000", false);
		setting($this->namespace."_default_thumb_size", "500x500", false);
	}
}