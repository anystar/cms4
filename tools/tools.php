<?php

function set_configurable($f3, $config) {
	
	d("hit");
}

function config($name, $value=null) {
	$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");

	if (!$result) return false;

	if (!$value)
		return base::instance()->DB->exec("SELECT value FROM settings WHERE setting=?", $name)[0]["value"];
	else
		set_config($name, $value);
}

function config_json($name, $value=null) {

	if (!$value)
	{
		$result = config($name);
		return json_decode($result, true);
	}
	else
	{	
		$value = json_encode($value);
		set_config($name, $value);
	}

}

function set_config($name, $value) {
	$result = config($name);

	if ($result)
		base::instance()->DB->exec("UPDATE settings SET value=? WHERE setting=?", [$value, $name]);
	else
		base::instance()->DB->exec("INSERT INTO settings VALUES (?, ?)", [$name, $value]);
}


 function d ($x=null) {

        echo "<pre>";
        if (f3) print_r((f3::instance()->DB->log()));
        echo "</pre><br><br>";
        echo "<hr><br><br>";
        if ($x == null) {

        }       else {
                echo "<pre>";
                print_r($x);
                echo "</pre>";
        }
        echo "<br><br><hr><br><br>";

        if (f3)
	        f3::instance()->error(0);

        die;
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