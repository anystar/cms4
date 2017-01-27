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
		setting_use_namespace($this->namespace);
		
		if (setting("directory") == null)
			setting("directory", "files");

		$this->directory = setting("directory");

		setting_clear_namespace();

		$this->routes(base::instance());
	}

	function routes($f3) {

		// Respond with uploaded files. Implements throttling
		$f3->route("GET /".$this->directory."/@file", function ($f3, $params) {

			if ($this->throttle == 0)
				$f3->error(503);

			$file = getcwd()."/". $this->directory."/".$params["file"];

			if (!file_exists($file)) {
				$f3->error(404);
				exit;
			}

			$mime_type = mime_content_type2($file);

			header('Content-Type:'.$mime_type);
			header('Content-Length: ' . filesize($file));
			readfile($file);

		}, null, $this->upload_speed * $this->throttle);


		if ($f3->admin)
		{
			// Receive uploaded files.
			$f3->route("POST /".$this->directory."/upload", function ($f3) {
				$this->upload_file($f3);
			});
		}

	}

	function upload_file ($f3) {
		$file = $f3->FILES["file"];

		// File cannot be named upload.
		if ($file["name"] == "upload") exit;

		// Is the image in the allowed list?
		//if (!$this->is_file_allowed($f3, $file["tmp_name"])) exit;

		// Does file already exist?
		//if (!$this->does_file_exist($f3, $file["tmp_name"])) exit;
		// Note: Need to store sha1 and search DB for quicker results.

		// Store the image!
		$this->store_file($f3, $file);
	}

	function is_file_allowed($f3, $file) {
		$mime_type = mime_content_type2($file);

		if (!in_array($mime_type, $this->allowed_types))
		{
			echo "file type not allowed";
			return false;
		}

		return true;
	}

	function does_file_exist ($f3, $file) {
		
		$sha1 = sha1_file($file["tmp_name"]);
		$result = $f3->DB->exec("SELECT filename FROM image_files WHERE sha1=UNHEX(?)", $sha1)[0];

		if ($result) {
			echo json_encode(array(
				"uploaded"=>1,
				"filename"=>$result["filename"],
				"url"=>$f3->SCHEME.'://'.$f3->HOST.$f3->BASE.'/'.$filename
			));

			return false;
		}

		$f3->filesha1 = $sha1;

		return true;
	}

	function store_file($f3, $file) {
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

		move_uploaded_file($file["tmp_name"], getcwd() . "/" . $this->directory . "/" . $filename);

		echo json_encode(array(
			"uploaded"=>1,
			"filename"=>$filename,
			"url"=>$f3->SCHEME.'://'.$f3->HOST.$f3->BASE.'/'.$this->directory.'/'.$filename
		));
	}
}