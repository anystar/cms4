<?php

class file_manager extends prefab {

	static $upload_path = "uploads/";

	function __construct() {
		$f3 = base::instance();

		if ($f3->exists("config.file_upload_path"))
			upload_image::$upload_path = $f3->get("config.file_upload_path");

		if ($this->hasInit())
		{
			$this->routes(base::instance());

			// TODO: Load up other things

			if (admin::$signed)
				$this->admin_routes($f3);
		}
	}

	function routes($f3) {

		// TODO: Insert routes for this module

	}

	function admin_routes($f3) {
		
		// TODO: Insert admin related routes for this module

		$f3->route("POST /file_upload", function ($f3, $post) {

			$file = $f3->FILES["upload"]["tmp_name"];
			$new_name = $f3->FILES["upload"]["name"];
			$new_name = str_replace(' ', '_', $new_name);
			$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

			move_uploaded_file($file, upload_image::$upload_path . $new_name);


			echo json_encode([
					"uploaded" => 1,
					"fileName" => $new_name,
					"url" => $f3->BASE . "/" . upload_image::$upload_path . $new_name
				]);

			die;
		});

		$f3->route(["GET /browse_files", "GET /browse_files/*"], function ($f3) {
			
			$f3->set('UI', $f3->CMS."adminUI/");

			$path = 'uploads/';

			foreach (new DirectoryIterator($path) as $fileInfo) {

			    if ($fileInfo->isDot()) continue; // Excludes linux . and .. directories
			    
			    $filename = $fileInfo->getFilename();
			    if ($filename == ".htaccess") continue; // Ignore htaccess files

			    $f["name"] = $filename;
			    $f["is_folder"] = $fileInfo->isDir();

			    $files[] = $f;
			}

			$f3->set("file_list", $files);

			echo Template::instance()->render("file_manager/file_browser.html");
		});

	}

	static function hasInit() {
		return true;
	}

	static function generate() {
		// $db = base::instance()->DB;

		// //TODO: Insert sql to generate table structures
		// $db->exec("");
	}

	static function admin_render() {

		// //TODO: Create html files for admin display and generation
		// if ($this::instance()->hasInit())
		// 	echo Template::instance()->render("module_name/module.html");
		// else
		// 	echo Template::instance()->render("module_name/module.html");
	}
}