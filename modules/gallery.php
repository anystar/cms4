<?php


class gallery extends prefab {

	private $namespace;
	private $route = "/gallery";

	private $upload_path = "uploads/gallery";
	private $thumb_path = "uploads/gallery/thumbs";
	private $thumb_prefix = "thumb_";

	function __construct($namespace) {
		$this->namespace = $namespace;
		$f3 = base::instance();

		// Set an upload path from settings or leave as default
		if ($value = setting($namespace."_upload_path"))
			$this->upload_path = $value;
	
		// Set a thumb path from settings or leave as default
		if ($value = setting($namespace."_thumb_path"))
			$this->thumb_path = $value;

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
		$f3->route('GET /admin/gallery', function ($f3) {

			$this->retreiveContent();
			$this->retreiveSettings();

			$temp_hive = $f3->hive();
			$temp_hive["gallery"] = $f3->get($this->namespace);

			echo Template::instance()->render("/gallery/gallery.html", "text/html", $temp_hive);
		});

		$f3->route('POST /admin/gallery/generate', function () {
			$this->generate($f3);
		});

		$f3->route('GET /admin/gallery/css/gallery.css', function ($f3) {
			echo View::instance()->render("gallery/css/gallery.css", "text/css");
			exit;
		});

		$f3->route('POST /admin/gallery/dropzone', function ($f3) {
			$this->upload($f3);
			exit;
		});


		$f3->route('GET /admin/gallery/delete/@id [ajax]', function ($f3, $params) {
			$this->jaxDelete($f3, $params["id"]);			
		});

		$f3->route('GET /admin/gallery/delete/@id', function ($f3, $params) {
			$this->elete($f3, $params["id"]);
		});

		$f3->route('POST /admin/gallery/upload_settings', function ($f3) {
			$this->pdate_settings($f3->POST);
		});
	}

	function ajaxDelete($f3, $id) {
		$db = $f3->DB;
		$result = $db->exec("SELECT * FROM gallery WHERE id=?", $id)[0];

		if (file_exists($this->upload_path . $result["filename"]))
			unlink($this->upload_path . $result["filename"]);

		if (file_exists($this->thumb_path . $result["thumb"]))
			unlink($this->thumb_path . $result["thumb"]);

		$db->exec("DELETE FROM gallery WHERE id=?", $id);

		echo true;
		exit;
	}

	function delete($f3, $id, $ajax=false) {
		$db = $f3->DB;
		$result = $db->exec("SELECT * FROM gallery WHERE id=?", $id)[0];

		if (!$result)
		{
			$f3->mock("GET /admin/gallery");
			return;
		}

		if (file_exists($this->upload_path . $result["filename"]))
			unlink($this->upload_path . $result["filename"]);
		else
			$f3->mock("GET /admin/gallery");

		if (file_exists($this->thumb_path . $result["thumb"]))
			unlink($this->thumb_path . $result["thumb"]);
		else
			$f3->mock("GET /admin/gallery");

		$db->exec("DELETE FROM gallery WHERE id=?", $id);

		$f3->mock("GET /admin/gallery");
	}

	function upload($f3) {

		$new_name = str_replace(' ', '_', $f3->FILES["file"]["name"]);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

		// Something happened and couldn't move image file..
		if (!move_uploaded_file($f3->FILES["file"]["tmp_name"], $this->upload_path.$new_name))
			return;

		// Get settings for image size
		$image_size = $f3->DB->exec("SELECT value FROM settings WHERE setting='gallery-default-image-size'")[0]['value'];
		$image_size = explode("x", $image_size);

		// Resize image
		if ($image_size[0] > 0 && $image_size[1] > 0)
			$this->resize_image($this->upload_path.$new_name, $image_size[0], $image_size[1], $this->upload_path.$new_name);

		// Get settings for thumb nail
		$thumb_size = $f3->DB->exec("SELECT value FROM settings WHERE setting='gallery-default-thumb-size'")[0]['value'];
		$thumb_size = explode("x", $thumb_size);

		// Resize to thumbnail
		if ($thumb_size[0] > 0 && $thumb_size[1] > 0)
			$this->resize_image($this->upload_path.$new_name ,$thumb_size[0], $thumb_size[1], $this->thumb_path.$this->thumb_prefix.$new_name);
		
		// If thumbnail settings are not set just resize as image size
		else if ($image_size[0] > 0 && $image_size[1] > 0)
			$this->resize_image($this->upload_path.$new_name ,$image_size[0], $image_size[1], $this->thumb_path.$this->thumb_prefix.$new_name);

		// If image settings not set lets just copy the raw file
		else
			copy($this->upload_path.$new_name, $this->thumb_path.$this->thumb_prefix.$new_name);


		// Record into database
		$f3->DB->exec("INSERT INTO gallery (filename, `order`, caption, section, thumb) 
					   VALUES (?, ?, ?, ?, ?)", [$new_name, 0, '', '', "thumb_".$new_name]);
	}

	function resize_image ($image, $x, $y, $save_as) {

		// Pull image off the disk into memory
		$temp_image = new Image($image, false, getcwd()."/");
	
		// Resize image using F3's image plugin
		$temp_image->resize($x, $y, false, false);
		
		// Save image	
		imagejpeg($temp_image->data(), $save_as);
	}

	function update_settings($data) {

		$db = base::instance()->DB;

		$setTo = $data["thumb_size_x"] . "x" . $data["thumb_size_y"];
		$db->exec("UPDATE settings SET value=? WHERE setting='gallery-default-thumb-size'", $setTo);

		$setTo = $data["image_size_x"] . "x" . $data["image_size_y"];
		$db->exec("UPDATE settings SET value=? WHERE setting='gallery-default-image-size'", $setTo);

		base::instance()->mock("GET /admin/gallery");
	}

	function retreiveContent() {
		$db = base::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery");

		// Lets do some file validation..
		foreach ($result as $key=>$image)
		{
			// If the file does not exist lets remove it from the DB
			if (!file_exists($this->upload_path."/".$image["filename"]))
			{
				$db->exec("DELETE FROM gallery WHERE id=?", $image["id"]);
				unset($result[$key]);
			}
		}
		
		base::instance()->set("gallery_images", $result);
	}


	function retreiveSettings () {
		$f3 = base::instance();
		$db = base::instance()->get("DB");

		$result = $db->exec("SELECT value FROM settings WHERE setting=?", "gallery-default-thumb-size")[0]["value"];
		$result = explode("x", $result);

		$f3->set("gallery.settings.thumb_size_x", $result[0]);
		$f3->set("gallery.settings.thumb_size_y", $result[1]);

		$result = $db->exec("SELECT value FROM settings WHERE setting=?", "gallery-default-image-size")[0]["value"];
		$result = explode("x", $result);

		$f3->set("gallery.settings.image_size_x", $result[0]);
		$f3->set("gallery.settings.image_size_y", $result[1]);

		$f3->set("max_upload_size", file_upload_max_size());
	}

	function hasInit()
	{	

		if (!extension_loaded("gd")) return false;

		$db = base::instance()->get("DB");
		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='gallery'");

		if (empty($result)) 
			return false;

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->upload_path))
			return false;

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->thumb_path))
			return false;

		if (admin::$signed) {
			// Ensure settings are inserted
			$result = $db->exec("SELECT setting FROM settings WHERE setting='gallery-default-thumb-size'");
			if (!$result) $db->exec("INSERT INTO settings VALUES ('gallery-default-thumb-size', '128x128')");

			$result = $db->exec("SELECT setting FROM settings WHERE setting='gallery-default-image-size'");
			if (!$result) $db->exec("INSERT INTO settings VALUES ('gallery-default-image-size', '512x512')");
		}

		return true;
	}

	function generate() {

		if (!base::instance()->webmaster) return;
		$db = base::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS 'gallery' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'filename' TEXT, 'order' INTEGER, 'caption' TEXT,'section' TEXT)");

		// Make sure uploads folder exists
		if (!is_dir(getcwd()."/uploads"))
			mkdir(getcwd()."/uploads");

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->upload_path))
			mkdir(getcwd()."/".$this->upload_path);

		// Create gallery folder
		if (!is_dir(getcwd()."/".$this->thumb_path))
			mkdir(getcwd()."/".$this->thumb_path);

		$this->hasInit();

		base::instance()->reroute('/admin/gallery');
	}
}