<?php

// @param  array    Same format as the global FILE
// @param  string   Target directory to save to
// @return array    
//   size: "100x100" 
//   type: "jpg"
function saveimg ($file, $directory, $options) {

	#######################################################
	################ HANDLE DEFAULT OPTIONS ###############
	#######################################################
	$defaults["size"] = "500x500";
	$defaults["crop"] = false;
	$defaults["enlarge"] = false;
	$defaults["quality"] = 100;
	$defaults["type"] = "jpg/png/gif/auto";
	$defaults["overwrite"] = true;
	$defaults["mkdir"] = true;

	check(0, (count($options) == 0), "**Default example:**", $defaults);

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
		$options["type"] = "jpg";

	if (!in_array($options["type"], ["jpg", "jpeg", "png", "gif"]))
		$options["type"] = "jpg";

	if (is_string($options["size"]))
		$options["size"] = explode("x", $options["size"]);

	$options["size"][0] = ((int)$options["size"][0] > 0) ? (int)$options["size"][0] : NULL;
	$options["size"][1] = ((int)$options["size"][1] > 0) ? (int)$options["size"][1] : NULL;

	if (in_array(NULL, $options["size"]))
		base::instance()->error(500, "Incorrect size set for image!");

	###############################################
	################ VALIDITY CHECK ###############
	###############################################
	if ($file == "" || $file == null)
		base::instance()->error(500, "File argument for saveimg NULL.");

	if (is_array($file)) {

		// When uploading multiple images often some upload fields wont
		// be set so lets just ignore those.
		if ($file["tmp_name"] == "")
			return false;

		if ($file["error"] > 0)
		{
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
	} 
	else if (is_string($file))
	{
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
			$file["name"] = pathinfo($stream)["basename"];
			$file["size"] = filesize($array["tmp_name"]);
			$file["type"] = mime_content_type2($stream);
		}
	}

	########################################################
	################ DIRECTORY HANDLER #####################
	########################################################
	if ($directory=="")
		base::instance()->error(500, "No directory provided");

	$directory = base::instance()->fixslashes($directory);
	$directory = ltrim($directory, "/");
	$directory = rtrim($directory, "/");

	$options["absolute-directory"] = getcwd()."/".$directory;

	if (!checkdir($options["absolute-directory"]))
		base::instance()->error(500, "Could not create or read directory provided for saveimg()");


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
	################ DETERMINE FILE TYPE AND NAME ####################
	#########################################################
	if (is_array($file)) {

		$pi = pathinfo($file["name"]);
		$options["tmp_name"] = $file["tmp_name"];

		if (!array_key_exists("filename", $options))
			$options["filename"] = $pi["filename"];
		else
			$options["filename"] = pathinfo($options["filename"])["filename"];

		if ($options["type"] == "auto")
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
	$GDimg = new \Image($options["tmp_name"], false, "");

	// Ensure GD loaded correctly
	if ($GDimg->data == false)
		base::instance()->error(500, "This image type ".$file_type." is not supported");

	// Process options
	if (array_key_exists("size", $options))
	{
		// Ensure size is something.
		if (($options["size"][0] + $options["size"][1]) > 0)
			$GDimg->resize($options["size"][0], $options["size"][1], $options["crop"], $options["enlarge"]);
	}

	$options["final-file"] = $options["absolute-directory"]."/".$options["filename"].".".$options["type"];

	// Save image depending on user selected file type
	switch ($options["type"])
	{	
		case "jpg":
		case "jpeg":
			$result = imagejpeg($GDimg->data($options["type"], $options["quality"]), $options["final-file"]);
		break;
		case "png":
			$result = imagepng($GDimg->data($options["type"], $options["quality"]), $options["final-file"]);
		break;
		case "gif":
			$result = imagegif($GDimg->data($options["type"], $options["quality"]), $options["final-file"]);
		break;
	}	

	if ($result == FALSE)
		base::instance()->error("Failed to save image. ```".json_encode($options, JSON_PRETTY_PRINT)."```");

	// Set back to previous memory limit
	ini_set('memory_limit', $old_memory_limit.'M');

	$GDimg->__destruct();
	unset($GDimg);

	// Return relative path to image
	return [
		"path"=>$directory."/".$options["filename"].".".$options["type"],
		"filename"=>$options["filename"].".".$options["type"],
	];
}