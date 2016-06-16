<?php


class gallery extends prefab {

	public function __construct() {

		$f3 = base::instance();

		if ($this->hasInit()) {
			$page = $f3->PATH;
			$page = ($page!="/") ? trim($page, "/") : "index";

			$page = explode("/", $page);

			$this->retreiveContent($page[1]);
		}

		if (admin::$signed)
			$this->admin_routes($f3);
	}

	public function admin_routes($f3) {
		$f3->route('GET /admin/gallery', "gallery::admin_render");
		$f3->route('GET /admin/gallery/generate', "gallery::generate");
	}

	static function retreiveContent($section="") {
		
		$db = f3::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery WHERE section=?", ($section) ? $section : "");

		f3::instance()->set("gallery_images", $result);
	}

	static function hasInit()
	{	
		$db = f3::instance()->get("DB");
		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='gallery'");
		
		if (empty($result)) 
			return false;

		return true;
	}

	static function generate() {
		$db = f3::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS 'gallery' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'filename' TEXT, 'order' INTEGER, 'caption' INTEGER,'section' TEXT)");
	
		f3::instance()->mock('GET /admin/gallery');
	}

	static function admin_render() {
		if (gallery::hasInit())
			echo Template::instance()->render("gallery/gallery.html");
		else
			echo Template::instance()->render("gallery/nogallery.html");
	}
}