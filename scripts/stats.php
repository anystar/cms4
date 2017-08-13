<?php

class stats extends prefab {

	public $stats;

	function __construct($settings) {

		// Create settings file
		if (!is_file(".cms/stats.json"))
		{
			$structure["views"] = 0;
			$structure["perMonth"] = array();

			file_put_contents(".cms/stats.json", json_encode($structure, JSON_PRETTY_PRINT));
		}

		$this->stats = json_decode(file_get_contents(".cms/stats.json"), true);

		if (!admin::$signed)
		{
			if (!Cache::instance()->exists(base::instance()->IP))
			{
				Cache::instance()->set(base::instance()->IP, true, 7200); // Cache for 2hrs
				$this->stats["views"]++;
				$date = date("F Y");
				if (!array_key_exists($date, $this->stats["perMonth"]))
					$this->stats["perMonth"][$date] = 1;
				else  
					$this->stats["perMonth"][$date]++;

				file_put_contents(".cms/stats.json", json_encode($this->stats, JSON_PRETTY_PRINT));
			}
		}
		
		base::instance()->set("STATS", $this->stats);

		ToolBar::instance()->append(Template::instance()->render("/stats/toolbar.html", null, ["STATS"=>$this->stats, "BASE"=>base::instance()->BASE]));

		base::instance()->route("GET /admin/stats", function ($f3) {

			$months = '"'.implode('","', array_slice(array_keys($this->stats["perMonth"]), -12, 12, true)).'"';
			$data = '"'.implode('","', array_slice($this->stats["perMonth"], -12, 12, true)).'"';

			$f3->set("monthsArray", $months);
			$f3->set("dataArray", $data);

			echo Template::instance()->render("/stats/stats.html", "text/html");
		});
	}
}