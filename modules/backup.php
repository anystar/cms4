<?php

class backup extends prefab {


	function __construct() {

		if (!admin::$signed)
			return;


		// $f3 = base::instance();

		// $f3->route("GET /admin/backup", function ($f3) {

		// 	$phar = new PharData("test.tar");

		// 	$phar->buildFromDirectory(getcwd());


		// });

	}
}