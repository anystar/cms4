<?php

function check ($type, $cond, ...$messages) {

	if (!is_bool($cond))
		$cond = (!isset($cond) || $cond == "");
	
	if ($cond)
	{
		foreach ($messages as $m) 
		{
			if (is_string($m))
				$output .= "<p>".markdown::instance()->convert($m)."</p>";
			if (is_array($m))
				$output .= "<pre><code>".base::instance()->highlight(json_encode($m, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES))."</code></pre>";
		}
		base::instance()->error($type, $output);
	}

}

function d ($x=null) {
 		if (class_exists("f3"))
 		{
 			if (f3::instance()->AJAX)
			{
                echo "<pre>";
                print_r($x);
                echo "</pre>";
				exit;
			}
 		}
 		
        echo "<pre>";
 		if (class_exists("f3"))
 		{
 			if (f3::instance()->DB)
	 			print_r((f3::instance()->DB->log()));
 		}
        echo "</pre><br><br>";
        echo "<hr><br><br>";
        if ($x == null) {

        }       else {
                echo "<pre>";
                print_r($x);
                echo "</pre>";
        }
        echo "<br><br><hr><br><br>";

        if (class_exists("f3")) base::instance()->error(0); 
        exit;
}

function j ($data) {
	header('Content-Type: application/json');

	if (is_array($data))
		echo json_encode($data);
	else 
		echo json_encode(["response"=>$data]);
	
	exit;
}


function k ($x, $return = false)
{
	if (!isset($GLOBALS["krumo"]))
	{
		if (class_exists("base"))
			base::instance()->error(0, 'Krumo path not set in config.ini. Please download Krumo from <a href="https://github.com/mmucklo/krumo">GitHub</a>');
		else
			die('Krumo path not set in config.ini. Please download Krumo from <a href="https://github.com/mmucklo/krumo">GitHub</a>');
	}

	if (!is_file($GLOBALS["krumo"]))
	{
		if (class_exists("base"))
			base::instance()->error(0, 'Krumo path incorrectly set in config.ini. Please download Krumo from <a href="https://github.com/mmucklo/krumo">GitHub</a>');
		else
			die('Krumo path incorrectly set in config.ini. Please download Krumo from <a href="https://github.com/mmucklo/krumo">GitHub</a>');
	}


	require_once $GLOBALS["krumo"];

	if (class_exists("base"))
	{
		if ($return)
			krumo($x, KRUMO_RETURN);
		else
			base::instance()->error(0, krumo($x, KRUMO_RETURN));
	}
	else
		d($x);

    if (class_exists("f3")) 
   		base::instance()->error(0);
}



function writable($path) {

	// Step 0: is even something
	if (strlen($path)  == 0) base::instance()->error(0, "no path given");

	// Step 1: Lets make sure the directory owner and server own is the same
	if (function_exists('posix_getpwuid'))
	{
		// For linux
		$serverUser = exec("whoami");
		$directoryUser = posix_getpwuid(fileowner($_SERVER["SCRIPT_FILENAME"]))["name"];
	}
	else
	{
		// For windows
		$serverUser = explode("\\", exec("whoami"))[1];
		$directoryUser = get_current_user();
	}

	if ($serverUser != $directoryUser)
	{
		base::instance()->error(0, "Warning!".
		"<br><br>".
		"The webserver is running as a different user than the current folder we are in.".
		"<br><br>".
		"Current Directory: ".$folder.
		"<br><br>".
		"Directory is owned by: ".$directoryUser.
		"<br><br>".
		"While we are trying to create files and folders as: " . $serverUser.
		"<br><br>");
	}

	// Step 2: What does php respond with?
	if (!file_exists($path))
		touch($path);

	if (!is_writable($path))
		base::instance()->error(0, "PHP is reporting that ".$path." is not writable. Unfourtantly we cannot say why, sorry.");

	return true;
}

function checkdir($path) {
	
	if ($path=="") return false;

	if (file_exists($path))
	{
		writable($path);
		return true;
	}
	else
	{
		mkdir($path);
		return true;
	}
}

function checkfile($path, $hash=null) {
	writable($path);
	return true;
}

function checkhtaccess() {
	if (!is_file(getcwd()."/.htaccess"))
		file_put_contents(".htaccess", redirect_htaccess());
}

function checkdeny () {

}


function arrmerge($org, $merge) {

	foreach ($merge as $key=>$val) {

		if (!is_array($val))
			$org[$key] = $val;
		else
			$org[$key] = array_merge($org[$key], $val);
	}

	return $org;
}

function redirect_htaccess () {
return "RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-l
RewriteRule .* cms.php [L,QSA]";
}

function redirect_off_htaccess() {
return "RewriteEngine off";
}

function deny_htaccess () {
return "Deny from all";
}

function mime_content_type2($filename) {

	if ($filename == null)
		return;

	require("mime_types.php");
		
	$tmp = explode('.',$filename);
	$tmp = array_pop($tmp);
    $ext = strtolower($tmp);
    
    if (array_key_exists($ext, $mime_types)) {
        return $mime_types[$ext];
    }
    elseif (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME);
        $mimetype = finfo_file($finfo, $filename);
        finfo_close($finfo);
        return $mimetype;
    }
    else {
        return 'application/octet-stream';
    }
}


// Drupal has this implemented fairly elegantly:
// http://stackoverflow.com/questions/13076480/php-get-actual-maximum-upload-size
function file_upload_max_size() {
  $max_size = -1;

  if ($max_size < 0) {
    // Start with post_max_size.
    $max_size = parse_file_size(ini_get('post_max_size'));

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parse_file_size(ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size) {
      $max_size = $upload_max;
    }
  }
  return $max_size;
}

function parse_file_size($size) {
	if ($size == 0) return "unlimted";
	
	return $size;

  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}

function isroute($route, $verb=null)
{
	if ($route == null)
		return;

	determine_path();

	$f3 = base::instance();

	if ($verb!=null)
		if (!strtolower($f3->VERB) == strtolower($verb))
			return false;

	if (is_array($route))
		foreach ($route as $item)
			return isroute($item);

	$route = $f3->split($route);

	foreach ($route as $item)
	{
		if ($item != "/")
			$item = rtrim($item, "/");
		else
			$item = "/index";

		if (fnmatch($item, $f3->PATH))
			return true;
		else if (fnmatch($item.".html", $f3->PATH))
			return true;
		else if (fnmatch($item.".htm", $f3->PATH))
			return true;
		else if (fnmatch("/".$item, $f3->PATH))
			return true;
	}

	return false;
}

function determine_path () {
	$f3 = base::instance();

	if ($GLOBALS["path_determined"]) return;

	// Get Path and make it relative to working directory
	$path = urldecode(ltrim($f3->PATH, "/"));

	if ($pos = strpos($path, "@"))
		$path = substr($path, 0, $pos);

	$cwd = getcwd();

	// If no path, find index file.
	if ($path == "") {
		if (is_file($cwd."/index.html"))
		{
			$f3->PATH = "/index";
			$f3->FILE = "index.html";
		}
		else if (is_file($cwd."/index.htm"))
		{
			$f3->PATH = "/index";
			$f3->FILE = "index.htm";
		}

		$f3->MIME = mime_content_type2(getcwd()."/".$f3->FILE);

		$GLOBALS["path_determined"] = true;
		return;
	}

	// Check if there is an extension
	if (!preg_match("/\.[^\.]+$/i", $path, $ext)) // no file extension
	{
		// do for .html 
		if (is_file($cwd."/".$path.".html")) { $f3->FILE = $path.".html"; $f3->PATH = "/".$path; }

		// do for .htm
		else if (is_file($cwd."/".$path.".htm")) { $f3->FILE = $path.".html"; $f3->PATH = "/".$path; }
	} 
	else // file extension found
	{	
		if (is_file($cwd."/".$path)) { $f3->FILE = $path; $f3->PATH = "/".$path; }
	}

	$f3->PATH = rtrim($f3->PATH, "/");
	$f3->MIME = mime_content_type2(getcwd()."/".$f3->FILE);

	$GLOBALS["path_determined"] = true;
}

function camelCase($str, array $noStrip = [])
{
        // non-alpha and non-numeric characters become spaces
        $str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $str);
        $str = trim($str);
        // uppercase the first character of each word
        $str = ucwords($str);
        $str = str_replace(" ", "", $str);
        $str = lcfirst($str);

        return $str;
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime;
    $ago->setTimestamp($datetime);
    
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function dt ($clear = false) {

	if ($clear)
		base::instance()->clear("dtlog");

    $time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];

    $dt = base::instance()->get("dtlog");
    $dt[] = "Process Time: {$time}";
    base::instance()->set("dtlog", $dt, 60*5);
    k($dt);
    die;
}

// @param  array    Same format as the global FILE
// @param  string   Target directory to save to
// @return array    
//   size: "100x100" 
//   type: "jpg"
function saveimg ($file, $directory, $options) {

	if ($file == "" || $file == null)
		base::instance()->error(500, "File argument for saveimg NULL.");

	if ($directory=="")
		base::instance()->error(500, "No directory provided");

	$directory = base::instance()->fixslashes($directory);
	$directory = ltrim($directory, "/");
	$directory = rtrim($directory, "/");

	$options["absolute-directory"] = getcwd()."/".$directory;

	if (!checkdir($options["absolute-directory"]))
		base::instance()->error(500, "Could not create or read directory provided for saveimg()");

	// Are we passed a string
	if (is_string($file))
	{
		return;
	}

	if (is_array($file)) {

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

		$pi = pathinfo($file["name"]);

		$options["tmp_name"] = $file["tmp_name"];
		$options["filename"] = $pi["filename"];

		if ($options["type"] == "auto")
			$options["type"] = $pi["extension"];

	} else {
		base::instance()->error("saveimg has not been passed an array");
	}

	// Load up GD
	$GDimg = new \Image($options["tmp_name"], false, "");

	// Ensure GD loaded correctly
	if ($GDimg->data == false)
		base::instance()->error(500, "This image type ".$file_type." is not supported");


	// Final variables
	$file = "";
	$file_type = "";

	// Process options
	if (array_key_exists("size", $options))
	{
		if (is_string($options["size"]))
			$options["size"] = explode("x", $options["size"]);

		$options["crop"] = isset($options["crop"]) ? $options["crop"] : false;
		$options["enlarge"] = isset($options["enlarge"]) ? $options["enlarge"] : false;

		//TODO: Handle null size issues
		$options["size"][0] = ($options["size"][0] > 0) ? $options["size"][0] : null;
		$options["size"][1] = ($options["size"][1] > 0) ? $options["size"][1] : null;;

		// Ensure size is something.
		if (($options["size"][0] + $options["size"][1]) > 0)
			$GDimg->resize($options["size"][0], $options["size"][1], $options["crop"], $options["enlarge"]);
	}


	if (!isset($options["quality"]))
		$options["quality"] = 100;

	if (!isset($options["type"]))
		$options["type"] = "jpg";

	if (!in_array($options["type"], ["jpg", "jpeg", "png", "gif"]))
		$options["type"] = "jpg";


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

	// Return relative path to image
	return $directory."/".$options["filename"].".".$options["type"];
}

// @param  string  Target directory
// @param  string  Target file extension
// @return boolean True on success, False on failure

function unlink_recursive($dir_name, $ext) {

    // Exit if there's no such directory
    if (!file_exists($dir_name)) {
        return false;
    }

    // Open the target directory
    $dir_handle = dir($dir_name);

    // Take entries in the directory one at a time
    while (false !== ($entry = $dir_handle->read())) {

        if ($entry == '.' || $entry == '..') {
            continue;
        }

        $abs_name = "$dir_name/$entry";

        if (is_file($abs_name) && preg_match("/^.+\.$ext$/", $entry)) {

            if (unlink($abs_name)) {
                continue;
            }
            return false;
        }
    }

    $dir_handle->close();
    return true;

}