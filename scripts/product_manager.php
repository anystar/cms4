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

		if (!is_dir($settings["folder"]."/product-images-thumbs")) 
			mkdir($settings["folder"]."/product-images-thumbs");

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

			echo \Template::instance()->render("/product-manager/index.html");
		});

		base::instance()->route("GET /admin/".$this->name."/add-product", function ($f3) {

			$f3->settings = $this->settings;
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/add-product.html");
		});

		base::instance()->route("POST /admin/".$this->name."/add-product", function ($f3) {


			///$product[""]


			//$this->add_product ($product);

			// Product successfully added, lets reroute to its manage page
			$f3->reroute("/admin/".$this->name."/manage-product?product="."productID");

		});

		base::instance()->route("GET /admin/".$this->name."/manage-product", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/manage-product.html");
		});

		base::instance()->route("GET /admin/".$this->name."/edit-product", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/edit-product.html");
		});

		base::instance()->route("POST /admin/".$this->name."/edit-product", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/edit-product.html");
		});

		base::instance()->route("GET /admin/".$this->name."/add-image", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/add-image.html");
		});

		base::instance()->route("GET /admin/".$this->name."/arrange-images", function ($f3) {
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/arrange-images.html");
		});

		base::instance()->route("GET /admin/".$this->name."/delete-product", function ($f3) {

			k("delete product ".$f3->GET["product"]);

			echo \Template::instance()->reroute("/product-manager/index.html");
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

	function add_product ($product) {



		//$this->product

	}

	function upload_image ($settings) {


		// Rename images
		
		// productname_primary_imgname


		// Resize images

		// Save images


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