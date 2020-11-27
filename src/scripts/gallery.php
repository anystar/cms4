<?php

class gallery {

	private $name;
	private $settings;

	function __construct($settings) {

		$f3 = \Base::instance();

		$defaults["class"] = "gallery";
		$defaults["name"] = "gallery";
		$defaults["label"] = "Gallery";
		$defaults["routes"] = "*";
		$defaults["path"] = "assets/gallery/";

			$image_defaults["size"] = "1500x1500";
			$image_defaults["thumbnail"] = [
				"size"=>"500x500",
				"crop"=>true,
				"enlarge"=>true,
				"quality"=>100
			];

			$image_defaults["crop"] = false;
			$image_defaults["enlarge"] = false;
			$image_defaults["quality"] = 100;
			$image_defaults["type"] = "jpg/png/gif/auto";
			$image_defaults["overwrite"] = true;
			$image_defaults["mkdir"] = true;
			$image_defaults["keep-original"] = true;

		$defaults["image-settings"] = $image_defaults;
		$defaults["captions-enabled"] = false;
		$defaults["tags-enabled"] = false;

		check(0, (count($settings) < 3), "**Default example:**", $defaults);

		check(0, $settings["label"], "No label set in **".$settings["name"]."** settings");
		check(0, $settings["path"], "No path set for **".$settings["name"]."**");

		// Set some defaults
		if (!array_key_exists("type", $settings)) $settings["type"] = $defaults["type"];
		if (!array_key_exists("quality", $settings)) $settings["quality"] = $defaults["quality"];
		if (!array_key_exists("captions-enabled", $settings)) $settings["captions-enabled"] = $defaults["captions-enabled"];
		if (!array_key_exists("tags-enabled", $settings)) $settings["tags-enabled"] = $defaults["tags-enabled"];


		// Does image-settings exsist?
		if (!array_key_exists("image-settings", $settings))
		{
			$tmp = [];

			if (array_key_exists("crop", $settings))
				$tmp["crop"] = $settings["crop"];

			if (array_key_exists("image-size", $settings))
				$tmp["size"] = $settings["image-size"];

			$tmp["overwrite"] = false;

			if (array_key_exists("thumb-size", $settings))
				$tmp["thumbnail"]["size"] = $settings["image-size"];

			$tmp["thumnail"]["crop"] = true;
			$tmp["thumnail"]["enlarge"] = true;
			$tmp["thumnail"]["quality"] = 100;


			$settings["image-settings"] = $tmp;
		}

		// Make path absolute
		$settings["filepath"] = getcwd()."/".ltrim($settings["path"], "/");

		checkdir($settings["filepath"]);
		checkdir($settings["filepath"]."/thumbs/");

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

			list($f3->width, $f3->height) = explode("x", $this->settings["image-settings"]["size"]);

			$f3->max_upload_count = ini_get('max_file_uploads');
			$f3->max_upload_size = file_upload_max_size();

			echo \Template::instance()->render("/gallery/gallery.html", "text/html");
		});

		$f3->route("POST /admin/{$this->name}/dropzone", function ($f3) {
			
			$this->upload($f3);

		});

		$f3->route("POST /admin/{$this->name}/update-caption", function ($f3) {
			$data = json_decode(\Base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

			$data["captions"][$f3->POST["filename"]] = $f3->POST["caption"];

			\Base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("POST /admin/{$this->name}/update-tags", function ($f3) {
			$data = json_decode(\Base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

			$data["tags"][$f3->POST["filename"]] = $f3->POST["tags"];

			\Base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("GET /admin/{$this->name}/delete", function ($f3, $params) {

			$data = json_decode(\Base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

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

			\Base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("POST /admin/{$this->name}/update_order", function ($f3) {

			$images = json_decode($f3->POST["gallery_order"], true);

			// Get data
			$data = json_decode(\Base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

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

			\Base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		});

		$f3->route("POST /admin/{$this->name}/upload_settings", function ($f3) {
			$this->update_settings($f3->POST);
			$f3->reroute("/admin/".$this->namespace);
		});

		$f3->route("POST /admin/{$this->name}/traditional_upload", function ($f3) {
	
			$this->upload();

			$f3->reroute("/admin/".$this->name);
		});

		$f3->route("POST /admin/{$this->name}/url_upload", function ($f3) {

			$upload_path = $this->settings["path"];

			$name = saveimg ($f3->POST["url"], $upload_path, $this->settings["image-settings"]);

			$this->append_to_order($name["filename"]);

			$f3->reroute("/admin/".$this->name);
		});
	}

	function getImages () {
		$return = array();

		$urlpath = ltrim(rtrim($this->settings["path"], "/"), "/");
		$filepath = $this->settings["filepath"];

		// Scan filepath
		$files = array_diff(scandir($filepath), array('..', '.'));;

		// Directory empty
		if (empty($files))
			return null;

		// Get data
		$data = json_decode(\Base::instance()->read(".cms/json/".$this->name."_data.json"), 1);

		if ($data == null)
		{
			// No data found at all, lets generate a bare file.
			$data["captions"] = array();
			$data["tags"] = array();
			$data["order"] = array();

			\Base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
		}

		// Ensure there is a captions and tags array
		if (!array_key_exists("captions", $data)) $data["captions"] = array();
		if (!array_key_exists("tags", $data)) $data["tags"] = array();

		foreach ($files as $key=>$file) {
			$temp = array();

			// Skip directories
			if (is_dir($filepath."/".$file))
				continue;

			// Skip non-existent files
			if (!is_file($filepath."/".$file))
				continue;

			$temp["url"] = \Base::instance()->BASE."/".$urlpath."/".$file;
			$temp["filename"] = $file;
			$temp["thumb"] = \Base::instance()->BASE."/".$urlpath."/"."thumbs/thumb_".$temp["filename"];

			$img = new Image($filepath."/".$file, false, "");

			// Ensure it loaded
			if ($img->data == false)
				continue;

			$thumbnail_size = $this->settings["image-settings"]["thumbnail"]["size"];
			$thumbnail_size = explode("x", $thumbnail_size);

			$temp["thumb_width"] = $thumbnail_size[0];
			$temp["thumb_height"] = $thumbnail_size[1];

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

		$temp = array_values($temp);

		return $temp;
	}

	function upload() {		

		$upload_path = $this->settings["path"];

		foreach (\Base::instance()->FILES as $file)
		{
			// Save primary image
			$name = saveimg ($file, $upload_path, $this->settings["image-settings"]);

			$this->append_to_order($name["filename"]);
		}
	}

	function append_to_order ($image_name) {
		$data = json_decode(\Base::instance()->read(".cms/json/".$this->name."_data.json"), 1);
		$data["order"][] = $image_name;
		\Base::instance()->write(".cms/json/".$this->name."_data.json", json_encode($data, JSON_PRETTY_PRINT));
	}

	function toolbar () {
		return "<a href='".\Base::instance()->BASE."/admin/".$this->name."' class='button'>Edit ".$this->name."</a>";
	}

	static function dashboard ($settings) {

		if (isroute($settings["routes"]))
			return '<a target="_blank" href="'.\Base::instance()->BASE.'/admin/'.$settings["name"].'/" class="webworkscms_button btn-fullwidth">Edit '.$settings["label"].'</a>';
		else
			return "";
	}
}