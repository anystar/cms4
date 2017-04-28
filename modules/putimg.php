<?php

class putimg extends prefab {

	function __construct() {

		$f3 = base::instance();

		$f3->route("POST /admin/putimg/upload", function ($f3) {

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

	}

	function routes($f3) {
		// Insert routes for this module

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