<?php

// @param  array    Same format as the global FILE
// @param  string   Target directory to save to
// @return array    
//   size: "100x100" 
//   type: "jpg"
function saveimg ($file, $directory, $options, &$fill=null) {

	#######################################################
	################### HANDLE ARGUMENTS ##################
	#######################################################
	if ($directory == "" || $directory == null)
	{
		if (array_key_exists("directory", $options)) 
		{
			$directory = $options["directory"];
		}
	}


	#######################################################
	################ HANDLE DEFAULT OPTIONS ###############
	#######################################################
	$defaults["filename"] = "";
	$defaults["size"] = "1500x1500";
	$defaults["thumbnail"] = [
		"size"=>"500x500",
		"crop"=>true,
		"enlarge"=>true,
		"quality"=>100,
		"subfolder"=>"thumbs"
	];

	$defaults["crop"] = false;
	$defaults["enlarge"] = false;
	$defaults["quality"] = 100;
	$defaults["type"] = "auto";
	$defaults["placeholder"] = "Placeholder Text or False for no placeholder";
	$defaults["overwrite"] = true;
	//$defaults["keep-original"] = true;

	check(0, (count($options) == 0), "**Default example:**", $defaults);

	if (isset($options["filename"]))
		if (empty($options["filename"]))
			unset($options["filename"]);

	if (!isset($options["crop"]))
		$options["crop"] = $defaults["crop"];

	$options["crop"] = (bool)$options["crop"];

	if (!isset($options["enlarge"]))
		$options["enlarge"] = $defaults["enlarge"];

	$options["enlarge"] = (bool)$options["enlarge"];

	if (!isset($options["quality"]))
		$options["quality"] = $defaults["quality"];

	$options["quality"] = (int)$options["quality"];

	if (!isset($options["overwrite"]))
		$options["overwrite"] = $defaults["overwrite"];

	$options["overwrite"] = (bool)$options["overwrite"];

	if (!isset($options["mkdir"]))
		$options["mkdir"] = $defaults["mkdir"];

	$options["mkdir"] = (bool)$options["mkdir"];

	if (!isset($options["type"]))
		$options["type"] = "auto";

	if (!in_array($options["type"], ["jpg", "jpeg", "png", "gif"]))
		$options["type"] = "auto";

	if (is_string($options["size"]))
		$options["size"] = explode("x", $options["size"]);

	if (isset($options["thumbnail"])) {

		if (!isset($options["thumbnail"]["size"]))
			base::instance()->error("No size set for thumbnail property");
		else 
		{
			if (is_string($options["thumbnail"]["size"]))
			{
				$options["thumbnail"]["size"] = explode("x", $options["thumbnail"]["size"]);

				$options["thumbnail"]["size"][0] = ((int)$options["thumbnail"]["size"][0] > 0) ? (int)$options["thumbnail"]["size"][0] : NULL;
				$options["thumbnail"]["size"][1] = ((int)$options["thumbnail"]["size"][1] > 0) ? (int)$options["thumbnail"]["size"][1] : NULL;
			}
		}

		if (!isset($options["thumbnail"]["crop"]))
			$options["thumbnail"]["crop"] = $defaults["thumbnail"]["crop"];

		if (!isset($options["thumbnail"]["enlarge"]))
			$options["thumbnail"]["enlarge"] = $defaults["thumbnail"]["enlarge"];

		if (!isset($options["thumbnail"]["quality"]))
			$options["thumbnail"]["quality"] = $defaults["thumbnail"]["quality"];

		if (!isset($options["thumbnail"]["subfolder"]))
			$options["thumbnail"]["subfolder"] = $defaults["thumbnail"]["subfolder"];

		$options["thumbnail"]["subfolder"] = ltrim($options["thumbnail"]["subfolder"], "/");
		$options["thumbnail"]["subfolder"] = rtrim($options["thumbnail"]["subfolder"], "/");
	}


	if (isset($options["size"]))
	{
		if (strtolower($options["size"][0]) == "auto")
			$options["size"][0] = NULL;

		if (strtolower($options["size"][1]) == "auto")
			$options["size"][1] = NULL;

		$options["size"][0] = ((int)$options["size"][0] > 0) ? (int)$options["size"][0] : NULL;
		$options["size"][1] = ((int)$options["size"][1] > 0) ? (int)$options["size"][1] : NULL;
	}

	##################################################
	################ MULTI FILE UPLOAD ###############
	##################################################
	// Handle name='image[]' requests.

	if (is_array($file))
	{
		if (array_key_exists("tmp_name", $file))
		{
			if (is_array($file["tmp_name"]))
			{
				foreach ($file as $x=>$item) {
					foreach ($item as $y=>$value)
					{
						$files[$y][$x] = $value;
					}
				}

				foreach ($files as $file)
				{
					if ($file["tmp_name"] != "")
						$fill[] = saveimg($file, $directory, $options);
				}

				return true;
			}
		}

		// if (!array_key_exists("error", $file))
		// {
		// 	foreach ($file as $f)
		// 	{
		// 		$fille[] = saveimg($f, $directory, $options);
		// 	}

		// 	return true;
		// }
	}

	###############################################
	################ VALIDITY CHECK ###############
	###############################################

	if ($file == "" || $file == null)
	{
		if ($options["placeholder"])
			saveplaceholder(null, $directory, $options);
		else
			base::instance()->error(500, "File argument for saveimg is NULL.");
	}

	if (is_array($file)) {

		// Check if we have a filename
		if (!array_key_exists("name", $file) || $file["name"] == "")
		{
			if (isset($options["filename"]))
				$file["name"] = $options["filename"];
			else
			{
				$file["name"] = "noname.png";
				$options["overwrite"] = false;
			}
		}

		// Ensure file actually exists
		if (!array_key_exists("tmp_name", $file))
		{
			if ($options["placeholder"])
				saveplaceholder($file["name"], $directory, $options);

			return;
		}

		// Ensure file actually exists
		if (!file_exists($file["tmp_name"]))
		{
			if ($options["placeholder"])
				saveplaceholder($file["name"], $directory, $options);

			return;
		}

		// If we have an error
		if (!array_key_exists("error", $file))
		{
			if ($options["placeholder"])
			{
				saveplaceholder($file["name"], $directory, $options);
				return;
			}
		}

		if ($file["error"] > 0)
		{

			if ($file["error"] == 4) {
				if ($options["placeholder"])
				{
					saveplaceholder($file["name"], $directory, $options);
					return;
				}
			}

			$phpFileUploadErrors = array(
			    0 => 'There is no error, the file uploaded with success',
			    1 => 'The uploaded file exceeds the maximum upload size',
			    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
			    3 => 'The uploaded file was only partially uploaded',
			    4 => 'No file was uploaded',
			    6 => 'Missing a temporary folder to upload into',
			    7 => 'Failed to write file to disk.',
			    8 => 'An extension stopped the file upload.',
			);

			// We should always handle this client side so throw a serious error
			base::instance()->error(500, "Error uploading file ". $phpFileUploadErrors[$file["error"]]);
		}

		// When uploading multiple images often some upload fields wont
		// be set so lets just ignore those.
		if ($file["tmp_name"] == "")
			return null;
	}
	else if (is_string($file))
	{
		// Replace spaces without convert slashes
		$file = str_replace(" " , "%20", $file);

		$stream = $file;
		$file = array();

		if (filter_var($stream, FILTER_VALIDATE_URL))
		{
			$data = file_get_contents($stream);

			if ($data === false)
				return false;

			$tmp = tmpfile();
			$file["tmp_name"] = stream_get_meta_data($tmp)["uri"];
			fwrite($tmp, $data);

			// Put it down as a temp file and generate proper array structure
			$file["name"] = $options["filename"];
			$file["size"] = filesize($array["tmp_name"]);
			$file["type"] = mime_content_type2($stream);
		}
	}

	########################################################
	################ DIRECTORY HANDLER #####################
	########################################################
	if ($directory=="")
		base::instance()->error(500, "No directory provided");

	// Check if it is a file or a directory
	if (is_file($directory))
	{
		$pi = pathinfo($directory);
		$file["name"] = $pi["basename"];
		$directory = $pi["dirname"];
	}

	if ($directory[0] === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i',$directory) > 0)
	{
		$options["absolute-directory"] = $directory;
	}
	else 
	{
		// Convert to absolute
		$directory = base::instance()->fixslashes($directory);
		$directory = ltrim($directory, "/");
		$directory = rtrim($directory, "/");

		// Make directory absolute
		$options["absolute-directory"] = getcwd()."/".$directory;
	}

	if (!checkdir($options["absolute-directory"]))
		base::instance()->error(500, "Could not create or read directory provided for saveimg()");

	if (array_key_exists("thumbnail", $options))
		if (!checkdir($options["absolute-directory"]."/".$options["thumbnail"]["subfolder"]))
			base::instance()->error(500, "Could not create or read subfolder directory provided for saveimg()");


	#########################################################
	################ HANDLE MEMORY ALLOCATION ###############
	#########################################################
	//initializing variables
	$maxMemoryUsage = 2048;

	$width = 0;
	$height = 0;
	$old_memory_limit = ini_get('memory_limit');

	// Convert to bytes
	$temp = trim($old_memory_limit);
	$last = strtolower($temp[strlen($temp)-1]);
	switch($last) {
	    // The 'G' modifier is available since PHP 5.1.0
	    case 'g':
	        $temp *= 1024;
	    case 'm':
	        $temp *= 1024;
	    case 'k':
	        $temp *= 1024;
	}

	$old_memory_limit = $temp / 1024 / 1024;
	$temp = null;

	// Getting the image width and height
	list($width, $height) = getimagesize($file["tmp_name"]);

	// Calculating the needed memory
	$new_memory_limit = $new_memory_limit + floor(($width * $height * 4 * 1.5 + 1048576) / 1048576);

	// Prevent going over the hard limit
	if ($new_memory_limit > $maxMemoryUsage)
	    base::instance()->error(500, "We will run out of memory if we process this image! Estimated size: ".$new_memory_limit."M");

	// Updating the default value
	if ($new_memory_limit > $old_memory_limit)
		ini_set('memory_limit', $new_memory_limit.'M');

	$new_memory_limit = 0;

	#########################################################
	############ DETERMINE FILE TYPE AND NAME ###############
	#########################################################
	if (is_array($file)) {

		$pi = pathinfo($file["name"]);

		$options["tmp_name"] = $file["tmp_name"];

		if (!array_key_exists("filename", $options))
			$options["filename"] = $pi["filename"];
		else
			$options["filename"] = pathinfo($options["filename"])["filename"];

		if ($options["type"] == "auto" || $options["type"] == "" || !array_key_exists("type", $options))
			$options["type"] = $pi["extension"];

		if ($options["overwrite"] === false) {

			if (is_file($options["absolute-directory"]."/".$options["filename"].".".$options["type"]))
			{	
				// Find next available filename
				$increment=0;
				while (is_file($options["absolute-directory"]."/".$options["filename"]."_".$increment.".".$options["type"]))
					$increment++;
				
				$options["filename"] .= "_".$increment;
			}
		}
	}

	#########################################################
	################ LOAD IMAGE INTO GD #####################
	#########################################################

	// Load up GD

	$GDimg = @new Image($options["tmp_name"], false, "");		

	if ($GDimg->data == false) 
	{
		// It's likely not an image. Lets just upload it as a file.
		copy($options["tmp_name"], $options["absolute-directory"]."/".$options["filename"].".".$options["type"]);
	}
	else
	{

		// Ensure GD loaded correctly
		if ($GDimg->data == false)
			base::instance()->error(500, "This image type ".$file_type." is not supported");

		// Fix EXIF orientation
		image_fix_orientation($GDimg->data, $options["tmp_name"]);

		// Resize Image
		if (array_key_exists("size", $options))
		{
			// Ensure size is something.
			if (($options["size"][0] + $options["size"][1]) > 0)
				$GDimg->resize($options["size"][0], $options["size"][1], $options["crop"], $options["enlarge"]);
		}

		$options["final-file"] = $options["absolute-directory"]."/".$options["filename"].".".$options["type"];

		// Save image depending on user selected file type
		switch (strtolower($options["type"]))
		{	
			case "jpg":
			case "jpeg":
				$result = imagejpeg($GDimg->data(), $options["final-file"], $options["quality"]);
			break;
			case "png":
				$result = imagepng($GDimg->data(), $options["final-file"]);
			break;
			case "gif":
				$result = imagegif($GDimg->data(), $options["final-file"]);
			break;
		}

		check(500, $result===false, "Failed to save image!", "**Settings passed**", $options);

		unset($result);

		$GDimg->__destruct();
		unset($GDimg);

		// Generate Thumbnail
		if (array_key_exists("thumbnail", $options))
		{
			// Load up GD again for thumbnail
			$GDimg = new \Image($options["tmp_name"], false, "");

			// Fix EXIF orientation
			image_fix_orientation($GDimg->data, $options["tmp_name"]);

			// Ensure size is something.
			if (($options["thumbnail"]["size"][0] + $options["thumbnail"]["size"][1]) > 0)
				$GDimg->resize($options["thumbnail"]["size"][0], $options["thumbnail"]["size"][1], $options["thumbnail"]["crop"], $options["thumbnail"]["enlarge"]);

			$options["thumbnail"]["final-file"] = $options["absolute-directory"]."/".$options["thumbnail"]["subfolder"]."/thumb_".$options["filename"].".".$options["type"];

			// Save image depending on user selected file type
			switch (strtolower($options["type"]))
			{	
				case "jpg":
				case "jpeg":
					$result = imagejpeg($GDimg->data($options["type"], $options["thumbnail"]["quality"]), $options["thumbnail"]["final-file"]);
				break;
				case "png":
					$result = imagepng($GDimg->data($options["type"], $options["thumbnail"]["quality"]), $options["thumbnail"]["final-file"]);
				break;
				case "gif":
					$result = imagegif($GDimg->data($options["type"], $options["thumbnail"]["quality"]), $options["thumbnail"]["final-file"]);
				break;
			}

			if ($result == FALSE)
				base::instance()->error("Failed to save image. ```".json_encode($options, JSON_PRETTY_PRINT)."```");
			else {
				$options["thumbnail"]["path"] = $directory."/".$options["thumbnail"]["subfolder"]."/thumb_".$options["filename"].".".$options["type"];
				$options["thumbnail"]["filename"] = $options["filename"].".".$options["type"];
			}

			$GDimg->__destruct();
			unset($GDimg);
		}

		unset($result);

		// Set back to previous memory limit
		ini_set('memory_limit', $old_memory_limit.'M');
	}

	

		if (isset($tmp))
			fclose($tmp);
		else
		{
			if (!array_key_exists("testing", $options))
				if ($options["testing"] != "true")
					unlink($options["tmp_name"]);
		}

	$options["path"] = $directory."/".$options["filename"].".".$options["type"];
	$options["filename"] = $options["filename"].".".$options["type"];

	if (is_array($fill))
		$fill[] = $options;

	return $options;
}


function saveplaceholder ($filename, $directory, $options) {

	if ($filename == null || $filename == "")
		$filename = "placeholder_".uniqid().".png";

	$placeholder_path = "https://placeholdit.imgix.net/~text?txtsize=33&txt=".urlencode($options["placeholder"])."&w=".$options["size"][0]."&h=".$options["size"][1];

	// Ensure size is something.
	if (($options["size"][0] + $options["size"][1]) > 0)
		copy($placeholder_path, $directory."/".$filename);

	if (array_key_exists("thumbnail", $options))
	{
		$thumb_placeholder_path = "https://placeholdit.imgix.net/~text?txtsize=25&txt=".urlencode($options["placeholder"])."&w=".$options["thumbnail"]["size"][0]."&h=".$options["thumbnail"]["size"][1];

		if (($options["thumbnail"]["size"][0] + $options["thumbnail"]["size"][1]) > 0)
			copy($thumb_placeholder_path, $directory."/".$options["thumbnail"]["subfolder"]."/thumb_".$filename);
	}
}


function image_fix_orientation(&$image, $filename) {

    // Ensure function exists
    if (!function_exists("exif_read_data")) return;

    $exif = @exif_read_data($filename);

    if (!empty($exif['Orientation'])) {
        switch ($exif['Orientation']) {
            case 3:
                $image = imagerotate($image, 180, 0);
                break;

            case 6:
                $image = imagerotate($image, -90, 0);
                break;

            case 8:
                $image = imagerotate($image, 90, 0);
                break;
        }
    }
}