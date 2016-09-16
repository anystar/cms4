<?php

class banners extends prefab {

	static $upload_path = "uploads/slider/";
	static $file_type = "jpeg";

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
		});

		$f3->route("GET /banners/slider.js", function ($f3) {
			echo Template::instance()->render(banners::$upload_path."/slider.js", "application/javascript");
		});
	}

	function admin_routes($f3) {

		#######################################################
		################# Admin Panel Render ##################
		#######################################################
		// Render admin panel
		$f3->route('GET /admin/banners', 'banners::admin_render');

		// Render install page
		$f3->route('POST /admin/banners/install', 'banners::install');

		$f3->route("GET /admin/banners/documentation", function ($f3) {
			echo Template::instance()->render("/banners/documentation.html");
		});


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
		$f3->route('GET /admin/bannersajax/delete/@image', function ($f3, $params) {
			banners::delete_banner($f3, $params);

			$f3->reroute("/admin/banners");
		});

		// Delete image via ajax
		$f3->route('GET /admin/banners/delete/@image [ajax]', function ($f3, $params) {

			banners::delete_banner($f3, $params);
			exit;
		});
	}

	static function retreive_content($f3) {

		// Get images URLs
		$dir = array_diff(scandir(banners::$upload_path), array('..', '.', "slider.html", "slider.css", "slider.js"));

		$order = json_decode(setting("banners_order"), true);

		// No images to display
		if (empty($dir))
		{
			if ($order)
				setting("banners_order", "");

			return;
		}

		if (count($order) == 0)
		{
			$order = $dir;
			setting("banners_order", json_encode($order));
		}

		// If there is a difference between whats in the directory
		// and what is in the order string... 
		if (count($order) != count($dir))
		{
			// Remove any images in order that do not exist
			foreach ($order as $x) {
				if (in_array($x, $dir)) {
					$keep[] = $x;
					$update_order = true;
				}
			}

			// Add any images into order that are not there
			foreach ($dir as $x) {
				if (!in_array($x, $order)) {
					$keep[] = $x;
					$update_order = true;
				}
			}
			
			// Update dir array with new array
			$order = $keep;
		}

		if ($order)
		{
			foreach ($order as $x) {
				$f3->push("banners.images", [
					"url"=>banners::$upload_path.$x,
					"filename"=>$x
				]);
			}
		}

		if (file_exists(banners::$upload_path."/slider.html"))
			$f3->banners["html"] = Template::instance()->render(banners::$upload_path."/slider.html");

		if ($update_order)
			setting("banners_order", json_encode($order));
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

		if (banners::hasInit())
		{
			if (admin::$signed) {
				if (file_exists(banners::$upload_path."/slider.css"))
					$f3->banners["css"] = Template::instance()->render(banners::$upload_path."/slider.css");

				if (file_exists(banners::$upload_path."/slider.js"))
					$f3->banners["js"] = Template::instance()->render(banners::$upload_path."/slider.js");
			}

			echo Template::instance()->render("/banners/banners.html");
		}
		else
			echo Template::instance()->render("/banners/install.html");
	}


	static function install ($f3) {
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
				copy($p."/".$img, getcwd()."/".banners::$upload_path."/".$img);
			}
		}

		$f3->reroute("/admin/banners");
	}


	static function delete_banner($f3, $params) {

		$path = getcwd()."/".banners::$upload_path;

		if (file_exists($path.$params['image']))
			unlink($path.$params['image']);
	}


	static function upload($f3) {

		// Create a safer file name
		$new_name = str_replace(' ', '_', $f3->FILES["file"]["name"]);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);
		$new_name = preg_replace('/\.[^.]+$/','',$new_name);
		$new_name .= ".".banners::$file_type;

		// 
		$tmpImage = getcwd()."/".banners::$upload_path."/".$new_name;

		// Something happened and couldn't move image file..
		if (!move_uploaded_file($f3->FILES["file"]["tmp_name"], $tmpImage))
		{
			exit("couldn't move uploaded file?");
		}

		// Get width and height settings
		$width = setting("banners_width");
		$height = setting("banners_height");

		// Make sure that width and height are set before resizing image
		if (($width*$height) > 0)
		{
			// Pull image off the disk into memor
			$temp_image = new Image($new_name, false, getcwd() . "/" . banners::$upload_path . "/"); // Image(filename, filehistory, path)

			// Resize image using F3's image plugin
			$temp_image->resize($width, $height, true, true); // resize(width, height, crop, enlarge)

			// Save image depending on user selected file type
			switch (banners::$file_type)
			{
				case "jpeg":
					imagejpeg($temp_image->data(banners::$file_type, 100), getcwd()."/".banners::$upload_path."/".$new_name);
				break;
				case "png":
					imagepng($temp_image->data(banners::$file_type, 100), getcwd()."/".banners::$upload_path."/".$new_name);
				break;
				case "gif":
					imagegif($temp_image->data(banners::$file_type, 100), getcwd()."/".banners::$upload_path."/".$new_name);
				break;
			}
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