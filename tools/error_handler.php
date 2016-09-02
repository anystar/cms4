<?php

class error {

	static $db;
	static $website;

	static function construct ($db, $website) {
		error::$db = $db;
		error::$website = $website;

		if (!error::isInit())
			error::generate();
	}

	static function log($message) {

		error::$db->exec("INSERT INTO errors (error, website) VALUES (?,?)", [$message, error::$website]);
	}

	static function error_handler ($errno, $errstr, $errfile, $errline) {

		return true;
	}

	static function delete_error($id) {

	}

	static function getlist () {

		$result = error::$db->exec("SELECT * FROM errors");

		d($result);
	}

	static function isInit() {
		$result = error::$db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='errors'");
		
		if (!$result)
			return false;
		else
			return true;
	}

	static function generate() {
		error::$db->exec("CREATE TABLE 'errors' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'error' TEXT, 'website' TEXT, 'time' DATETIME DEFAULT CURRENT_TIMESTAMP);");
	}

}