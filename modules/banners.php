<?php

class banners extends prefab {
	private $namespace;
	private $routes;

	private $file_path, $upload_path, $file_type;

	function __construct($namespace) {
		$f3 = base::instance();
		$this->namespace = $namespace;

		$this->routes = setting($namespace."_routes");
		$this->file_path = getcwd() . "/" . setting($namespace."_directory");

		// Load banner for this route
		if (isroute($this->routes))
		{
			$this->load_settings();
			$this->retreive_content($f3);
		}

		$this->routes($f3);

		if (admin::$signed)
			$this->admin_routes($f3);
	}

	function routes($f3) {

		$f3->route("GET /".$this->namespace."/slider.css", function ($f3) {
			echo Template::instance()->render("uploads/".$this->namespace."/slider.css", "text/css");
		});

		$f3->route("GET /".$this->namespace."/slider.js", function ($f3) {
			echo Template::instance()->render("uploads/".$this->namespace."/slider.js", "application/javascript");
		});
	}

	function admin_routes($f3) {

		#######################################################
		################# Admin Panel Render ##################
		#######################################################
		// Render admin panel
		$f3->route('GET /admin/'.$this->namespace, function ($f3) {

			if (!$this->routes || !$this->file_path)
				$f3->reroute("/admin/".$this->namespace."/setup");

			$this->load_settings();
			$this->retreive_content($f3);

			$f3->set("max_upload_size", file_upload_max_size());

			// Overwrite banners template var incase it is used by another module
			// This is only for admin section.
			$f3->banner = $f3->get($this->namespace);

			$f3->namespace = $this->namespace;

			echo Template::instance()->render("/banners/banners.html");
		});

		$f3->route('GET /admin/'.$this->namespace.'/setup', function ($f3) {
			
			$this->load_settings();
			$f3->banner = $f3->get($this->namespace);
			$f3->namespace = $this->namespace;

			echo Template::instance()->render("/banners/setup.html");
		});

		$f3->route('GET /admin/'.$this->namespace.'/documentation', function ($f3) {
			echo Template::instance()->render("/banners/documentation.html");
		});


		#######################################################
		########### Delete, Upload, Update Settings ###########
		#######################################################
		
		// Update system config of banner system
		$f3->route('POST /admin/'.$this->namespace.'/update_settings', function ($f3) {
			$this->update_settings($f3);
			$f3->reroute('/admin/'.$this->namespace."/setup");
		});

		// Update system config of banner system
		$f3->route('POST /admin/.'.$this->namespace.'./update_settings [ajax]', function ($f3) {
			$this->update_settings($f3);
			exit();
		});

		// Upload via drop zone
		$f3->route('POST /admin/'.$this->namespace.'/dropzone', function () { $this->upload(); });

		// Delete image via ajax
		$f3->route('GET /admin/'.$this->namespace.'/delete/@image [ajax]', function ($f3, $params) {

			$this->delete_banner($f3, $params);
			exit;
		});
	}

	function load_settings()
	{
		$f3 = base::instance();

		setting_use_namespace($this->namespace);

		if (!$this->file_type = setting("file_type")) 
			$this->file_type = "jpeg";

		$routes = setting("routes");
		$fw = setting("javascript_framework_url");
		$js = setting("javascript_url");
		$css = setting("stylesheet_url");
		$jsinit = setting("javascript_init");
		$template = setting("template_code");

		if (!$js || !$css || !$jsinit || !$template)
			$f3->set("{$namespace}.error", true);

		$f3->set("{$this->namespace}.routes", $routes);

		$f3->set("{$this->namespace}.javascript_framework_url", $fw);
		$f3->set("{$this->namespace}.javascript_url", $js);
		$f3->set("{$this->namespace}.stylesheet_url", $css);
		$f3->set("{$this->namespace}.javascript_init", $jsinit);
		$f3->set("{$this->namespace}.template_code", $template);

		setting_clear_namespace();
	}

	function retreive_content($f3) {

		// If there is no folder, don't continue.
		if (!is_dir($this->file_path))
			return;

		// Get images from folder
		$dir = array_diff(scandir($this->file_path), array('..', '.', "slider.html", "slider.css", "slider.js"));

		$order = json_decode(setting($this->namespace."_order"), true);

		// No images to display
		if (empty($dir))
		{
			if ($order)
				setting($this->namespace."_order", "");

			return;
		}

		if (count($order) == 0)
		{
			$order = $dir;
			setting($this->namespace."_order", json_encode($order));
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
				$f3->push($this->namespace.".images", [
					"url"=>"uploads/".$this->namespace."/".$x,
					"filename"=>$x
				]);
			}
		}

		$html = setting($this->namespace."_template_code");


		// if (file_exists($this->file_path."/slider.html"))
		// {
		// 	$temp_hive["banner"] = $f3->get($this->namespace);
		// 	$temp_hive["BASE"] = $f3->BASE;


		// 	$html = Template::instance()->render("uploads/".$this->namespace."/slider.html", "text/html", $temp_hive);
		// 	$f3->set($this->namespace.".html", $html);
		// }

		$f3->set();

		if ($update_order)
			setting($this->namespace."_order", json_encode($order));
	}

	function update_settings($f3) {

		setting_use_namespace($this->namespace);

		if (isset($f3->POST["routes"]))
			setting("routes", $f3->POST["routes"]);

		if (isset($f3->POST["directory"]))
			setting("directory", $f3->POST["directory"]);

		// Slider settings
		if (isset($f3->POST["javascript_framework_url"]))
			setting("javascript_framework_url", $f3->POST["javascript_framework_url"]);

		if (isset($f3->POST["javascript_url"]))
			setting("javascript_url", $f3->POST["javascript_url"]);

		if (isset($f3->POST["stylesheet_url"]))
			setting("stylesheet_url", $f3->POST["stylesheet_url"]);

		if (isset($f3->POST["javascript_init"]))
			setting("javascript_init", $f3->POST["javascript_init"]);

		if (isset($f3->POST["template_code"]))
			setting("template_code", $f3->POST["template_code"]);

		if (isset($f3->POST["directory"]))
			setting("directory", $f3->POST["directory"]);

		// Upload settings
		if (isset($f3->POST["html"]))
			file_put_contents($this->file_path."/slider.html", $f3->POST["html"]);

		if (isset($f3->POST["css"]))
			file_put_contents($this->file_path."/slider.css", $f3->POST["css"]);

		if (isset($f3->POST["js"]))
			file_put_contents($this->file_path."/slider.js", $f3->POST["js"]);

		if (isset($f3->POST["width"]))
			setting("width", $f3->POST["width"]);

		if (isset($f3->POST["height"]))
			setting("height", $f3->POST["height"]);

		if (isset($f3->POST["banners_order"]))
			setting("order", $f3->POST["banners_order"]);

		setting_clear_namespace();
	}


	function delete_banner($f3, $params) {

		$path = $this->file_path."/".$params['image'];

		if (file_exists($path))
			unlink($path);
	}


	function upload() {
		$f3 = base::instance();

		// Temp image path
		$temp_image = $f3->FILES["file"]["tmp_name"];

		// Create a safer file name
		$new_name = str_replace(' ', '_', $f3->FILES["file"]["name"]);
		$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);
		$new_name = preg_replace('/\.[^.]+$/','',$new_name);
		$new_name .= ".".$this->file_type;

		// Where to save
		$save_to = $this->file_path."/".$new_name;

		// Ensure directory exsists and make it if it doesn't
		if (!is_dir($this->file_path))
			mkdir($this->file_path, 0755, true);

		// Get width and height settings
		$width = setting($this->namespace."_width");
		$height = setting($this->namespace."_height");

		// Make sure that width and height are set before resizing image
		if (($width*$height) > 0)
		{
			// Pull image off the disk into memory
			$temp_image = new Image($temp_image, false, "/"); // Image(filename, filehistory, path)

			// Resize image using F3's image plugin
			$temp_image->resize($width, $height, true, true); // resize(width, height, crop, enlarge)

			// Save image depending on user selected file type
			switch ($this->file_type)
			{	
				case "jpg":
				case "jpeg":
					imagejpeg($temp_image->data($this->file_type, 100), $save_to);
				break;
				case "png":
					imagepng($temp_image->data($this->file_type, 100), $save_to);
				break;
				case "gif":
					imagegif($temp_image->data($this->file_type, 100), $save_to);
				break;
			}
		}
	}

	function install () {
		$f3 = base::instance();

		// Ensure directory exsists and make it if it doesn't
		d($this->file_path);

		if (!is_dir($this->file_path))
			mkdir($this->file_path, 0755, true);

		setting($this->namespace."_width", $width, false);
		setting($this->namespace."_height", $height, false);

		// Load default images
		if (is_dir($systemPath."/default_images"))
		{	
			$p = $systemPath."/default_images";
			$dir = array_diff(scandir($p), array('..', '.'));

			foreach ($dir as $img) 
			{
				if (!file_exists($this->file_path."/".$img))
					copy($p."/".$img, $this->file_path."/".$img);
			}
		}

		$f3->reroute('/admin/'.$this->namespace);
	}
}