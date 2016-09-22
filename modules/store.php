<?php

class store extends prefab {

	function __construct() {

		if (admin::$signed)
		{

			base::instance()->route("GET /admin/store", function ($f3) {
				echo Template::instance()->render("/store/store.html");
			});
		}
	}

	function routes($f3) {

		// TODO: Insert routes for this module

	}

	function admin_routes($f3) {
		
		// TODO: Insert admin related routes for this module

	}

	static function hasInit() {
		$db = base::instance()->get("DB");

		// TODO: replace TABLE_NAME
		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='TABLE_NAME'");
		
		if (empty($result))
			return false;
	}

	static function generate() {
		$db = base::instance()->DB;

		//TODO: Insert sql to generate table structures
		$db->exec("");
	}

	static function admin_render() {

	}

	static function installed () {

	}

	static function install() {

	}
}