<?php

class ckeditor extends prefab {

	function __construct() {

		$this->routes(base::instance());


		if (admin::$signed)
			$this->admin_routes(base::instance());
	}

	function routes($f3) {

	}

	function admin_routes($f3) {
		
		// TODO: Insert admin related routes for this module

		$f3->route("GET /admin/ckeditor/contents.css", function () {
			echo Template::instance()->render("/ckeditor/css/contents.css", "text/stylesheet");
		});

	}

	// static function hasInit() {
	// 	$db = base::instance()->get("DB");

	// 	// TODO: replace TABLE_NAME
	// 	$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='TABLE_NAME'");
		
	// 	if (empty($result))
	// 		return false;
	// }

	// static function generate() {
	// 	$db = base::instance()->DB;

	// 	//TODO: Insert sql to generate table structures
	// 	$db->exec("");
	// }

	// static function admin_render() {

	// 	//TODO: Create html files for admin display and generation
	// 	if ($this::instance()->hasInit())
	// 		echo Template::instance()->render("module_name/module.html");
	// 	else
	// 		echo Template::instance()->render("module_name/module.html");
	// }
}