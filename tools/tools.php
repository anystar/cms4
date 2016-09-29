<?php

function setting($name, $value=null, $overwrite=true) {
	$db = base::instance()->DB;

	$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
	if (!$result) return false; // There is no settings table..

	if ($value === null)
		return $db->exec("SELECT value FROM settings WHERE setting=?", $name)[0]["value"];
	else
	{
		if ($overwrite)
			set_setting($name, $value);
		else
		{
			$result = $db->exec("SELECT value FROM settigns WHERE setting=?", $name)[0]["value"];

			if (!$result)
				set_setting($name, $value);
		}
	}
}

function setting_json($name, $value=null) {

	if (!$value)
	{
		$result = setting($name);
		return json_decode($result, true);
	}
	else
	{	
		$value = json_encode($value);
		set_setting($name, $value);
	}

}

function set_setting($name, $value) {

	$result = base::instance()->DB->exec("SELECT setting FROM settings WHERE setting=?", $name);

	if ($result)
		base::instance()->DB->exec("UPDATE settings SET value=? WHERE setting=?", [$value, $name]);
	else
		base::instance()->DB->exec("INSERT INTO settings VALUES (?, ?)", [$name, $value]);
}

function d ($x=null) {

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

        if (class_exists("f3")) f3::instance()->error(0); 
        exit;
}



function writable($path) {

	// Step 0: is even something
	if (strlen($path)  == 0) {
		d("No path given");
		exit;
	}

	// Step 1: Lets make sure the directory owner and server own is the same
	$serverUser = exec("whoami");
	$directoryUser = posix_getpwuid(fileowner($_SERVER["SCRIPT_FILENAME"]))["name"];

	if ($serverUser != $directoryUser)
	{
		echo "Warning!";
		echo "<br><br>";
		echo "The webserver is running as a different user than the current folder we are in.";
		echo "<br><br>";
		echo "Current Directory: ".$folder;
		echo "<br><br>";
		echo "Directory is owned by: ".$directoryUser;
		echo "<br><br>";
		echo "While we are trying to create files and folders as: " . $serverUser;;
		echo "<br><br>";
		die;
	}

	// Step 2: What does php respond with?
	if (!file_exists($path)) {
		touch($path);
	}

	if (!is_writable($path)) {
		echo "PHP is reporting that ".$path." is not writable.";
		echo "Unfourtantly we cannot say why, sorry.";
		exit;
	}

	return true;
}

function checkdir($path) {
	
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