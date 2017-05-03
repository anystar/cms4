<?php

class dropimg extends prefab {

	function __construct() {

		$f3 = base::instance();


		if (admin::$signed) {


			$f3->route("POST /admin/dropimg/upload", function ($f3) {

				// Check if current image exists
				if (!file_exists(getcwd()."/".$f3->POST["filename"]))
				{
					echo "HUH, file does not exist??";
					die;
				}

				$file = $f3->POST["filename"];
				$ext = pathinfo(getcwd()."/".$file)["extension"];
				$size = explode("x", $f3->POST["size"]);

				// Resize and overwrite 
				$this->resize($f3->FILES["file"]["tmp_name"], $file, $size[0], $size[1], $ext);
			});

			$f3->set("dropimg", Template::instance()->render("dropimg/init.html", null));
		}


		Template::instance()->extend("dropimg", function ($args) {

			$string .= '<?php if (admin::$signed) {?>';
			$string .= "<img ";

			$classFilled = false;
			foreach ($args["@attrib"] as $key=>$value) {

				if ($key=="resize")
				{
					$string .= 'data-size="'.$value.'" ';
				} else if ($key=="class") {
					$string .= 'class="'.$value.' imgdropzone" ';
					$classFilled = true;
				} else {
					$string .= $key.'="'.$value.'" ';
				}
			}

			if (!$classFilled)
				$string .= 'class="imgdropzone" ';

			$string .= 'id="'.uniqid("dropimg_").'"';
			$string .= ">";
			$string .= "<?php } ?>";
			
			$string .= '<?php if (!admin::$signed) {?>';
			$string .= "<img ";

			foreach ($args["@attrib"] as $key=>$value) {

				if ($key != "resize")
				{
					$string .= $key.'="'.$value.'" ';
				}
			}

			$string .= ">";
			$string .= "<?php } ?>";
			
			return $string;
		});

	}


	function resize ($image, $save_to, $width, $height, $file_type) {

		// Pull image off the disk into memory
		$temp_image = new Image($image, false, "/"); // Image(filename, filehistory, path)

		// Make sure that width and height are set before resizing image
		if (($width*$height) > 0)
		{
			// Resize image using F3's image plugin
			$temp_image->resize($width, $height, true, true); // resize(width, height, crop, enlarge)
		}

		// Save image depending on user selected file type
		switch ($file_type)
		{	
			case "jpg":
			case "jpeg":
				imagejpeg($temp_image->data($file_type, 100), $save_to);
			break;
			case "png":
				imagepng($temp_image->data($file_type, 100), $save_to);
			break;
			case "gif":
				imagegif($temp_image->data($file_type, 100), $save_to);
			break;
		}
	}
}