<?php

class product_manager extends prefab {

	public $settings, $name, $products_mapper, $collections;
	private $jig;

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
		$image_defaults["placeholder"] = "Product Placeholder";

		$defaults["image-settings"] = $image_defaults;

		// Throw the default settings example up
		check(0, (count($settings) < 3), "**Default example:**", $defaults);

		// Make path absolute
		$settings["absfolder"] = getcwd()."/".ltrim($settings["folder"], "/");

		// Ensure folder and file structure is valid
		if (!is_dir($settings["absfolder"]))
			mkdir($settings["absfolder"]);

		if (!is_dir($settings["absfolder"]."/product-data")) 
			mkdir($settings["absfolder"]."/product-data");

		if (!is_dir($settings["absfolder"]."/product-images")) 
			mkdir($settings["absfolder"]."/product-images");

		if (!is_dir($settings["absfolder"]."/product-images")) 
			mkdir($settings["absfolder"]."/product-images");

		if (!is_dir($settings["absfolder"]."/product-images/thumbs")) 
			mkdir($settings["absfolder"]."/product-images/thumbs");

		// Deny from product-data folder
		if (!is_file($settings["absfolder"]."/product-data/.htaccess"))
			file_put_contents($settings["absfolder"]."/product-data/.htaccess", "Deny From All");

		$this->settings = $settings;
		$this->name = $settings["name"];

		$this->jig = new \DB\Jig ($settings["folder"]."/product-data/", \DB\Jig::FORMAT_JSON );

		$this->collections = new \DB\Jig\Mapper($this->jig, "collections.json");

		$this->products_mapper = new \DB\Jig\Mapper($this->jig, "products.json");
		$this->products_mapper->onload(function($self){
			
			// Get any primary images..
			$image_folder = $this->settings["absfolder"]."product-images/";

			$images = glob($image_folder."primary_".$self->product_id."_*");

			if (count($images) == 1)
			{
				$self->primary_image = rtrim($this->settings["folder"], "/")."/"."product-images/".basename($images[0]);
				$self->primary_image_file = $images[0];
			}
			else
			{
				$self->primary_image = "";
				$self->primary_image_file = "";
			}

			$image_folder .= "thumbs/";

			if (file_exists($image_folder."thumb_".basename($images[0])))
			{
				$self->primary_image_thumb = rtrim($this->settings["folder"], "/")."/"."product-images/thumbs/"."thumb_".basename($images[0]);
				$self->primary_image_thumb_file = $image_folder."thumb_".basename($images[0]);
			}
			else
			{
				// No thumb found, let us just use the primary image
				$self->primary_image_thumb = $self->primary_image;
				$self->primary_image_thumb_file = $self->primary_image_file;
			}

		});

		$this->products_mapper->beforeupdate(function ($self) {
			$self->clear("primary_image");
			$self->clear("primary_image_file");
			$self->clear("primary_image_thumb");
			$self->clear("primary_image_thumb_file");
		});
		
		$this->routes(\Base::instance());
	}

	public function load ($flatten = false) {

		$collections_results = $this->collections->find([], ["order"=>"order SORT_ASC"]);

		$collections = [];
		foreach ($collections_results as $collection)
		{
			$collections[] = $collection->name;
		}

		$product_results = $this->products_mapper->find();

		$products_in_collection = [];
		foreach ($collections as $collection)
		{
			$products_in_collection[$collection] = [];

			foreach ($product_results as $product)
			{
				if (array_key_exists($collection, $product->collections))
				{
					if ($product->collections[$collection] == 0)
						$products_in_collection[$collection][] = $product->cast();
					else
						$products_in_collection[$collection][$product->collections[$collection]] = $product->cast();
				}

				// if (!$flatten)
				// 	krsort($products_in_collection[$collection]);
			}
		}

		$temp = [];
		if ($flatten)
		{
			foreach ($products_in_collection as $collectionName=>$collection)
			{
				foreach ($collection as $product)
				{
					$product["collection"] = $collectionName;
					$temp[] = $product;
				}
			}

			$products_in_collection = $temp;
		}

		return $products_in_collection;
	}

	function routes($f3) {

		if (!admin::$signed)
			return;

		\Base::instance()->route("GET /admin/".$this->name, function ($f3) {

			$f3->settings = $this->settings;
			$f3->name = $this->name;

			$f3->collections = $this->collections->find([], ["order"=>"order SORT_DESC"]);

			// Generate collection dropdown array
			$f3->in_collection = false;
			foreach ($f3->collections as $collection)
			{	
				if ($collection->name == $f3->GET["collection"])
				{
					$f3->in_collection = $collection->name;
					$collection->active = true;
				}
				else
					$collection->active = false;
			}

			$f3->products_in_collection = $this->products_mapper->find(['isset(@collections) && isset(@collections["'.$f3->in_collection.'"])']);

			$rearranged = [];
			$unorderedIndex = 0;
			foreach ($f3->products_in_collection as $product)
			{
				if ($product->collections[$f3->in_collection] == 0)
					$rearranged[$unorderedIndex--] = $product;
				else
					$rearranged[$product->collections[$f3->in_collection]] = $product;
			}

			krsort($rearranged);

			$f3->products_in_collection = $rearranged;

			if ($f3->in_collection)
				$f3->products = $this->products_mapper->find(['isset(@collections) && !isset(@collections["'.$f3->in_collection.'"])']);
			else
				$f3->products = $this->products_mapper->find();
			
			echo \Template::instance()->render("/product-manager/index.html");
		});

		\Base::instance()->route("GET /admin/".$this->name."/add-product", function ($f3) {

			$f3->settings = $this->settings;
			$f3->name = $this->name;

			echo \Template::instance()->render("/product-manager/add-product.html");
		});

		\Base::instance()->route("POST /admin/".$this->name."/add-product", function ($f3) {

			$f3->POST["product_id"] = $this->generateID();

			$primary_image = $f3->FILES["product_primary_image"];

			// Rename
			if ($primary_image["name"] == "")
				$primary_image["name"] = "placeholder.png";

			$primary_image["name"] = $this->create_product_name("primary", $f3->POST["product_id"], $f3->POST["product_name"], $primary_image["name"]);

			$this->products_mapper->copyfrom($f3->POST);	
			$this->products_mapper["collections"] = [];

			$this->products_mapper->insert();

			saveimg($primary_image, $this->settings["folder"]."product-images/", $this->settings["image-settings"]);

			// Product successfully added, lets reroute to its manage page
			$f3->reroute("/admin/".$this->name."/manage-product?product=".$f3->POST["product_id"]);
			
		});

		\Base::instance()->route("GET /admin/".$this->name."/manage-product", function ($f3) {
			$f3->name = $this->name;

			$this->products_mapper->load(["@product_id=?", $f3->GET["product"]]);
			$f3->product = $this->products_mapper;

			echo \Template::instance()->render("/product-manager/manage-product.html");
		});

		\Base::instance()->route("GET /admin/".$this->name."/edit-product", function ($f3) {
			$f3->name = $this->name;

			$f3->product = $this->products_mapper->find(["@product_id=?", $f3->GET["product"]])[0];

			echo \Template::instance()->render("/product-manager/edit-product.html");
		});

		\Base::instance()->route("POST /admin/".$this->name."/edit-product", function ($f3) {
			$f3->name = $this->name;

			$product = $this->products_mapper->load(["@product_id=?", $f3->POST["product"]]);

			$primary_image = $f3->FILES["product_primary_image"];

			if ($primary_image["name"]!="")
			{
				if (file_exists($product->primary_image_file))
					unlink($product->primary_image_file);
				if (file_exists($product->primary_image_thumb_file))
					unlink($product->primary_image_thumb_file);

				$primary_image["name"] = $this->create_product_name("primary", $product->product_id, $product->product_name , $primary_image["name"]);
				saveimg($primary_image, $this->settings["folder"]."product-images/", $this->settings["image-settings"]);
			}

			$product->product_name = $f3->POST["product_name"];
			$product->product_price = $f3->POST["product_price"];
			$product->product_description = $f3->POST["product_description"];

			$product->update();

			\Base::instance()->reroute("/admin/".$this->name."/edit-product?alert=1&product=".$f3->POST["product"]);
		});

		\Base::instance()->route("GET /admin/".$this->name."/manage-images", function ($f3) {
			$f3->name = $this->name;

			$f3->product = $this->products_mapper->load(["@product_id=?", $f3->GET["product"]]);

			echo \Template::instance()->render("/product-manager/manage-images.html");
		});

		\Base::instance()->route("GET /admin/".$this->name."/duplicate-product", function ($f3) {

			$this->products_mapper->onload(null);
			
			$product = $this->products_mapper->load(["@product_id=?", $f3->GET["product"]]);
			$product->copyto("temp_product");
			$f3->temp_product["product_id"] = $this->generateID();
			$product->reset();
			$product->copyfrom("temp_product");
			$product->insert();

			// Generate new images
			$primary_image["name"] = $this->create_product_name("primary", $product->product_id, $product->product_name , "placeholder.png");
			saveimg($primary_image, $this->settings["folder"]."product-images/", $this->settings["image-settings"]);

			$f3->reroute("/admin/".$this->name."/edit-product?alert=0&product=".$product->product_id);

		});

		\Base::instance()->route("GET /admin/".$this->name."/delete-product", function ($f3) {

			$product = $this->products_mapper->load(["@product_id=?", $f3->GET["product"]]);

			if (file_exists($product->primary_image_file))
				unlink($product->primary_image_file);
			if (file_exists($product->primary_image_thumb_file))
				unlink($product->primary_image_thumb_file);

			$product->erase();

			$f3->reroute("/admin/".$this->name);
		});

		\Base::instance()->route("GET /admin/".$this->name."/organise", function ($f3) {
			$f3->name = $this->name;
			$f3->products = $this->products_mapper->find();
			$f3->collections = $this->collections->find([], ["order"=>"order SORT_DESC"]);

			echo \Template::instance()->render("/product-manager/organise.html");
		});

		\Base::instance()->route("POST /admin/".$this->name."/add-collection", function ($f3) {

			$this->collections->reset();
			$this->collections->name = $f3->POST["collection_name"];
			$this->collections->order = 0;
			$this->collections->insert();

			$f3->reroute("/admin/".$this->name."/organise?alert=1&collection=".$this->collections["_id"]);
		});

		\Base::instance()->route("GET /admin/".$this->name."/collection-add-product", function ($f3) {

			$product = $this->products_mapper->load(["@product_id=?", $f3->GET["product"]]);

			if (!$product->exists("collections"))
			{
				$product->collections = [];
			}

			if (!isset($product->collections[$f3->GET["collection"]]))
				$product->collections[$f3->GET["collection"]] = 0;

			$product->update();

			$f3->reroute("/admin/".$this->name."/?alert=2&collection=".$f3->GET["collection"]);
		});

		\Base::instance()->route("GET /admin/".$this->name."/collection-remove-product", function ($f3) {

			$product = $this->products_mapper->load(["@product_id=?", $f3->GET["product"]]);

			if (!array_key_exists("collections", $product))
				$product->collections = [];

			if (!isset($product->collections[$f3->GET["collection"]]))
				unset($product->collections[$f3->GET["collection"]]);

			$product->update();

			$f3->reroute("/admin/".$this->name."/?alert=3&collection=".$f3->GET["collection"]);
		});

		\Base::instance()->route("GET /admin/".$this->name."/collection-delete", function ($f3) {

			$products = $this->products_mapper->find(['isset(@collections) && isset(@collections["'.$f3->GET["collection"].'"])']);

			foreach ($products as $product) {
				unset($product->collections[$f3->GET["collection"]]);
				$product->update();
			}

			$collection = $this->collections->load(["@name=?", $f3->GET["collection"]]);

			$collection->erase();

			$f3->reroute("/admin/".$this->name."/organise?alert=2&collection=".$this->collections["_id"]);
		});

		\Base::instance()->route("GET /admin/".$this->name."/collection-empty", function ($f3) {

			$products = $this->products_mapper->find(['isset(@collections) && isset(@collections["'.$f3->GET["collection"].'"])']);

			foreach ($products as $product) {
				unset($product->collections[$f3->GET["collection"]]);
				$product->update();
			}

			$f3->reroute("/admin/".$this->name."/organise?alert=3");
		});

		\Base::instance()->route("POST /admin/".$this->name."/collection-order-products", function ($f3) {

			$collection = $f3->POST["collection"];
			$order = json_decode($f3->POST["order"], true);

			foreach ($order as $key=>$product_id) 
			{
				$product = $this->products_mapper->load(["@product_id=?", $product_id]);

				$product->collections[$collection] = count($order)-$key;
				$product->update();
			}

			exit();
		});

		\Base::instance()->route("POST /admin/".$this->name."/collection-order-collections", function ($f3) {

			$order = json_decode($f3->POST["order"], true);

			foreach ($order as $key=>$collection)
			{
				$collection = $this->collections->load(["@name=?", $collection]);

				$collection->order = count($order)-$key;
				$collection->update();
			}

			exit();
		});

		\Base::instance()->route("POST /admin/".$this->name."/collection-rename", function ($f3) {

			$collection = $this->collections->load(["@name=?", $f3->POST["old"]]);
			$collection->name = $f3->POST["new"];
			$collection->update();

			exit();
		});

		\Base::instance()->route("GET /admin/product-manager/style.css", function ($f3) {

			echo \Template::instance()->render("/product-manager/style.css", "text/css");
			$f3->abort();
		});

		\Base::instance()->route("GET /admin/product-manager/script.js", function ($f3) {

			echo \Template::instance()->render("/product-manager/script.js", "application/javascript");
			$f3->abort();
		});
	}

	function create_product_name ($prefix, $id, $product_name, $filename) {

		$product_name = product_manager::normalizeString($product_name);
		$filename = product_manager::normalizeString($filename);

		return $prefix."_".$id."_".$product_name."_".$filename;
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

	function generateID () {
		// Product identifier
		for ($i=0; $i < 10; $i++)
		{
			$id = substr(sha1(uniqid("")), -8);

			// Check if see if there is a collision
			if (!$this->products_mapper->find(["@product_id=?", $id]))
			{	
				return $id;
				$id = null;
				$i = 99;
			}
		}

		if ($i != 100)
			\Base::instance()->error(500, "We actually collided?");
	}

	static function dashboard ($settings) {

		if (isroute($settings["routes"]))
		{
			$settings["name"] = isset($settings["name"]) ? $settings["name"] : "product_manager";
			$settings["label"] = isset($settings["label"]) ? $settings["label"] : "Products";

			return '<a href="'.\Base::instance()->BASE.'/admin/'.$settings["name"].'/" class="webworkscms_button btn-fullwidth">Edit '.$settings["label"].'</a>';
		}
	}
}