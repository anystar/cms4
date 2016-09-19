<?php

class ckeditor extends prefab {

	function __construct() {

		$this->routes(base::instance());


		if (admin::$signed)
			$this->admin_routes(base::instance());

		// Don't load if on admin path
		if (!preg_match("/\/admin(.*)/", base::instance()->PATH))
		{
			$this->inject_inline_editor();
		}
	}

	function routes($f3) {

		$f3->route('GET /ckeditor/images/inlinesave-color.svg', function () {
			echo View::instance()->render("/ckeditor/images/save-color.png", "image/png");
		});

		$f3->route('GET /ckeditor/images/inlinesave-label.svg', function () {
			echo View::instance()->render("/ckeditor/images/save-label.png", "image/png");
		});

		$f3->route('GET /ckeditor/cms_save.js', function () {
			echo Template::instance()->render("/ckeditor/js/cms_save.js", "application/javascript");
		});

	}

	function admin_routes($f3) {
		// TODO: Insert admin related routes for this module

		$f3->route("GET /admin/ckeditor/contents.css", function () {
			echo Template::instance()->render("/ckeditor/css/contents.css", "text/stylesheet");
		});

	}

	function inject_inline_editor () {
		$f3 = base::instance();

		// Get list of template variables
		

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