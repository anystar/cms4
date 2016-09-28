<?php

class store extends prefab {

	function __construct() {

		if (admin::$signed)
		{

			base::instance()->route("GET /admin/store", function ($f3) {

				$db = new DB\SQL('sqlite:'.$f3->SETTINGS['paths']["cms"]."/"."store.db");

				$f3->store['modules'] = $db->exec("SELECT * FROM modules");

				echo Template::instance()->render("/store/store.html");
			});
		}
	}
}