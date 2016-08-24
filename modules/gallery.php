<?php


class gallery extends prefab {

	static $upload_path = "uploads/gallery/";
	static $thumb_path = "uploads/gallery/thumbs/";
	static $thumb_prefix = "thumb_";

	public function __construct() {

		$f3 = base::instance();

		$default = $f3->exists("SETTINGS[gallery.page]") ? $f3->get("SETTINGS[gallery.page]") : "/gallery";
		$f3->set("SETTINGS[gallery.page]", $default);

		if ($f3->exists("SETTINGS.gallery_upload_path"))
			gallery::$upload_path = $f3->get("SETTINGS.gallery_upload_path");

		if ($f3->exists("SETTINGS.gallery_thumb_path"))
			gallery::$thumb_path = $f3->get("SETTINGS.gallery_thumb_path");

		if ($this->hasInit()) {

			$pageToLoadOn = $f3->get("SETTINGS[gallery.page]");

			if ($pageToLoadOn == $f3->PATH || $pageToLoadOn == "all" || $pageToLoadOn == $f3->POST["return"])
				$this->retreiveContent($page[1]);

			base::instance()->set("gallery_path", gallery::$upload_path);
			base::instance()->set("gallery_thumb_path", gallery::$thumb_path);
		}

		if (admin::$signed)
			$this->admin_routes($f3);

	}

	public function admin_routes($f3) {
		$f3->route('GET /admin/gallery', "gallery::admin_render");
		$f3->route('POST /admin/gallery/generate', "gallery::generate");

		$f3->route('GET /admin/gallery/js/dropzone.js', function ($f3) {
			echo View::instance()->render("gallery/js/dropzone.js", "text/javascript");
			exit;
		});

		$f3->route('GET /admin/gallery/css/dropzone.css', function ($f3) {
			echo View::instance()->render("gallery/css/dropzone.css", "text/css");
			exit;
		});

		$f3->route('GET /admin/gallery/css/lity.min.css', function ($f3) {
			echo View::instance()->render("gallery/css/lity.min.css", "text/css");
			exit;
		});

		$f3->route('GET /admin/gallery/js/lity.min.js', function ($f3) {
			echo View::instance()->render("gallery/js/lity.min.js", "text/javascript");
			exit;
		});

		$f3->route('GET /admin/gallery/css/gallery.css', function ($f3) {
			echo View::instance()->render("gallery/css/gallery.css", "text/css");
			exit;
		});

		$f3->route('POST /admin/gallery/dropzone', function ($f3) {
			gallery::upload($f3);
			exit;
		});


		$f3->route('GET /admin/gallery/delete/@id [ajax]', function ($f3, $params) {
			gallery::ajaxDelete($f3, $params["id"]);			
		});

		$f3->route('GET /admin/gallery/delete/@id', function ($f3, $params) {
			gallery::delete($f3, $params["id"]);
		});

		$f3->route('POST /admin/gallery/upload_settings', function ($f3) {
			gallery::update_settings($f3->POST);
		});
	}

	static function ajaxDelete($f3, $id) {
		$db = $f3->DB;
		$result = $db->exec("SELECT * FROM gallery WHERE id=?", $id)[0];

		if (file_exists(gallery::$upload_path . $result["filename"]))
			unlink(gallery::$upload_path . $result["filename"]);

		if (file_exists(gallery::$thumb_path . $result["thumb"]))
			unlink(gallery::$thumb_path . $result["thumb"]);

		$db->exec("DELETE FROM gallery WHERE id=?", $id);

		echo true;
		exit;
	}

	static function delete($f3, $id, $ajax=false) {
		$db = $f3->DB;
		$result = $db->exec("SELECT * FROM gallery WHERE id=?", $id)[0];

		if (!$result)
		{
			$f3->mock("GET /admin/gallery");
			return;
		}

		if (file_exists(gallery::$upload_path . $result["filename"]))
			unlink(gallery::$upload_path . $result["filename"]);
		else
			$f3->mock("GET /admin/gallery");

		if (file_exists(gallery::$thumb_path . $result["thumb"]))
			unlink(gallery::$thumb_path . $result["thumb"]);
		else
			$f3->mock("GET /admin/gallery");

		$db->exec("DELETE FROM gallery WHERE id=?", $id);

		$f3->mock("GET /admin/gallery");
	}

	static function upload($f3) {

		$new_name = str_replace(' ', '_', $f3->FILES["file"]["name"]);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

		// Something happened and couldn't move image file..
		if (!move_uploaded_file($f3->FILES["file"]["tmp_name"], gallery::$upload_path.$new_name))
			return;

		// Get settings for image size
		$image_size = $f3->DB->exec("SELECT value FROM settings WHERE setting='gallery-default-image-size'")[0]['value'];
		$image_size = explode("x", $image_size);

		// Resize image
		if ($image_size[0] > 0 && $image_size[1] > 0)
			gallery::resize_image(gallery::$upload_path.$new_name, $image_size[0], $image_size[1], gallery::$upload_path.$new_name);

		// Get settings for thumb nail
		$thumb_size = $f3->DB->exec("SELECT value FROM settings WHERE setting='gallery-default-thumb-size'")[0]['value'];
		$thumb_size = explode("x", $thumb_size);

		// Resize to thumbnail
		if ($thumb_size[0] > 0 && $thumb_size[1] > 0)
			gallery::resize_image(gallery::$upload_path.$new_name ,$thumb_size[0], $thumb_size[1], gallery::$thumb_path.gallery::$thumb_prefix.$new_name);
		
		// If thumbnail settings are not set just resize as image size
		else if ($image_size[0] > 0 && $image_size[1] > 0)
			gallery::resize_image(gallery::$upload_path.$new_name ,$image_size[0], $image_size[1], gallery::$thumb_path.gallery::$thumb_prefix.$new_name);

		// If image settings not set lets just copy the raw file
		else
			copy(gallery::$upload_path.$new_name, gallery::$thumb_path.gallery::$thumb_prefix.$new_name);


		// Record into database
		$f3->DB->exec("INSERT INTO gallery (filename, `order`, caption, section, thumb) 
					   VALUES (?, ?, ?, ?, ?)", [$new_name, 0, '', '', "thumb_".$new_name]);
	}

	static function resize_image ($image, $x, $y, $save_as) {

		// Pull image off the disk into memory
		$temp_image = new Image($image, false, getcwd()."/");
	
		// Resize image using F3's image plugin
		$temp_image->resize($x, $y, false, false);
		
		// Save image	
		imagejpeg($temp_image->data(), $save_as);
	}

	static function update_settings($data) {

		$db = base::instance()->DB;

		$setTo = $data["thumb_size_x"] . "x" . $data["thumb_size_y"];
		$db->exec("UPDATE settings SET value=? WHERE setting='gallery-default-thumb-size'", $setTo);

		$setTo = $data["image_size_x"] . "x" . $data["image_size_y"];
		$db->exec("UPDATE settings SET value=? WHERE setting='gallery-default-image-size'", $setTo);

		base::instance()->mock("GET /admin/gallery");
	}

	static function retreiveContent($section="") {
		
		$db = base::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery WHERE section=?", ($section) ? $section : "");

		base::instance()->set("gallery_images", $result);
	}

	static function retreiveAllContent() {
		$db = base::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery");

		// Lets do some file validation..
		foreach ($result as $key=>$image)
		{
			// If the file does not exist. Lets remove this from the DB
			if (!file_exists(gallery::$upload_path.$image["filename"]))
			{
				$db->exec("DELETE FROM gallery WHERE id=?", $image["id"]);
				unset($result[$key]);
			}
		}
		
		base::instance()->set("gallery_images", $result);
	}

	static function retreiveSettings () {
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

		$f3->set("max_upload_size", gallery::file_upload_max_size());
	}

	static function hasInit()
	{	
		if (!extension_loaded("gd")) return false;

		$db = base::instance()->get("DB");
		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='gallery'");

		if (empty($result)) 
			return false;


		$upload_path = gallery::$upload_path;
		if (!file_exists($upload_path))
		{
			// Attempt to make directory
			if (mkdir($upload_path))
				return;

			die("<strong>Fatel Error in gallery module:</strong> Please create upload folder for uploading to work.<br>Upload folder is: ".$upload_path);
		}

		$thumb_path = gallery::$thumb_path;
		if (!file_exists($thumb_path))
		{
			// Attempt to make directory
			if (mkdir($thumb_path))
				return;

			die("<strong>Fatel Error in gallery module:</strong> Please create thumb upload folder for uploading to work.<br>Upload folder is: ".$upload_path);
		}

		if (admin::$signed) {
			// Ensure settings are inserted
			$result = $db->exec("SELECT setting FROM settings WHERE setting='gallery-default-thumb-size'");
			if (!$result) $db->exec("INSERT INTO settings VALUES ('gallery-default-thumb-size', '128x128')");

			$result = $db->exec("SELECT setting FROM settings WHERE setting='gallery-default-image-size'");
			if (!$result) $db->exec("INSERT INTO settings VALUES ('gallery-default-image-size', '512x512')");
		}

		gallery::patch_columns();

		return true;
	}

	static function generate() {

		$db = base::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS 'gallery' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'filename' TEXT, 'order' INTEGER, 'caption' TEXT,'section' TEXT)");

		gallery::hasInit();

		base::instance()->mock('GET /admin/gallery');
	}

	static function admin_render() {
		$f3 = base::instance();

		if (gallery::hasInit())
		{
			gallery::retreiveAllContent();
			gallery::retreiveSettings();
			echo Template::instance()->render("gallery/gallery.html");
		}
		else
		{

			$f3->gd_not_loaded = false;
			if (!extension_loaded("gd"))
				$f3->gd_not_loaded = true;

			echo Template::instance()->render("gallery/nogallery.html");
		}
	}

	static function patch_columns ()
	{
		$result = base::instance()->DB->exec("PRAGMA table_info(gallery)");

		$patch_thumb=true;
		//Patch to ensure type column is added.
		foreach ($result as $r) {
			if ($r["name"] == "thumb")
				$patch_thumb = false;
		}

		if ($patch_thumb)
			base::instance()->DB->exec("ALTER TABLE gallery ADD COLUMN thumb TEXT DEFAULT NULL");
	}


	// Drupal has this implemented fairly elegantly:
	// http://stackoverflow.com/questions/13076480/php-get-actual-maximum-upload-size
	static function file_upload_max_size() {
	  static $max_size = -1;

	  if ($max_size < 0) {
	    // Start with post_max_size.
	    $max_size = gallery::parse_size(ini_get('post_max_size'));

	    // If upload_max_size is less, then reduce. Except if upload_max_size is
	    // zero, which indicates no limit.
	    $upload_max = gallery::parse_size(ini_get('upload_max_filesize'));
	    if ($upload_max > 0 && $upload_max < $max_size) {
	      $max_size = $upload_max;
	    }
	  }
	  return $max_size;
	}

	static function parse_size($size) {
		if ($size == 0) return "unlimted";
		
		return $size;

	  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
	  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
	  if ($unit) {
	    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
	    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
	  }
	  else {
	    return round($size);
	  }
	}
}