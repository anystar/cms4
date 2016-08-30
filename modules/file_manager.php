<?php

class file_manager extends prefab {

	static $upload_path = "uploads/";
	static $image_upload_path = "uploads/images";

	function __construct() {
		$f3 = base::instance();

		if ($f3->exists("SETTINGS.file_upload_path"))
			file_manager::$upload_path = $f3->get("SETTINGS.file_upload_path");
		else
			$f3->set("SETTINGS.file_upload_path", file_manager::$upload_path);

		if ($f3->exists("SETTINGS.image_upload_path"))
			file_manager::$upload_path = $f3->get("SETTINGS.image_upload_path");
		else
			$f3->set("SETTINGS.image_upload_path", file_manager::$upload_path);

		if ($this->hasInit())
		{
			$this->routes(base::instance());

			// TODO: Load up other things

			if (admin::$signed)
				$this->admin_routes($f3);
		}
		else
		{
			$f3->route("GET /admin/file_manager", function ($f3) {
				echo Template::instance()->render("/file_manager/init.html");
			});

			$f3->route("POST /admin/file_manager/init", function ($f3) {
				
				if (!file_exists(getcwd()."/".file_manager::$upload_path))
					mkdir(getcwd()."/".file_manager::$upload_path);

				file_put_contents(getcwd()."/".file_manager::$upload_path."/.htaccess", "RewriteEngine off");

				$f3->reroute("/admin/file_manager");
			});
		}
	}

	function routes($f3) {


	}

	function admin_routes($f3) {
		
		$f3->route("GET /admin/file_manager", function ($f3) {
			$f3->set("max_upload_size", file_manager::file_upload_max_size());

			echo Template::instance()->render("/file_manager/file_manager.html");
		});

		$f3->route("POST /admin/file_manager/dropzone", function ($f3) {
			
			$uploadTo = $f3->POST["location"];

			$file = $f3->FILES["file"]["tmp_name"];
			$new_name = $f3->FILES["file"]["name"];
			$new_name = str_replace(' ', '_', $new_name);
			$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

			echo  $uploadTo . "/" . $new_name;
			move_uploaded_file($file, $uploadTo . "/" . $new_name);
			exit;
		});

		$f3->route("POST /admin/file_manager/image_upload", function ($f3) {
			$response = file_manager::upload_image($f3, $f3->FILES["upload"]["tmp_name"], $f3->FILES["upload"]["name"]);

			echo json_encode([
					"uploaded" => 1,
					"fileName" => $response,
					"url" => $f3->BASE . "/" . file_manager::$image_upload_path . "/" . $response
				]);

			exit;
		});

		$f3->route("POST /admin/file_manager/image_upload_via_dialog", function ($f3) {				
			$response = file_manager::upload_image($f3, $f3->FILES["upload"]["tmp_name"], $f3->FILES["upload"]["name"]);
			
			$path = file_manager::$image_upload_path . "/" . $response;
			$ck_func_number = $f3->GET["CKEditorFuncNum"];
			echo "<script type='text/javascript'>window.parent.CKEDITOR.tools.callFunction('$ck_func_number', '$path', 'File uploaded successfully');</script>";
			exit;
		});

		$f3->route(["GET /admin/file_manager/browse_files", "GET /browse_files/*"], function ($f3) {
			echo Template::instance()->render("file_manager/file_browser.html");
		});

		$f3->route("GET /admin/file_manager/file_list", "file_manager::file_list");

		$f3->route("GET /admin/file_manager/style.css", function ($f3) {
			echo Template::instance()->render("file_manager/style.css", "text/css");
		});

		$f3->route("GET /admin/file_manager/script.js", function ($f3) {
			echo Template::instance()->render("file_manager/script.js", "text/javascript");
		});

		$f3->route("POST /admin/file_manager/delete", function ($f3) {

			$file = $f3->POST["file"];

			// remove base path if it exsits
			$file = ltrim($file, $f3->BASE);

			if (file_exists(getcwd()."/".$file)) {
				unlink(getcwd()."/".$file);
				echo 1;
			}

			die;
		});

	}

	static function upload_image($f3, $temp_path, $temp_name) {

		$file = $temp_path;
		$new_name = $temp_name;
		$new_name = str_replace(' ', '_', $new_name);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

		move_uploaded_file($file, file_manager::$image_upload_path . "/" . $new_name);

		return $new_name;
	}

	static function file_list() {
		$files = array();

		$response = file_manager::scan(rtrim(file_manager::$upload_path, "/"));

		header('Content-type: application/json');

		echo json_encode(array(
			"name" => basename(file_manager::$upload_path),
			"type" => "folder",
			"path" => file_manager::$upload_path,
			"items" => $response
		));
	}

	static function scan($dir){

		$files = array();

		// Is there actually such a folder/file?
		if(file_exists($dir)){

			foreach(scandir($dir) as $f) {
			
				if(!$f || $f[0] == '.') {
					continue; // Ignore hidden files
				}

				// Is it a file or folder?
				if(is_dir(getcwd()."/".$dir."/".$f)) {
					$files[] = array(
						"name" => $f,
						"type" => "folder",
						"path" => $dir . '/' . $f,
						"items" => file_manager::scan($dir . '/' . $f) // Recursively get the contents of the folder
					);
				} else {


					
					$files[] = array(
						"name" => $f,
						"type" => "file",
						"path" => base::instance()->BASE."/".$dir . "/" . $f,
						"size" => filesize($dir . "/" . $f) // Gets the size of this file
					);
				}
			}
		
		}

		return $files;
	}

	static function hasInit() {
		$upload_path = getcwd()."/".file_manager::$upload_path;

		if (!file_exists($upload_path))
			return false;

		if (!file_exists($upload_path."/.htaccess"))
			file_put_contents($upload_path."/.htaccess", "RewriteEngine off");

		base::instance()->file_manager["init"] = true;
		return true;
	}


	// Drupal has this implemented fairly elegantly:
	// http://stackoverflow.com/questions/13076480/php-get-actual-maximum-upload-size
	static function file_upload_max_size () {
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