<?php

class script_editor {

    function __construct($settings) {

        $this->routes(base::instance());
    }

	function routes($f3) {

		$f3->route("GET /admin/script-editor", function ($f3) {
            
            // Load Settings JSON
            $settings = json_decode(file_get_contents(".cms/settings.json"), true);


            $f3->scripts = $settings["scripts"];

			echo Template::instance()->render("/script-editor/index.html", "text/html");
		});


	}

	static function dashboard ($settings) {
        		
	}
}