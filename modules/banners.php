<?php

class banners extends prefab {

	function __construct() {

		if ($this->hasInit())
		{
			$this->routes(base::instance());

			// TODO: Load up other things
		}

		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {

		// TODO: Insert routes for this module

	}

	function admin_routes($f3) {
		
		$f3->route('GET /admin/banners', 'banners::admin_render');

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
		//$db->exec("");
	}

	static function admin_render() {

		//TODO: Create html files for admin display and generation
		if (banners::hasInit())
			echo Template::instance()->render("banners/banners.html");
		else
			echo Template::instance()->render("banners/init.html");
	}
}