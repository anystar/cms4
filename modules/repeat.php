<?php

class repeat extends prefab {

	private $routes;
	private $namespace;

	function __construct($namespace) {

		$this->snippets[] = ["file" => "_blog.html", "name" => "Blog"];
		$this->snippets[] = ["file" => "_menu.html", "name" => "Menu"];
		$this->snippets[] = ["file" => "_testimonials.html", "name" => "Testimonials"];

		$this->namespace = $namespace;
		$this->routes = base::instance()->split(setting($namespace."_routes"));

		if (isroute("/admin/".$namespace)) {
			if (!$this->check_install())
				base::instance()->reroute("/admin/".$this->namespace."/setup");
		}

		if (isroute($this->routes)) {

			$result = base::instance()->DB->exec("SELECT id, data FROM {$this->namespace} ORDER BY rowOrder");

			foreach ($result as $row)
			{
				$temp = json_decode($row["data"], true);

				foreach ($temp as $t)
					$temp[] = $t;

				$temp["id"] = $row["id"];

				$data[] = $temp;
			}

			base::instance()->set($namespace, $data);
		}

		if (admin::$signed)
		{
			$this->setup_routes(base::instance());
			$this->admin_routes(base::instance());
		}


		$this->routes(base::instance());
	}

	function routes($f3) {
		// Insert routes for this module

	}

	function admin_routes($f3) {

		$f3->route(['GET /admin/'.$this->namespace, 'GET /admin/'.$this->namespace.'/update' ], function ($f3) {
			$f3->namespace = $this->namespace;

			$data = $f3->DB->exec("SELECT id, data FROM {$this->namespace} ORDER BY rowOrder");

			foreach ($data as $row)
			{
				$temp = json_decode($row["data"], true);

				foreach ($temp as $t)
					$temp[] = $t;

				$temp["id"] = $row["id"];

				$f3->data[] = $temp;
			}

			$f3->action = "Create";

			if (isroute('/admin/'.$this->namespace.'/update'))
			{
				$row = $f3->DB->exec("SELECT * FROM {$this->namespace} WHERE id=? ORDER BY rowOrder", $f3->GET["data_id"]);

				if (count($row) > 0)
				{	
					$row = $row[0];

					$temp = json_decode($row["data"], true);

					foreach ($temp as $t)
						$temp[] = $t;

					$temp["id"] = $row["id"];

					$f3->values = $temp;

					$f3->action = "Update";
				}
			}

			$f3->snippet = setting($this->namespace."_snippet");

			$f3->module_name = base::instance()->DB->exec("SELECT name FROM licenses WHERE namespace=?", [$this->namespace])[0]["name"];

			echo Template::instance()->render("/repeat/repeat.html");
		});

		$f3->route('POST /admin/'.$this->namespace.'/addupdate', function ($f3) {

			// Create
			if ($f3->POST["data_id"] == null) {

				unset($f3->POST["data_id"]);
				$json = json_encode($f3->POST);

				$f3->DB->exec("INSERT INTO {$this->namespace} (data) VALUES (?)", $json);
				$id = $f3->DB->lastInsertId();
				$f3->DB->exec("UPDATE {$this->namespace} SET rowOrder=? WHERE id=?", [$id,$id]);
			}

			// Update
			if ($f3->POST["data_id"] != null) {
				$id = $f3->POST["data_id"];
				unset($f3->POST["data_id"]);
				$json = json_encode($f3->POST);

				$f3->DB->exec("UPDATE {$this->namespace} SET data=? WHERE id=?", [$json, $id]);
			}

			$f3->reroute('/admin/'.$this->namespace.'#form');
		});

		$f3->route('GET /admin/'.$this->namespace.'/delete', function ($f3) {

			$f3->DB->exec("DELETE FROM {$this->namespace} WHERE id=?", $f3->GET["data_id"]);

			$f3->reroute('/admin/'.$this->namespace.'#form');
		});

		$f3->route('POST /admin/'.$this->namespace.'/reorder [ajax]', function ($f3) {

			$order = json_decode($f3->POST["order"], 1);

			if (count($order)>0) {
				foreach ($order as $key=>$id) {
					$f3->DB->exec("UPDATE {$this->namespace} SET rowOrder=? WHERE id=?", [$key, $id]);
				}
			}

		});



	}

	function setup_routes ($f3) {

		$f3->route('GET /admin/'.$this->namespace.'/setup', function ($f3) {
			$f3->namespace = $this->namespace;

			$f3->set("repeat.routes", setting($this->namespace."_routes"));

			$f3->repeat["snippets"] = $this->snippets;

			$f3->repeat["snippet"] = setting($this->namespace."_snippet");

			$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->namespace}'");

			if (empty($result))
				base::instance()->DB->exec("CREATE TABLE '{$this->namespace}' ('id' INTEGER PRIMARY KEY, 'data' TEXT, 'rowOrder' INTEGER);");

			$count = count($f3->DB->exec("SELECT * FROM {$this->namespace} ORDER BY rowOrder"));

			if ($count)
				$f3->repeat["lock_snippet"] = true;

			$f3->module_name = base::instance()->DB->exec("SELECT name FROM licenses WHERE namespace=?", [$this->namespace])[0]["name"];

			echo Template::instance()->render("/repeat/setup.html");
		});

		$f3->route('POST /admin/'.$this->namespace.'/setup', function ($f3) {
			
			$this->install();

			$f3->reroute('/admin/'.$this->namespace."/setup");
		});
	}

	function asset_routes ($f3) {
		// Insert any assets in here

		// EG: $f3->route('GET /test/path', function () { echo Template::instance()->render("/repeat/test_file.html", "text/html"); });
	}

	function install () {

		setting_use_namespace($this->namespace);
		setting("routes", base::instance()->POST["routes"]);
		setting("snippet", base::instance()->POST["snippet"]);
		setting_clear_namespace();
	}

	function check_install() {
		setting_use_namespace($this->namespace);

		if (!setting("routes"))
			return false;

		if (!setting("snippet"))
			return false;

		setting_clear_namespace();

		return true;
	}

}