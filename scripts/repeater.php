<?php

class repeater {

	private $name;
	public $data;

	function __construct($settings) {


		$this->snippets[] = "_blog.html";
		$this->snippets[] = "_menu.html";
		$this->snippets[] = "_products.html";
		$this->snippets[] = "_testimonials.html";

		check(0, $settings["name"], "No `name` set in **".$settings["name"]."** settings");
		check(0, $settings["label"], "No `label` set in **".$settings["name"]."** settings");
		check(0, $settings["template"]
			, "No `admin-template` set in **".$settings["name"]."** settings",
			"Available templates follow",
			$this->snippets
		);

		check(0, !in_array($settings["template"], $this->snippets),
			"Template `".$settings["template"]."` does not exist, set in **".$settings["name"]."** settings",
			"Available templates follow",
			$this->snippets
		);

		$jig = new \DB\Jig (getcwd()."/.cms/repeaters/", \DB\Jig::FORMAT_JSON );

		$this->data = new \DB\Jig\Mapper($jig, $settings["name"]);

		$this->name = $settings["name"];
		$this->settings = $settings;

		// Do admin routes
		if (admin::$signed)
			$this->admin_routes(base::instance());


		// $this->routes(base::instance());
	}


	function admin_routes($f3) {

		$f3->route(['GET /admin/'.$this->name, 'GET /admin/'.$this->name.'/update' ], function ($f3) {

			$f3->template = $this->settings["template"];

			$f3->action = "Create";

			// if (isroute('/admin/'.$this->name.'/update'))
			// {
			// 	$row = $f3->DB->exec("SELECT * FROM {$this->name} WHERE id=? ORDER BY rowOrder", $f3->GET["data_id"]);

			// 	if (count($row) > 0)
			// 	{	
			// 		$row = $row[0];

			// 		$temp = json_decode($row["data"], true);

			// 		foreach ($temp as $t)
			// 			$temp[] = $t;

			// 		$temp["id"] = $row["id"];

			// 		$f3->values = $temp;

			// 		$f3->action = "Update";
			// 	}
			// }

			
			$f3->name = $this->name;
			$f3->data = $this->data->find();
			echo Template::instance()->render("/repeat/repeat.html");
		});

		$f3->route('POST /admin/'.$this->name.'/addupdate', function ($f3) {


			// Upload any files
			if ($image_directory = setting($this->name . "_image_directory")) {

				checkdir(getcwd()."/".$image_directory);

				$imagesize = [0,0];
				$imagesize = setting($this->name . "_image_size");

				if (strlen($imagesize) > 0)
					$imagesize = explode("x", $imagesize);

				foreach ($f3->FILES as $key => $file)
				{
					if (!$file["tmp_name"])
						continue;

					if ($imagesize[0] > 0 && $imagesize[1] > 0)
					{
						$this->resize_image ($file["tmp_name"], $imagesize[0], $imagesize[1], getcwd()."/".$image_directory."/".$file["name"]);
					}
					else
						move_uploaded_file($file["tmp_name"], getcwd()."/".$image_directory."/".$file["name"]);

					if (checkfile(getcwd()."/".$image_directory."/".$file["name"]))
						$f3->POST[$key] = ltrim(rtrim($image_directory, "/"), "/") . "/" . $file["name"];

				}
			}

			// Create
			if ($f3->POST["data_id"] == null) {

				unset($f3->POST["data_id"]);
				$json = json_encode($f3->POST);

				$f3->DB->exec("INSERT INTO {$this->name} (data) VALUES (?)", $json);
				$id = $f3->DB->lastInsertId();
				$f3->DB->exec("UPDATE {$this->name} SET rowOrder=? WHERE id=?", [$id,$id]);
			}

			// Update
			if ($f3->POST["data_id"] != null) {
				$id = $f3->POST["data_id"];
				unset($f3->POST["data_id"]);
			
				$data = json_decode($f3->DB->exec("SELECT data FROM {$this->name} WHERE id=?", [$id])[0]["data"], true);
				
				$data = arrmerge($data, $f3->POST);

				$json = json_encode($data);

				$f3->DB->exec("UPDATE {$this->name} SET data=? WHERE id=?", [$json, $id]);
			}

			$f3->reroute('/admin/'.$this->name.'#form');
		});

		$f3->route('GET /admin/'.$this->name.'/delete', function ($f3) {

			$f3->DB->exec("DELETE FROM {$this->name} WHERE id=?", $f3->GET["data_id"]);

			$f3->reroute('/admin/'.$this->name.'#form');
		});

		$f3->route('GET /admin/'.$this->name.'/toggle', function ($f3) {

			$data = $f3->DB->exec("SELECT data FROM {$this->name} WHERE id=?", [$f3->GET["data_id"]]);
			$data = json_decode($data[0]["data"], true);

			if (!array_key_exists("hidden", $data))
				$data["hidden"] = false;

			$data["hidden"] = !$data["hidden"];

			$data = json_encode($data);

			$f3->DB->exec("UPDATE {$this->name} SET data=? WHERE id=?", [$data, $f3->GET["data_id"]]);

			$f3->reroute('/admin/'.$this->name);
		});

		$f3->route('POST /admin/'.$this->name.'/reorder [ajax]', function ($f3) {

			j("hello");

			// $order = json_decode($f3->POST["order"], 1);

			// if (count($order)>0) {
			// 	foreach ($order as $key=>$id) {
			// 		$f3->DB->exec("UPDATE {$this->name} SET rowOrder=? WHERE id=?", [$key, $id]);
			// 	}
			// }

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
		return "<a href='".base::instance()->BASE."/admin/".$this->settings["name"]."' class='button'>Edit ".$this->settings["label"]."</a>";
	}
}