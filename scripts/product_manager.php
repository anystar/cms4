<?php

class product_manager extends prefab {

	public $settings, $name;
	private $jig, $products;

	function __construct($settings) {

		$defaults["class"] = "product_manager";
		$defaults["name"] = "product_manager";
		$defaults["label"] = "Products";
		$defaults["routes"] = "*";

		$defaults["folder"] = "products/";

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

		$defaults["image-settings"] = $image_defaults;

		// Throw the default settings example up
		check(0, (count($settings) < 3), "**Default example:**", $defaults);

	
		// Make path absolute
		$settings["folder"] = getcwd()."/".ltrim($settings["folder"], "/");

		// Ensure folder and file structure is valid
		if (!is_dir($settings["folder"]))
			mkdir($settings["folder"]);

		if (!is_dir($settings["folder"]."/product-data")) 
			mkdir($settings["folder"]."/product-data");

		if (!is_dir($settings["folder"]."/product-images")) 
			mkdir($settings["folder"]."/product-images");

		if (!is_dir($settings["folder"]."/product-images")) 
			mkdir($settings["folder"]."/product-images");

		if (!is_dir($settings["folder"]."/product-images/thumbs")) 
			mkdir($settings["folder"]."/product-images/thumbs");

		// Deny from product-data folder
		if (!is_file($settings["folder"]."/product-data/.htaccess"))
			file_put_contents($settings["folder"]."/product-data/.htaccess", "Deny From All");

		$this->jig = new \DB\Jig ($settings["folder"]."/product-data/", \DB\Jig::FORMAT_JSON );

		$this->products = new \DB\Jig\Mapper($this->jig, "products.json");

		//$data = $this->jig->find();



		$this->settings = $settings;
		$this->name = $settings["name"];

		$this->routes(base::instance());
	}

	function routes($f3) {

		if (!admin::$signed)
			return;

		base::instance()->route("GET /admin/".$this->name, function ($f3) {

			$f3->settings = $this->settings;
			$f3->name = $this->name;

			$f3->products = $this->products->find();

			echo \Template::instance()->render("/product-manager/index.html");
		});

		base::instance()->route("GET /admin/".$this->name."/add-product", function ($f3) {

			$f3->settings = $this->settings;
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/add-product.html");
		});

		base::instance()->route("POST /admin/".$this->name."/add-product", function ($f3) {

			// Product identifier
			for ($i=0; $i < 10; $i++)
			{
				$id = substr(sha1(uniqid("")), -8);

				// Check if see if there is a collision
				if (!$this->products->find(["@product_id=?", $id]))
				{	
					$f3->POST["product_id"] = $id;
					$id = null;
					$i = 99;
				}
			}

			if ($i != 100)
				base::instance()->error(500, "We actually collided?");

			$primary_image = $f3->FILES["product_primary_image"];

			// Rename
			$primary_image["name"] = $this->create_product_name("primary", $f3->POST["product_id"], $f3->POST["product_name"], $primary_image["name"]);

			$this->products->copyfrom($f3->POST);
			$this->products->insert();

			saveimg($primary_image, $this->settings["folder"]."product-images/", $this->settings["image-settings"]);

			// Product successfully added, lets reroute to its manage page
			$f3->reroute("/admin/".$this->name."/manage-product?product=".$f3->POST["product_id"]);

		});

		base::instance()->route("GET /admin/".$this->name."/manage-product", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/manage-product.html");
		});

		base::instance()->route("GET /admin/".$this->name."/edit-product", function ($f3) {
			$f3->name = $this->name;

			$f3->product = $this->products->find(["@product_id=?", $f3->GET["product"]])[0];

			echo \Template::instance()->render("/product-manager/edit-product.html");
		});

		base::instance()->route("POST /admin/".$this->name."/edit-product", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/edit-product.html");
		});

		base::instance()->route("GET /admin/".$this->name."/manage-images", function ($f3) {
			$f3->name = $this->name;

			$f3->product = $this->products->load(["@product_id=?", $f3->GET["product"]]);
			$f3->product_primary_image = $this->primary_image($f3->product);

			echo \Template::instance()->render("/product-manager/manage-images.html");
		});

		base::instance()->route("GET /admin/".$this->name."/delete-product", function ($f3) {

			$this->products->load(["@product_id=?", $f3->GET["product"]]);
			$this->products->erase();

			$this->delete_products_images($this->products->products_id);

			$f3->reroute("/admin/".$this->name);
		});

		base::instance()->route("GET /admin/product-manager/style.css", function ($f3) {

			echo \Template::instance()->render("/product-manager/style.css", "text/css");
			$f3->abort();
		});

		base::instance()->route("GET /admin/product-manager/script.js", function ($f3) {

			echo \Template::instance()->render("/product-manager/script.js", "application/javascript");
			$f3->abort();
		});
	}

	function create_product_name ($prefix, $id, $product_name, $filename) {

		$product_name = product_manager::normalizeString($product_name);
		$filename = product_manager::normalizeString($filename);

		return $prefix."_".$id."_".$product_name."_".$filename;
	}

	function primary_image ($product) {

		$image_folder = $this->settings["folder"]."product-images/";

		$images = glob($image_folder."primary_".$product->product_id."_*");

		// Really should be == 0 or else we have mutliply primary images
		// if (count($images) > 0)
		// {
		// 	finfo($images[0]);
		// }

	}

	function delete_products_images ($id) {

		$image_folder = $this->settings["folder"]."product-images/";

		// Delete primary images
		$images = glob($image_folder."primary_".$id."_*");

		if ($images != null)
			foreach ($images as $image)
				unlink($image);

		// Delete primary image thumb
		$images = glob($image_folder."thumbs/"."thumb_primary_".$id."_*");

		if ($images != null)
			foreach ($images as $image)
				unlink($image);

		// Delete additional images
		$images = glob($image_folder."additional_".$id."_*");

		if ($images != null)
			foreach ($images as $image)
				unlink($image);

		// Delete additional image thumb
		$images = glob($image_folder."thumbs/"."thumb_additional_".$id."_*");

		if ($images != null)
			foreach ($images as $image)
				unlink($image);
	}

	public static function normalizeString ($str = '')
	{
	    $str = strip_tags($str); 
	    $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
	    $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
	    $str = strtolower($str);
	    $str = html_entity_decode( $str, ENT_QUOTES, "utf-8" );
	    $str = htmlentities($str, ENT_QUOTES, "utf-8");
	    $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str);
	    $str = str_replace(' ', '-', $str);
	    $str = rawurlencode($str);
	    $str = str_replace('%', '-', $str);
	    return $str;
	}

	static function dashboard ($settings) {

		if (isroute($settings["routes"]))
		{
			$settings["name"] = isset($settings["name"]) ? $settings["name"] : "product_manager";
			$settings["label"] = isset($settings["label"]) ? $settings["label"] : "Products";

			return '<a href="'.base::instance()->BASE.'/admin/'.$settings["name"].'/" class="webworkscms_button btn-fullwidth">Edit '.$settings["label"].'</a>';
		}
	}
}