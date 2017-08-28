<?php

class gallery {

	private $name;
	private $settings;

	function __construct($settings) {

		$f3 = base::instance();

		$defaults["class"] = "gallery";
		$defaults["name"] = "gallery";
		$defaults["label"] = "Gallery";
		$defaults["route"] = "gallery.html";
		$defaults["image-size"] = "1500x1500";
		$defaults["thumb-size"] = "400x400";
		$defaults["path"] = "assets/gallery/";
		$defaults["crop"] = "false";
		$defaults["enlarge"] = "false";
		$defaults["captions-enabled"] = false;
		$defaults["tags-enabled"] = false;

		check(0, (count($settings) < 3), "**Default example:**", $defaults);

		check(0, $settings["label"], "No label set in **".$settings["name"]."** settings");
		check(0, $settings["image-size"], 'Please set a size for **'.$settings["name"].'** settings', "**Example:**",'`image-size: 400x200`', "Leave blank for no resize");
		check(0, $settings["thumb-size"], 'Please set a thumb size for **'.$settings["name"].'** settings', "**Example:**",'`thumb-size: 100x50`', "Leave blank for no resize");
		check(0, $settings["path"], "No path set for **".$settings["name"]."**");

		// Make path absolute
		$settings["filepath"] = getcwd()."/".ltrim($settings["path"], "/");

		checkdir($settings["filepath"]);
		checkdir($settings["filepath"]."/thumbs/");

		if (is_string($settings["image-size"]))
			$settings["image-size"] = explode("x", $settings["image-size"]);

		if (is_string($settings["thumb-size"]))
			$settings["thumb-size"] = explode("x", $settings["thumb-size"]);

		$settings["captions-enabled"] = $settings["captions-enabled"]=="true" || $settings["captions-enabled"]=="1" ? true : false;
		$settings["tags-enabled"] = $settings["tags-enabled"]=="true" || $settings["tags-enabled"]=="1" ? true : false;

		$this->settings = $settings;
		$this->name = $settings["name"];

		if (isroute($settings['routes']))
			$this->images = $this->getImages();

		// Load admin routes if signed in
		if (admin::$signed)
		{
			if (isroute($settings["routes"]) && !isroute("/admin/*")) {
				$f3->gallery_toolbar = $settings;
				$f3->clear("gallery_toolbar");
			}

			$this->admin_routes($f3);
		}
	}

	function admin_routes($f3) {

		$f3->route("GET /admin/{$this->name}", function ($f3) {


			$f3->gallery = $this->getImages();
			$f3->label = $this->settings["label"];
			$f3->name = $this->settings["name"];
			$f3->enable_captions = $this->settings["captions-enabled"];
			$f3->enable_tags = $this->settings["tags-enabled"];
			$f3->height = $this->settings["image-size"][0];
			$f3->width = $this->settings["image-size"][1];

			$f3->max_upload_count = ini_get('max_file_uploads');
			$f3->max_upload_size = file_upload_max_size();

			echo Template::instance()->render("/gallery/gallery.html", "text/html");
		});

		$f3->route("POST /admin/{$this->name}/dropzone", function ($f3) {
			
			$this->upload($f3);

		});

		$f3->route("POST /admin/{$this->name}/update-caption", function ($f3) {
			$data = json_decode(base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

			$data["captions"][$f3->POST["filename"]] = $f3->POST["caption"];

			base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("POST /admin/{$this->name}/update-tags", function ($f3) {
			$data = json_decode(base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

			$data["tags"][$f3->POST["filename"]] = $f3->POST["tags"];

			base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("GET /admin/{$this->name}/delete", function ($f3, $params) {

			$data = json_decode(base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

			$image = $f3->GET["image"];

			// Remove from order
			if (array_key_exists("order", $data))
				if ($key = array_search($image, $data["order"]))
					unset($data["order"][$key]);

			// Remove any captions
			if (array_key_exists("captions", $data))
				if (array_key_exists($image, $data["captions"]))
					unset($data["captions"][$image]);

			// Remove any tags
			if (array_key_exists("tags", $data))
				if (array_key_exists($image, $data["tags"]))
					unset($data["tags"][$image]);

			// Remove file
			if (file_exists($this->settings["path"]."/".$image))
				unlink($this->settings["path"]."/".$image);

			// Remove thumbnail
			if (file_exists($this->settings["path"]."/thumbs/thumb_".$image))
				unlink($this->settings["path"]."/thumbs/thumb_".$image);

			base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("POST /admin/{$this->name}/update_order", function ($f3) {

			$images = json_decode($f3->POST["gallery_order"], true);

			// Get data
			$data = json_decode(base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

			// Get a list of current images
			$files = array_diff(scandir($this->settings["filepath"]), array('..', '.', 'thumbs'));;

			// Do a validity check to ensure images exist.
			foreach ($images as $image) {
				$path = $this->settings["filepath"]."/".$image;

				// Only add those that exists
				if ($key = array_search($image, $files))
				{
					unset($files[$key]);
					$order[] = $image;
				}
			}

			// Add any that are left over
			if (count($files) > 0)
				$order = array_merge($order, $files);

			$data["order"] = $order;

			base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("POST /admin/{$this->name}/upload_settings", function ($f3) {
			$this->update_settings($f3->POST);
			$f3->reroute("/admin/".$this->namespace);
		});

		$f3->route("POST /admin/{$this->name}/traditional_upload", function ($f3) {
	
			$this->upload($f3);

			$f3->reroute("/admin/".$this->name);
		});
	}

	function getImages () {
		$return = array();

		$urlpath = $this->settings["path"];
		$filepath = $this->settings["filepath"];

		// Scan filepath
		$files = array_diff(scandir($filepath), array('..', '.'));;

		// Directory empty
		if (empty($files))
			return null;

		// Get data
		$data = json_decode(base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

		if ($data == null)
		{
			// No data found at all, lets generate a bare file.
			$data["captions"] = array();
			$data["tags"] = array();
			$data["order"] = array();

			base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		}

		// Ensure there is a captions and tags array
		if (!array_key_exists("captions", $data)) $data["captions"] = array();
		if (!array_key_exists("tags", $data)) $data["tags"] = array();


		foreach ($files as $key=>$file) {
			$temp = array();

			// Skip directories
			if (is_dir($filepath."/".$file))
				continue;

			$temp["url"] = base::instance()->BASE."/".$urlpath."/".$file;
			$temp["filename"] = $file;

			// Check for thumb
			if (!is_file($thumb = $filepath."/thumbs/thumb_".$temp["filename"]))
				$this->resize_image ($filepath."/".$file, $this->settings["thumb-size"][0], $this->settings["thumb-size"][1], $thumb);
			
			$temp["thumb"] = base::instance()->BASE."/".$urlpath."/thumbs/thumb_".$temp["filename"];

			$img = new Image($filepath."/".$file);

			$temp["width"] = $img->width();
			$temp["height"] = $img->height();

			$temp["thumb_width"] = $this->settings["thumb-size"][0];
			$temp["thumb_height"] = $this->settings["thumb-size"][1];

			if ($temp["width"] != $this->settings["width"] || $temp["height"] != $this->settings["height"])
				$temp["error_size"] = true;

			// Add captions and tags from data array
			if (array_key_exists($temp["filename"], $data["captions"]))
				$temp["caption"] = $data["captions"][$temp["filename"]];

			// Add captions and tags from data array
			if (array_key_exists($temp["filename"], $data["tags"]))
				$temp["tags"] = $data["tags"][$temp["filename"]];

			$return[$temp["filename"]] = $temp;
		}

		// Sort into order
		if (array_key_exists("order", $data)) {
			$temp = array();

			foreach ($data["order"] as $o) {

				if (array_key_exists($o, $return))
				{
					$temp[] = $return[$o];
					unset($return[$o]);
				}
			}			
		}

		// Add any extras that didn't appear in order array
		if (count($return) > 0)
			$temp = array_merge($temp, $return);

		return $temp;
	}

	function upload($f3) {		

		$upload_path = $this->settings["path"];
		$thumb_path = $this->settings["path"] . "/thumbs/";

		foreach ($f3->FILES as $file)
		{
			
			if ($file["tmp_name"] == "") continue;

			$temp_image = $file["tmp_name"];

			// Check to ensure extension is same as actual file type.


			// Add a check if file is too big
			// ...........

			// New name
			$new_name = str_replace(' ', '_', $file["name"]);
			$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

			$pi = pathinfo($new_name);

			$new_name = $pi["filename"] . ".jpg";

			// Where to save the full image too
			$save_to_full = getcwd()."/".$upload_path."/".$new_name;

			// Where to save the thumb too
			$save_to_thumb = getcwd()."/".$thumb_path."/thumb_".$new_name;

			// Get settings for image size
			$image_size = $this->settings["image-size"];
			$thumb_size = $this->settings["thumb-size"];

			// Resize full image and save
			if ($image_size[0] > 0 && $image_size[1] > 0)
				$this->resize_image($temp_image, $image_size[0], $image_size[1], $save_to_full);
			// If no image size set, just move image
			else
				copy($temp_image, $save_to_full);

			// Resize thumbnail image and save
			if ($thumb_size[0] > 0 && $thumb_size[1] > 0)
				$this->resize_image($temp_image ,$thumb_size[0], $thumb_size[1], $save_to_thumb);
			
			// If thumbnail settings are not set just resize as image size
			else if ($image_size[0] > 0 && $image_size[1] > 0)
				$this->resize_image($temp_image, $image_size[0], $image_size[1], $save_to_thumb);

			// If image settings not set lets just copy the raw file
			else
				copy($temp_image, $save_to_thumb);

			$this->append_to_order($new_name);
		}
	}

	function resize_image ($image, $x, $y, $save_as) {

		// Pull image off the disk into memory
		$temp_image = new Image($image, false);

		// Resize image using F3's image plugin
		$temp_image->resize($x, $y, $this->settings["crop"], $this->settings["enlarge"]);
		
		// Save image
		imagejpeg($temp_image->data(), $save_as);
	}

	function append_to_order ($image_name) {
		$data = json_decode(base::instance()->read(".cms/json/".$this->name."_data.json"), 1);
		$data["order"][] = $image_name;
		base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
	}

	function toolbar () {
		return "<a href='".base::instance()->BASE."/admin/".$this->name."' class='button'>Edit ".$this->name."</a>";
	}
}