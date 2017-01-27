<?php
/*
	* File Manager 
	*
	* Allows the ability to upload and get files while throttling requests so not to
	* over load the server with requests.
	*
	* Accepts:
	*    - http post request with file
	*    - http get request and responds with file or 404
    *
	* Options:
	*    - max_upload_speed
			Set as kbits per seconds.
			Default: 2000kbits
		
	*    - throttle_multiplier
	*       Throttles user downloading from server. 
    *       Set as value a between 0-1 where 0 throws a 503 Service Unavailable and 1 permits full speed.
	*		Default: 1
*/

class file_manager extends prefab {

	private $namespace;
	private $directory = "files";
	private $upload_speed = 0;
	private $throttle = 1;

	function __construct($namespace) {

		$this->routes(base::instance());

	}

	function routes($f3) {

		$f3->route("GET /".$this->directory, function ($f3, $params) {

			if ($this->throttle == 0)
				$f3->error(503);

			$file = getcwd()."/". $f3->upload_path.$params["img"];

			if (!file_exists($file)) {
				$f3->error(404);
				exit;
			}

			$type = image_type_to_mime_type(exif_imagetype($file));

			header('Content-Type:'.$type);
			header('Content-Length: ' . filesize($file));
			readfile($file);

		}, null, $upload_speed * $throttle);


		$f3->route("POST /upload", function ($f3) {
			$file = $f3->FILES["upload"];

			// Is the image in the allowed list?
			if (!is_allowed_image($f3, $file)) exit;

			// Does file already exist?
			if (!does_file_exsist($f3, $file)) exit;

			// Store the image!
			store_image($f3, $file);
		});

	}

	function is_file_allowed($f3, $file) {
		$type = image_type_to_mime_type(exif_imagetype($file['tmp_name']));

		if (!in_array($type, $f3->allowed_types))
		{
			echo "file type not allowed";
			return false;
		}

		return true;
	}

	function does_file_exsist ($f3, $file) {
		
		$sha1 = sha1_file($file["tmp_name"]);
		$result = $f3->DB->exec("SELECT filename FROM image_files WHERE sha1=UNHEX(?)", $sha1)[0];

		if ($result) {
			echo json_encode(array(
				"uploaded"=>1,
				"filename"=>$result["filename"],
				"url"=>$f3->address . $result["filename"]
			));

			return false;
		}

		$f3->filesha1 = $sha1;

		return true;
	}

	function store_image($f3, $file) {
		// Make sure filename is not taken..
		$keep_checking = true;
		while ($keep_checking) {
			// Generate a very random file name
			$filename = sha1($f3->get("IP") . mt_rand(100000, 559939000));
			$filename = substr($filename, 0, mt_rand(10, 15));
			$filename .= "-" . $file["name"];

			if (!file_exists($f3->upload_path.$filename))
				$keep_checking = false;
		}

		move_uploaded_file($file["tmp_name"], $this->directory . "/" . $filename);

		echo json_encode(array(
			"uploaded"=>1,
			"filename"=>$filename,
			"url"=>$f3->address . $filename
		));
	}
}