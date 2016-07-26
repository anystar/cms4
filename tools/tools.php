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