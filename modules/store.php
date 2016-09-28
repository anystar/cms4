<?php

class store extends prefab {

	public $storeDB;

	function __construct() {
		$f3 = base::instance();

		if (admin::$signed)
		{
			$this->storeDB = new DB\SQL('sqlite:'.$f3->SETTINGS['paths']["cms"]."/"."store.db");

			$f3->route("GET /admin/store", function ($f3) {

				$f3->store['modules'] = $this->storeDB->exec("SELECT * FROM modules");

				echo Template::instance()->render("/store/store.html");
			});

			$f3->route("POST /admin/store/install", function ($f3) {
				$module = $f3->POST["module"];

				// Does class exsist?
				if (!class_exists($module))
				{
					error::log("Trying to install module which does not exist? Error has been logged.");
					return;
				}

				// Does module exsist in database?
				$result = $this->storeDB->exec("SELECT allow_multiple FROM modules WHERE module=?", [$module])[0];
				
				if (!$result)
				{
					error::log("Trying to install module which does not exist? Error has been logged.");
					return;
				}

				// If we don't allow multiple, make sure they don't already have it installed
				if ($result['allow_multiple'] == 0)
				{
					// todo..
				}

				// Lets generate a license ID


				// Store license in our records

				// First 8 is the client, // next 8 is the module name // next 16 is a randomly generated set

				// Store license in clients database



				$result = $this->storeDB->exec("SELECT id FROM licenses WHERE module=?", [$module]);

				if (!$result)
				{
					error::log("Trying to install module which does not exist? Error has been logged.");
					return;
				}

			});

		}
	}
}