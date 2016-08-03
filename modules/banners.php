<?php

class banners extends prefab {

	static $upload_path = "uploads/slider/";
	static $file_type = "jpeg";

	static $system;
	static $system_path;

	function __construct() {
		$f3 = base::instance();

		if ($f3->exists("SETTINGS.banners-upload_path"))
			banners::$upload_path = $f3->get("CONFIG.banners-upload_path");

		if ($f3->exists("SETTINGS.banners-file_type"))
			banners::$file_type = $f3->get("CONFIG.banners-file_type");

		if ($this->hasInit())
		{
			banners::$system = config("banners-system");

			// get system configuration
			$f3->set("banners.system_config", config_json("banners-system_config"));
			$f3->set("banners.width", config_json("banners-width"));
			$f3->set("banners.height", config_json("banners-height"));

			banners::$system_path = "banners/systems/".banners::$system."/";

			banners::retreive_content($f3);

			$this->routes(base::instance());
		}

		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {

		$f3->route("GET /banners/slider.css", function ($f3) {
			$f3->UI = $f3->CONFIG["paths"]["cms"]."/"."adminUI/";
			echo Template::instance()->render(banners::$system_path."/"."slider.css", "text/css");
			exit();
		});

		$f3->route("GET /banners/slider.js", function ($f3) {
			$f3->UI = $f3->CONFIG["paths"]["cms"]."/"."adminUI/";
			$f3->banner = config_json("banners-system_config");
			echo Template::instance()->render(banners::$system_path."/"."slider.js", "text/javascript");
			exit();
		});

	}

	function admin_routes($f3) {

		$f3->route('GET /admin/test', 'banners::test');		
		$f3->route('GET /admin/banners', 'banners::admin_render');
		$f3->route('POST /admin/banners/init', 'banners::init');

		$f3->route('POST /admin/banners/dropzone', 'banners::upload');

		$f3->route('POST /admin/banners/update_settings', function ($f3) {

			set_config("banners-width", filter_var($f3->POST["width"], FILTER_SANITIZE_NUMBER_INT));
			set_config("banners-height", filter_var($f3->POST["height"], FILTER_SANITIZE_NUMBER_INT));

			$f3->reroute("/admin/banners");
		});

		$f3->route('POST /admin/banners/update_javascript_settings', function ($f3) {
			config_json("banners-system_config", $f3->POST);

			$f3->reroute("/admin/banners");
		});

		$f3->route('GET /admin/banners/delete/@image', function ($f3, $params) {
			banners::delete_banner($f3, $params);

			$f3->reroute("/admin/banners");
		});

		$f3->route('GET /admin/banners/delete/@image [ajax]', function ($f3, $params) {
			banners::delete_banner($f3, $params);

			echo $f3->get("banners.html");
			exit;
		});
	}

	static function retreive_content($f3) {
		// Get slider configuration

		// Get images URLs
		$dir = array_diff(scandir(banners::$upload_path), array('..', '.'));

		foreach ($dir as $img) {
			$f3->push("banners.images", [
				"url"=>banners::$upload_path.$img,
				"filename"=>$img
			]);
		}

		$tmp = $f3->UI; $f3->UI = $f3->CONFIG["paths"]["cms"]."/"."adminUI/banners/systems/".banners::$system."/";
		$html = \Template::instance()->render("slider.html");
		$f3->UI = $tmp;

		$f3->set("banners.html", $html);
	}


	static function hasInit() {
		$db = base::instance()->get("DB");

		$result = config("banners-system");

		if (!$result) return false;

		if (is_dir(getcwd()."/".banners::$upload_path))
			return true;

		return false;

	}

	static function admin_render() {

		//TODO: Create html files for admin display and generation
		if (banners::hasInit())
			echo Template::instance()->render("banners/banners.html");
		else
			echo Template::instance()->render("banners/init.html");
	}


	static function test() {

		echo Template::instance()->render("banners/test.html");

	}

	static function init($f3) {

		if (banners::hasInit()) return false;

		$system = $f3->POST["system"];
		$cms = $f3->CONFIG["paths"]["cms"];
		$systemPath = $cms."adminUI/banners/systems/".$system;

		// Check to see if directory exists for javascript system
		if (!is_dir($systemPath)) {
			$f3->ERRORS["not_a_system"];
			$f3->mock("GET /admin/banners");
			return;
		}

		$width = filter_var($f3->POST["bannerwidth"], FILTER_SANITIZE_NUMBER_INT);
		$height = filter_var($f3->POST["bannerheight"], FILTER_SANITIZE_NUMBER_INT);

		// Set the systems defaults
		$defaults = file_get_contents($systemPath."/default_settings.json");
		config("banners-system_config", $defaults);
		config("banners-system", $system);
		config("banners-width", $width);
		config("banners-height", $height);

		// Create uploads folder
		if (!is_dir(getcwd()."/".banners::$upload_path))
			mkdir(getcwd()."/".banners::$upload_path);

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

		banners::add_banner(getcwd()."/".banners::$upload_path, "temp_image_name", $f3->FILES["file"]["name"], config("banners-width"), config("banners-height"));

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
}