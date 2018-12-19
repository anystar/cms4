<?php

class script_editor {

    function __construct($settings) {

        $this->routes(base::instance());
    }

	function routes($f3) {

		$f3->route("GET /admin/script-editor", function ($f3) {
            
            $f3->scripts = $f3->SETTINGS["scripts"];

			echo Template::instance()->render("/script-editor/index.html", "text/html");
		});

		$f3->route("GET /admin/script-editor/settings/@name", function ($f3, $params) {
			
			$f3->script = $f3->SETTINGS["scripts"][$params["name"]];
			$f3->name = $params["name"];

			echo Template::instance()->render("/script-editor/settings.html", "text/html");
		});

		$f3->route("POST /admin/script-editor/settings/@name", function ($f3, $params) {
			
			setting("scripts.".$params["name"], $f3->POST);
			
			$f3->script = $f3->SETTINGS["scripts"][$params["name"]];
			$f3->name = $params["name"];

			echo Template::instance()->render("/script-editor/settings.html", "text/html");
		});

	}

	static function dashboard ($settings) {
        		
	}
}