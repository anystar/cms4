<?php

class debug extends prefab {

	function __construct() {
		$f3 = base::instance();

		if (admin::$signed)
		{
			$f3->set("debug.json_hive", json_encode($f3->hive()));

			$inlinecode = Template::instance()->render("/debug/debug.html");
			$f3->concat("admin", $inlinecode);
		}
	}
}