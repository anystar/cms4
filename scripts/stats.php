<?php

class stats extends prefab {

	public $stats;
	private $email;

	function __construct($settings) {

		$this->email = $settings["user"];

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

				$milestones = array(
					100=>"100 views",
					1000=>"1000 views",
					2000=>"2000 views",
					5000=>"5k views",
					10000=>"10k views",
					25000=>"25k views",
					50000=>"50k views",
					100000=>"100k views",
					250000=>"250k views",
					500000=>"500k views",
					750000=>"750k views",
					1000000=>"1 Millon views!!!!",
					2000000=>"Epic 2 Million views!",
					5000000=>"Holy cow 5mil views!!",
					10000000=>"wow 10 Million views!"
				);

				if (array_key_exists($this->stats["views"], $milestones))
					$this->email_view_alert($milestones[$this->stats["views"]]);
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

	function email_view_alert ($view_message) {

		$config = $GLOBALS["config"];
		if (array_key_exists("stats", $config))
		if (array_key_exists("emails", $config["stats"]))
			$emails = $config["stats"]["emails"];

		base::instance()->view_message = $view_message;
		$webmaster_email_body = \Template::instance()->render("/stats/view_webmaster_alert_email.html", null);
		$email_body = \Template::instance()->render("/stats/view_alert_email.html", null);
		base::instance()->clear("view_message");

		$config = base::instance()->CONFIG["mailer"];

		// Webmaster emails
		foreach ($emails as $email)
		{
			$smtp = new SMTP(
							$config["smtp.host"],
							$config["smtp.port"],
							$config["smtp.scheme"],
							$config["smtp.user"],
							$config["smtp.pw"]
						);

			$smtp->set('To', $email);
			$smtp->set('From', '"'.$config["smtp.from_name"].'"'.' <'.$config["smtp.from_mail"].'>');
			$smtp->set('Subject', 'Milestone for '.base::instance()->HOST);
			$smtp->set('Content-Type', "text/html");

			$smtp->send($webmaster_email_body);
		}

		if ($this->email != "")
		{
			$smtp = new SMTP(
							$config["smtp.host"],
							$config["smtp.port"],
							$config["smtp.scheme"],
							$config["smtp.user"],
							$config["smtp.pw"]
						);

			$smtp->set('To', $this->email);
			$smtp->set('From', '"'.$config["smtp.from_name"].'"'.' <'.$config["smtp.from_mail"].'>');
			$smtp->set('Subject', 'Milestone for '.base::instance()->HOST);
			$smtp->set('Content-Type', "text/html");

			$smtp->send($email_body);
		}
	}
}