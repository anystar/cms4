<?php

class file_manager extends prefab {

	static $upload_path = "uploads";

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


	}

	function admin_routes($f3) {
		
		// TODO: Insert admin related routes for this module

		$f3->route("POST /admin/file_upload", function ($f3, $post) {

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

		$f3->route(["GET /admin/browse_files", "GET /browse_files/*"], function ($f3) {
			
			$f3->set('UI', $f3->CMS."adminUI/");

			echo Template::instance()->render("file_manager/file_browser.html");
		});

		$f3->route("GET /admin/file_manager/file_list", "file_manager::file_list");

		$f3->route("GET /admin/file_manager/style.css", function ($f3) {
			echo Template::instance()->render("file_manager/style.css", "text/css");
		});

		$f3->route("GET /admin/file_manager/script.js", function ($f3) {
			echo Template::instance()->render("file_manager/script.js", "text/javascript");
		});

	}

	static function file_list() {
		$files = array();

		$response = file_manager::scan(file_manager::$upload_path);

		header('Content-type: application/json');

		echo json_encode(array(
			"name" => basename(file_manager::$upload_path),
			"type" => "folder",
			"path" => file_manager::$upload_path,
			"items" => $response
		));

		exit;
	}

	static function scan($dir){

		$files = array();

		// Is there actually such a folder/file?

		if(file_exists($dir)){
		
			foreach(scandir($dir) as $f) {
			
				if(!$f || $f[0] == '.') {
					continue; // Ignore hidden files
				}

				if(is_dir($dir . '/' . $f)) {

					// The path is a folder

					$files[] = array(
						"name" => $f,
						"type" => "folder",
						"path" => $dir . '/' . $f,
						"items" => file_manager::scan($dir . '/' . $f) // Recursively get the contents of the folder
					);
				}
				
				else {

					// It is a file

					$files[] = array(
						"name" => $f,
						"type" => "file",
						"path" => $dir . '/' . $f,
						"size" => filesize($dir . '/' . $f) // Gets the size of this file
					);
				}
			}
		
		}

		return $files;
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