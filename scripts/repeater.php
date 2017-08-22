<?php

class repeater {

	private $name;
	public $jig;

	function __construct($settings) {

		$defaults["class"] = "repeater";
		$defaults["name"] = "example";
		$defaults["label"] = "example";
		$defaults["route"] = "*";
		$defaults["template"] = "_products.html";

		check(0, (count($settings) < 3), "**Default example:**", $defaults);

		$this->snippets[] = "_blog.html";
		$this->snippets[] = "_menu.html";
		$this->snippets[] = "_products.html";
		$this->snippets[] = "_testimonials.html";
		$this->snippets[] = "_events.html";

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

		$jig = new \DB\Jig (getcwd()."/.cms/repeaters/", \DB\Jig::FORMAT_JSON );

		$this->jig = new \DB\Jig\Mapper($jig, $settings["name"]);

		$this->data = $this->jig->find();

		$this->name = $settings["name"];
		$this->settings = $settings;

		// Do admin routes
		if (admin::$signed)
			$this->admin_routes(base::instance());

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
			$f3->data = $this->jig->find();

			echo Template::instance()->render("/repeat/repeat.html");
		});

		$f3->route('POST /admin/'.$this->name.'/addupdate', function ($f3) {

			// Upload any files
			// if ($image_directory = setting($this->name . "_image_directory")) {

			// 	checkdir(getcwd()."/".$image_directory);

			// 	$imagesize = [0,0];
			// 	$imagesize = setting($this->name . "_image_size");

			// 	if (strlen($imagesize) > 0)
			// 		$imagesize = explode("x", $imagesize);

			// 	foreach ($f3->FILES as $key => $file)
			// 	{
			// 		if (!$file["tmp_name"])
			// 			continue;

			// 		if ($imagesize[0] > 0 && $imagesize[1] > 0)
			// 		{
			// 			$this->resize_image ($file["tmp_name"], $imagesize[0], $imagesize[1], getcwd()."/".$image_directory."/".$file["name"]);
			// 		}
			// 		else
			// 			move_uploaded_file($file["tmp_name"], getcwd()."/".$image_directory."/".$file["name"]);

			// 		if (checkfile(getcwd()."/".$image_directory."/".$file["name"]))
			// 			$f3->POST[$key] = ltrim(rtrim($image_directory, "/"), "/") . "/" . $file["name"];

			// 	}
			// }

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

	function resize_image ($image, $x, $y, $save_as) {

		// Pull image off the disk into memory
		$temp_image = new Image($image, false, "/");

		// Resize image using F3's image plugin
		$temp_image->resize($x, $y, false, true);
		
		// Save image	
		imagejpeg($temp_image->data(), $save_as);
	}


	function toolbar () {
		return "<a href='".base::instance()->BASE."/admin/".$this->settings["name"]."' class='button'>Add/Edit ".$this->settings["label"]."</a>";
	}
}