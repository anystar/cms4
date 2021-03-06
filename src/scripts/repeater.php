<?php

class repeater {

	public $name;
	public $jig;
	public $data;
	public $snippet;

	function __construct($settings) {

		$defaults["class"] = "repeater";
		$defaults["name"] = "example";
		$defaults["label"] = "example";
		$defaults["routes"] = "*";
		$defaults["template"] = "_products.html";

		check(0, (count($settings) < 3), "**Default example:**", $defaults);

		$from_cms = scandir($GLOBALS["ROOTDIR"]."/src/scriptsUI/repeat/");
		$from_cms = array_diff($from_cms, array('.', '..', 'repeat.html'));

		$client_dir = getcwd()."/.cms/repeat-snippets/";
		$from_client = array();
		if (is_dir($client_dir))
		{
			$from_client = scandir($client_dir);
			$from_client = array_diff($from_client, array('.', '..'));

			foreach ($from_client as $snippet) {
				if (in_array($snippet, $from_cms)) {
					\Base::instance()->error(500, "Duplicated named repeating snippets ".$snippet);
				}
			}
		}

		$this->snippets = array_merge($from_client, $from_cms);

		check(0, $settings["name"], "No `name` set in **".$settings["name"]."** settings");
		check(0, $settings["label"], "No `label` set in **".$settings["name"]."** settings");
		check(0, $settings["template"]
			, "No `template` set in **".$settings["name"]."** settings",
			"Available templates follow",
			$this->snippets
		);

		check(0, !in_array($settings["template"], $this->snippets),
			"Template `".$settings["template"]."` does not exist, set in **".$settings["name"]."** settings",
			"Available templates follow",
			$this->snippets
		);

		// Is it in the CMS directory
		if (in_array($settings["template"], $from_cms))
			$settings["template"] = "/repeat/" . $settings["template"];

		else if (in_array($settings["template"], $from_client)) 
			$settings["template"] = "/.cms/repeat-snippets/" . $settings["template"];

		$jig = new \DB\Jig (getcwd()."/.cms/repeaters/", \DB\Jig::FORMAT_JSON );

		$this->jig = new \DB\Jig\Mapper($jig, $settings["name"]);

		$data = $this->jig->find();

		if (count($data) > 0 && is_array($data)){
			foreach ($data as $row)
				$this->data[] = $row->cast();
		}

		$this->name = $settings["name"];
		$this->settings = $settings;

		// Do admin routes
		if (admin::$signed)
		{
			$this->admin_routes(\Base::instance());
		}
	}

	function admin_routes($f3) {

		$f3->route(['GET /admin/'.$this->name, 'GET /admin/'.$this->name.'/update' ], function ($f3) {

			$f3->template = $this->settings["template"];

			$f3->action = "Create";

			if (isroute('/admin/'.$this->name.'/update'))
			{

				$this->jig->load(["@_id=?", $f3->GET["data_id"]]);

				if (!$this->jig->dry())
				{	
					$f3->values = $this->jig->cast();

					$f3->action = "Update";
					$this->jig->reset();
				}
			}
			
			$f3->name = $this->name;
			$data = $this->jig->find();

			$f3->data = array();

			foreach ($data as $row)
				$f3->data[] = $row->cast();

			echo \Template::instance()->render("/repeat/repeat.html");
		});

		$f3->route('POST /admin/'.$this->name.'/addupdate', function ($f3) {

			// Upload any files
			$image_directory = "";
			if (array_key_exists("image-directory", $this->settings))
				$image_directory = $this->settings["image-directory"];

			$images = array();
			
			foreach ($f3->FILES as $key=>$file)
			{
				if ($file["tmp_name"] == "") continue;


				if (array_key_exists($key, $this->settings["image-settings"])) 
				{
					$image_upload_settings = $this->settings["image-settings"][$key];					
				} 
				else if (array_key_exists("size", $this->settings["image-settings"])) 
				{
					$image_upload_settings = $this->settings["image-settings"];
				}


				$fill = [];

				saveimg($file, $image_directory, $image_upload_settings, $fill);

				foreach ($fill as $image)
				{
					$tmp["image"] = $image["path"];

					if (array_key_exists("thumbnail", $image))
						$tmp["thumbnail"] = $image["thumbnail"]["path"];

					$images[$key][] = $tmp;
				}

			}

			foreach ($images as $key=>$image)
			{
				if (count($image) == 1)
				{
					$f3->POST[$key]["image"] = $image[0]["image"];
					$f3->POST[$key]["thumbnail"] = $image[0]["thumbnail"];
				}
				else if (count($images) > 1)
					$f3->POST[$key] = $image;
			}

			if ($f3->POST["data_id"])
				$this->jig->load(["@_id=?", $f3->POST["data_id"]]);		

			unset($f3->POST["data_id"]);

			$this->jig->copyfrom($f3->POST);

			$this->jig->save();

			$f3->reroute('/admin/'.$this->name.'#form');
		});

		$f3->route('GET /admin/'.$this->name.'/delete', function ($f3) {

			$this->jig->erase(["@_id=?", $f3->GET["data_id"]]);

			$f3->reroute('/admin/'.$this->name);
		});

		$f3->route('GET /admin/'.$this->name.'/toggle', function ($f3) {

			$this->jig->load(["@_id=?", $f3->GET["data_id"]]);

			if ($this->jig->exists("hidden"))
			 	$this->jig->clear("hidden");
			 else
			 	$this->jig->set("hidden", true);

			$this->jig->update();

			$f3->reroute('/admin/'.$this->name);
		});

		$f3->route('POST /admin/'.$this->name.'/reorder [ajax]', function ($f3) {

			$file = getcwd()."/.cms/repeaters/".$this->settings["name"];

			$order = json_decode($f3->POST["order"], true);
			$data = json_decode($f3->read($file), true);
			$data = array_replace(array_flip($order), $data);
			$f3->write($file, json_encode($data, JSON_PRETTY_PRINT));

		});
	}


	function save_image ($settings) {


			k($settings);
	}


	function toolbar () {
		return "<a href='".\Base::instance()->BASE."/admin/".$this->settings["name"]."' class='button'>Add/Edit ".$this->settings["label"]."</a>";
	}

	static function dashboard ($settings) {

		if (isroute($settings["routes"]))
			return '<a target="_blank" href="'.\Base::instance()->BASE.'/admin/'.$settings["name"].'/" class="webworkscms_button btn-fullwidth">Edit '.$settings["label"].'</a>';
	}
}
