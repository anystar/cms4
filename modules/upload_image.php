<?php

class upload_image extends prefab {

	static $upload_path = "uploads/images/";

	function __construct() {
		$f3 = base::instance();

		if ($f3->exists("config.image_upload_path"))
			upload_image::$upload_path = $f3->get("config.image_upload_path");

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

		$f3->route("POST /admin/image_upload", function ($f3, $post) {

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

	}

	static function hasInit() {



		if (!file_exists(upload_image::$upload_path))
			die("<strong>Fatel Error in upload image module:</strong> Please create upload folder for image uploading to work.<br>Upload folder is: ".upload_image::$upload_path);

		if (!is_writable(upload_image::$upload_path))
			die("<strong>Fatel Error in upload image module:</strong> Please ensure upload folder is writable by PHP. Perhaps chmod g+w uploads.<br>Upload folder is: ".upload_image::$upload_path);

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