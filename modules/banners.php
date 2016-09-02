<?php

class banners extends prefab {

	static $upload_path = "uploads/slider/";
	static $file_type = "jpeg";

	static $system;
	static $system_path;

	function __construct() {
		$f3 = base::instance();
		
		if ($f3->exists("SETTINGS.banners_upload_path"))
			banners::$upload_path = $f3->get("SETTINGS.banners_upload_path");

		if ($f3->exists("SETTINGS.banners_file_type"))
			banners::$file_type = $f3->get("SETTINGS.banners_file_type");

		if ($this->hasInit())
		{
			banners::retreive_content($f3);

			$this->routes(base::instance());
		}

		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {

		$f3->route("GET /banners/slider.css", function ($f3) {
			echo Template::instance()->render(banners::$upload_path."/slider.css", "text/css");
			exit();
		});

		$f3->route("GET /banners/slider.js", function ($f3) {
			echo Template::instance()->render(banners::$upload_path."/slider.js", "application/javascript");
			exit();
		});

	}

	function admin_routes($f3) {

		#######################################################
		################# Admin Panel Render ##################
		#######################################################
		// Render admin panel
		$f3->route('GET /admin/banners', 'banners::admin_render');

		// Render initilization page
		$f3->route('POST /admin/banners/init', 'banners::init');


		#######################################################
		########### Delete, Upload, Update Settings ###########
		#######################################################
		
		// Update system config of banner system
		$f3->route('POST /admin/banners/update_settings', function ($f3) {
			banners::update_settings($f3);
			$f3->reroute("/admin/banners");
		});

		// Update system config of banner system
		$f3->route('POST /admin/banners/update_settings [ajax]', function ($f3) {
			banners::update_settings($f3);
			exit();
		});

		// Upload via drop zone
		$f3->route('POST /admin/banners/dropzone', 'banners::upload');

		// Delete image
		$f3->route('GET /admin/banners/delete/@image', function ($f3, $params) {
			banners::delete_banner($f3, $params);

			$f3->reroute("/admin/banners");
		});

		// Delete image via ajax
		$f3->route('GET /admin/banners/delete/@image [ajax]', function ($f3, $params) {

			banners::delete_banner($f3, $params);

			echo $f3->get("banners.html");
			exit;
		});
	}

	static function retreive_content($f3) {

		// Get images URLs
		$dir = array_diff(scandir(banners::$upload_path), array('..', '.'));

		$order = json_decode(setting("banners_order"));

		// Validate order
		foreach ($dir as $img) {
			if ($img != "slider.html" && $img != "slider.css" && $img != "slider.js")
			{
				if (!in_array($img, $order)) {
					$order[] = $img;
				}
			}
		}

		foreach ($order as $img) {
			$f3->push("banners.images", [
				"url"=>banners::$upload_path.$img,
				"filename"=>$img
			]);
		}

		if (file_exists(banners::$upload_path."/slider.html"))
			$f3->banners["html"] = Template::instance()->render(banners::$upload_path."/slider.html");
	}

	static function update_settings($f3) {

		if (isset($f3->POST["html"]))
			file_put_contents(getcwd()."/".banners::$upload_path."/slider.html", $f3->POST["html"]);

		if (isset($f3->POST["css"]))
			file_put_contents(getcwd()."/".banners::$upload_path."/slider.css", $f3->POST["css"]);

		if (isset($f3->POST["js"]))
			file_put_contents(getcwd()."/".banners::$upload_path."/slider.js", $f3->POST["js"]);

		if (isset($f3->POST["width"]))
			setting("banners_width", $f3->POST["width"]);

		if (isset($f3->POST["height"]))
			setting("banners_height", $f3->POST["height"]);

		if (isset($f3->POST["banners_order"]))
			setting("banners_order", $f3->POST["banners_order"]);
	}

	static function hasInit() {

		$db = base::instance()->get("DB");
	
		if (!is_dir(getcwd()."/".banners::$upload_path))
			return false;

		base::instance()->banners["init"] = true;
		return true;
	}

	static function admin_render($f3) {
		$f3->set("max_upload_size", file_manager::file_upload_max_size());

		//TODO: Create html files for admin display and generation
		if (banners::hasInit())
		{
			if (admin::$signed) {
				$f3->banners["css"] = Template::instance()->render(banners::$upload_path."/slider.css");
				$f3->banners["js"] = Template::instance()->render(banners::$upload_path."/slider.js");
			}

			echo Template::instance()->render("/banners/banners.html");
		}
		else
			echo Template::instance()->render("/banners/init.html");
	}


	static function init($f3) {
		if (banners::hasInit()) return false;
		if (!$f3->webmaster)
			return;

		// Default slider
		$system = "wowslider";
		$width = "1500";
		$height = "300";

		// Check to see if directory exists for javascript system
		$cms = $f3->SETTINGS["paths"]["cms"];
		$systemPath = $cms."modulesUI/banners/systems/".$system;

		if (!is_dir($systemPath)) {
			error::log("A banner system was created where none exists at $systemPath");
			$f3->rereoute("/admin/banners");
			return;
		}

		// Make sure uploads folder exists
		if (!is_dir(getcwd()."/uploads"))
			mkdir(getcwd()."/uploads");

		// Create uploads folder
		if (!is_dir(getcwd()."/".banners::$upload_path))
			mkdir(getcwd()."/".banners::$upload_path);

		// Copy banner system to client folder
		copy($systemPath."/slider.html", getcwd()."/".banners::$upload_path."/slider.html");
		copy($systemPath."/slider.js", getcwd()."/".banners::$upload_path."/slider.js");
		copy($systemPath."/slider.css", getcwd()."/".banners::$upload_path."/slider.css");

		setting("banners_width", $width);
		setting("banners_height", $height);

		// Load default images
		if (is_dir($systemPath."/default_images"))
		{	
			$p = $systemPath."/default_images";
			$dir = array_diff(scandir($p), array('..', '.'));

			foreach ($dir as $img) {
				banners::add_banner($p, $img, $img, $width, $height);
			}
		}

		$f3->reroute("/admin/banners");
	}


	static function upload($f3) {

		// Something happened and couldn't move image file..
		if (!move_uploaded_file($f3->FILES["file"]["tmp_name"], banners::$upload_path."/temp_image_name"))
			return;

		banners::add_banner(getcwd()."/".banners::$upload_path, "temp_image_name", $f3->FILES["file"]["name"], setting("banners_width"), setting("banners_height"));

		unlink(getcwd()."/".banners::$upload_path."/temp_image_name");
	}

	static function add_banner($path, $image, $name, $x, $y) {

		$new_name = str_replace(' ', '_', $name);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);
		$new_name = preg_replace('/\.[^.]+$/','',$new_name);
		$new_name .= ".".banners::$file_type;

		banners::resize_image($path, $image, $x, $y, getcwd()."/".banners::$upload_path.$new_name);
	}

	static function delete_banner($f3, $params) {

		$path = getcwd()."/".banners::$upload_path;

		if (file_exists($path.$params['image']))
			unlink($path.$params['image']);
	}

	static function resize_image ($path, $image, $x, $y, $save_as) {

		if (!file_exists($path."/".$image)) exit("no file?");

		// Pull image off the disk into memory
		$temp_image = new Image($image, false, $path."/");
		
		// Resize image using F3's image plugin
		$temp_image->resize($x, $y, true, true);

		switch (banners::$file_type)
		{
			case "jpeg":
				imagejpeg($temp_image->data(banners::$file_type, 100), $save_as);
			break;
			case "png":
				imagepng($temp_image->data(banners::$file_type, 100), $save_as);
			break;
			case "gif":
				imagegif($temp_image->data(banners::$file_type, 100), $save_as);
			break;
		}
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