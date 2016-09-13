<?php

class statistics extends prefab {

	var $about = array(
		"module name" => "Statistics"
	);

	var $db;

	function __construct() {

		if (!$this->hasInit())
			return;

		// Do not record if on admin path
		if (preg_match("/\/admin(.*)/", base::instance()->PATH))
		{
		
			// If signed in and on admin path get data			
			if (admin::$signed)
				$this->getOverview();

			// Do not record if on admin path
			return;
		}

		// Do not record if signed into admin
		if (!admin::$signed)
			return;

		$this->record();
	}

	function record() {
		$f3 = base::instance();

		$ip = ip2long($f3->IP);

		$this->db->begin();
		$this->db->exec("INSERT INTO hits (ip, page) VALUES (?, ?)", [$f3->IP, $f3->PATH]);
		$this->db->exec("INSERT OR IGNORE INTO visits (ip) VALUES (?)", [$ip]);
		$this->db->exec("UPDATE visits SET hits = hits + 1 WHERE ip LIKE ?", [$ip]);
		$this->db->commit();
	}

	function getOverview() {

		$now = date('Y-m-d',time()+86400);
		$startOfMonth = date('Y-m-01');
		$startOfYear = date('Y-01-01');

		base::instance()->statistics["visits_this_month"] = $this->db->exec("SELECT count(*) as hits FROM visits WHERE date BETWEEN ? AND ?", [$startOfMonth, $now])[0]["hits"];		
		base::instance()->statistics["visits_this_year"] = $this->db->exec("SELECT count(*) as hits FROM visits WHERE date BETWEEN ? AND ?", [$startOfYear, $now])[0]["hits"];
	}


	function hasInit() {
		
		if (!file_exists(getcwd()."/db/statistics"))
			return false;

		$db = $this->db = new DB\SQL('sqlite:'.getcwd()."/db/statistics");
		
		return true;
	}

	function generate() {
		touch(getcwd()."/db/statistics");
		$this->db = new DB\SQL('sqlite:'.getcwd()."/db/statistics");
	
		//TODO: Insert sql to generate table structures
		$this->db->exec("CREATE TABLE 'hits' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'ip' TEXT, 'page' TEXT, 'date'  INTEGER DEFAULT CURRENT_TIMESTAMP  );");
	}

	function admin_render() {

		//TODO: Create html files for admin display and generation
		if ($this::instance()->hasInit())
			echo Template::instance()->render("module_name/module.html");
		else
			echo Template::instance()->render("module_name/module.html");
	}
}