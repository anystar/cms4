<?php


class gallery extends prefab {

	static $upload_path = "uploads/gallery/";
	static $thumb_path = "uploads/gallery/thumbs/";

	public function __construct() {

		$f3 = base::instance();

		$default = $f3->exists("CONFIG[gallery.page]") ? $f3->get("CONFIG[gallery.page]") : "/gallery";
		$f3->set("CONFIG[gallery.page]", $default);

		if ($f3->exists("config.gallery_upload_path"))
			gallery::$upload_path = $f3->get("config.gallery_upload_path");

		if ($f3->exists("config.gallery_thumb_path"))
			gallery::$thumb_path = $f3->get("config.gallery_thumb_path");

		if ($this->hasInit()) {

			$pageToLoadOn = $f3->get("CONFIG[contact.page]");

			if ($pageToLoadOn == $f3->PATH || $pageToLoadOn == "all")
				$this->retreiveContent($page[1]);

			base::instance()->set("gallery_path", gallery::$upload_path);
			base::instance()->set("gallery_thumb_path", gallery::$thumb_path);
		}

		if (admin::$signed)
			$this->admin_routes($f3);

	}

	public function admin_routes($f3) {
		$f3->route('GET /admin/gallery', "gallery::admin_render");
		$f3->route('GET /admin/gallery/generate', "gallery::generate");

		$f3->route('GET /admin/gallery/js/dropzone.js', function ($f3) {
			echo View::instance()->render("gallery/js/dropzone.js", "text/javascript");
			exit;
		});

		$f3->route('GET /admin/gallery/css/dropzone.css', function ($f3) {
			echo View::instance()->render("gallery/css/dropzone.css", "text/css");
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

		$f3->route('GET /admin/gallery/delete/@id', function ($f3, $params) {
			gallery::delete($f3, $params["id"]);
		});
	}

	static function delete($f3, $id) {
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

		if (move_uploaded_file($f3->FILES["file"]["tmp_name"], gallery::$upload_path . $new_name))
		{
			$thumb_size = $f3->DB->exec("SELECT value FROM settings WHERE setting='gallery-default-thumb-size'")[0]['value'];
			$thumb_size = explode("x", $thumb_size);

			// Move success
			$img = new Image(gallery::$upload_path . $new_name, false, getcwd()."/");

			$img->resize($thumb_size[0], $thumb_size[1]);

			imagejpeg($img->data(), gallery::$thumb_path."thumb_".$new_name);

			$f3->DB->exec("INSERT INTO gallery (filename, `order`, caption, section, thumb) 
						   VALUES (?, ?, ?, ?, ?)", [$new_name, 0, '', '', "thumb_".$new_name]);
		}
	}

	static function retreiveContent($section="") {
		
		$db = base::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery WHERE section=?", ($section) ? $section : "");

		base::instance()->set("gallery_images", $result);
	}

	static function retreiveAllContent() {
		$db = base::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery");
		
		base::instance()->set("gallery_images", $result);
	}

	static function hasInit()
	{	
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
	
		base::instance()->mock('GET /admin/gallery');
	}

	static function admin_render() {

		if (gallery::hasInit())
		{
			gallery::retreiveAllContent();
			echo Template::instance()->render("gallery/gallery.html");
		}
		else
			echo Template::instance()->render("gallery/nogallery.html");
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
}