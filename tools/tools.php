<?php

function setting($name, $value=null) {
	$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
	if (!$result) return false; // There is no settings table..

	if (!$value)
		return base::instance()->DB->exec("SELECT value FROM settings WHERE setting=?", $name)[0]["value"];
	else
		set_setting($name, $value);
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
	// Lets create a settings table..

	$result = setting($name);

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
		echo "No path given";
		exit;
	}

	// Step 1: Lets make sure the server is running as the user
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
	}

	// Step 2: What does php respond with?
	if (!file_exists($path)) {
		echo $path . "does not exsist?";
		exit;
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

	if (!$hash)
		return true;

	$tmpHash = sha1_file($path);

	if ($tmpHash == $hash)
		return true;
	else
	{
		echo "<pre>";
		echo "File is different than what is expected?";
		echo "<br><br>";
		echo $path;
		echo "<br><br>";
		echo "Expected sha1: ".$hash;
		echo "<br><br>";
		echo "Got sha1:      ".$tmpHash;
		echo "</pre>";
		die;
	}
}

function checkhtaccess() {

	$expectingHash = "f5122dec22bb87e67245acfad2882f4ab4772fea";
	$tmpHash = sha1_file(".htaccess");

	if ($tmpHash == $expectingHash)
		return true;

	echo "<pre>";
	echo "File is different than what is expected?";
	echo "<br><br>";
	echo $path;
	echo "<br><br>";
	echo "Expected sha1: ".$hash;
	echo "<br><br>";
	echo "Got sha1:      ".$tmpHash;
	echo "</pre>";
	die;
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


function htaccess_example() {
echo <<<EOF
<strong>.htaccess does not exist. Please use this snippet to create a .htaccess folder in the client directory.</strong>

<p>
<textarea cols=50 rows=10>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-l
RewriteRule .* cms.php [L,QSA]
RewriteRule .* cms.php [L,QSA]
</textarea>
</p>

<strong>This snippet redirects all requests to cms.php. If you want a folder accessable put this .htaccess file in each folder.</strong>
<p>
<textarea cols=50 rows=10>
RewriteEngine off
</textarea>
</p>

<strong>Hint: CMS modules will attempt to create .htaccess for you where they can.</strong>
EOF;

	exit;
}