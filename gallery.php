<?php


class gallery {

	static function load($section="") {
		
		$db = f3::instance()->DB;

		$result = $db->exec("SELECT * FROM gallery WHERE section=?", ($section) ? $section : "");

		f3::instance()->set("gallery_images", $result);
	}

	static function exists()
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

}