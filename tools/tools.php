<?php

function config($name, $value=null) {

	if (!$value)
		return base::instance()->DB->exec("SELECT value FROM settings WHERE setting=?", $name)[0]["value"];
	else
		set_config($name, $value);
}

function set_config($name, $value) {

	// Does the value exist?
	$result = config($name);

	if ($result)
		base::instance()->DB->exec("UPDATE settings SET value=? WHERE setting=?", [$name, $value]);
	else
		base::instance()->DB->exec("INSERT INTO settings VALUES (?, ?)", [$name, $value]);
}