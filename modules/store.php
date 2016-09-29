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

				// Get modules already installed so we can filter out
				// modules from that store that cannot be installed more than once.
				$result = $f3->DB->exec("SELECT module FROM licenses");

				// flatten result
				foreach ($result as $module)
					$installed[] = $module["module"];

					
				// only check if user has installed modules
				if ($installed)
				{	
					// Loop through everything in store
					foreach ($f3->store['modules'] as $key=>$module)
					{	
						// Only check if module does not allow multiples
						if ($module["allow_multiple"] == 0) {

							if (in_array($module["module"], $installed)) {
								unset($f3->store['modules'][$key]);
							}
						}
					}
				}

				echo Template::instance()->render("/store/store.html");
			});


			$f3->route("GET /admin/store/@module", function ($f3, $params) {

				$f3->module = $module = $this->storeDB->exec("SELECT * FROM modules WHERE module=?", $params["module"])[0];
			
				// Is this a once off install, lets just install it.
				if ($module["allow_multiple"] === 0) 
				{
					if (!$this->check_if_already_owned($module["module"])) {

						$this->install($module["module"], $module["name"], $module["module"]);
						return;
					}
				}

				echo Template::instance()->render("/store/purchase.html");

			});

			$f3->route("POST /admin/store/@module/install", function ($f3, $params) {
				$module = $params["module"];
				$module_name = $f3->POST["module_name"];
				$namespace = $f3->POST["namespace"];

				// Does module exist?
				if (!class_exists($module))
					$f3->errors["message"] = "You are trying to install a module which does not exsist?";

				// Does module exsist in database?
				if (!$this->storeDB->exec("SELECT allow_multiple, name FROM modules WHERE module=?", [$module])[0])
					$f3->errors["message"] = "You are trying to install a module which does not exsist?";

				// Validate module
				if (strlen($module_name) == 0)
					$f3->errors["module_name"] = "No name provided";

				else if (strlen($module_name) > 30)
					$f3->errors["module_name"] = "Name too long";

				// Validate namespace

				if (strlen($namespace) == 0)
					$f3->errors["namespace"] = "No namespace provided";		
				else if (!preg_match("/^[A-Za-z_-]+$/", $namespace)) {
					$f3->errors["namespace"] = "Name cannot contain special characters";
				}

				if (count($f3->errors) == 0)
					$this->install($module, $module_name, $namespace);
				else
					echo Template::instance()->render("/store/purchase.html");

			});

		}
	}

	function install ($module, $module_name, $namespace) {
		$f3 = base::instance();

		// Does class exsist?
		if (!class_exists($module))
		{
			error::log("Trying to install module which does not exist? Error has been logged.");
			return;
		}

		// Does module exsist in database?
		$result = $this->storeDB->exec("SELECT allow_multiple, name FROM modules WHERE module=?", [$module])[0];

		if (!$result)
		{
			error::log("Trying to install module which does not exist? Error has been logged.");
			return;
		}

		// If we don't allow multiples of this module, 
		// make sure they don't already have it installed
		if ($result['allow_multiple'] == 0)
		{
			// todo..
		}

		// Lets generate a license ID
		
		// For backward compatability so we can use random_bytes
		require_once $f3->SETTINGS["paths"]["random_compat"] . "/lib/random.php";

		// First 8 is the client, // next 8 is the module name // next 16 is a randomly generated set
		$client_key = substr(sha1("darklocker@gmail.com"), 0, 8);
		$module_key = substr(sha1($module), 0, 8);
		$random_key = bin2hex(random_bytes(8));

		// Its nice to have pretty things
		$key = $client_key."-".$module_key."-".substr($random_key, 0, 8)."-".substr($random_key, 8, 8);

		// Store license in our records
		//$this->storeDB->exec("INSERT INTO licenses (module, account, key) VALUES (?, ?, ?)", [$module, 0, $key]);

		// Store license in clients database
		$f3->DB->exec("INSERT INTO licenses (module, name, namespace, key) VALUES (?, ?, ?, ?)", [$module, $module_name, $namespace, $key]);

		// Initiate install for module.
		$m = new $module($namespace);
		
		// Install and let install handle routing behaviour.
		$m->install();
	}

	function check_if_already_owned ($module) {
		$db = base::instance()->DB;
		$result = $db->exec("SELECT id FROM licenses WHERE module=?", $module);

		if ($result)
			return true;
		else
			return false;

	}
}