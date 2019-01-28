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

		if (admin::$signed)
			$this->stats = json_decode(file_get_contents(".cms/stats.json"), true);
		
		if (!admin::$signed)
		{
			if (!\Cache::instance()->exists(\Base::instance()->IP))
			{
				\Cache::instance()->set(\Base::instance()->IP, true, 7200); // Cache for 2hrs

				// Only track views counted within a certain country
				$config = $GLOBALS["config"];

				// TODO: Allow configuration in users files.

				$geo = \Web\Geo::instance()->location();
				if (array_key_exists("stats", $config))
				if (array_key_exists("country", $config["stats"]))
				if (in_array($geo["country_code"], $config["stats"]["country"]))
				{
					$this->stats = json_decode(file_get_contents(".cms/stats.json"), true);
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
		}
		
		\Base::instance()->set("STATS", $this->stats);

		$render = \Template::instance()->render("/stats/toolbar.html", null, ["STATS"=>$this->stats, "BASE"=>\Base::instance()->BASE]);

		toolbar::instance()->append($render);

		\Base::instance()->route("GET /admin/stats", function ($f3) {

			for ($i = 0; $i <= 11; $i++) {
			    $months[date("F Y", strtotime( date( 'Y-m-01' )." -$i months"))] = 0;
			}

			$months = array_reverse($months);
			$data = array_merge($months, $this->stats["perMonth"]);

			$months = '"'.implode('","', array_keys($data)).'"';
			$data = '"'.implode('","', $data).'"';

			$f3->set("monthsArray", $months);
			$f3->set("dataArray", $data);

			echo \Template::instance()->render("/stats/stats.html", "text/html");
		});
	}

	function email_view_alert ($view_message) {

		$config = \Base::instance()->CONFIG;
		$emails = array();

		if (array_key_exists("stats", $config))
		if (array_key_exists("emails", $config["stats"]))
			$emails = $config["stats"]["emails"];

		\Base::instance()->view_message = $view_message;

		$webmaster_email_body = \Template::instance()->render("/stats/view_webmaster_alert_email.html", null);
		$email_body = \Template::instance()->render("/stats/view_alert_email.html", null);

		\Base::instance()->clear("view_message");

		$mailer = new \Mailer();

		if (count($emails) > 0)
		{
			// Webmaster emails
			$mailer->setHTML($webmaster_email_body);
			$mailer->addTo(array_shift($emails));
			foreach ($emails as $email)
				$mailer->addBcc($email);

			$mailer->send('Milestone for '.\Base::instance()->HOST);
			$mailer->reset();
		}

		if ($this->email != "")
		{
			$mailer->addHTML($email_body);
			$mailer->addTo($this->email);
			$mailer->send('Milestone for '.\Base::instance()->HOST);
			$mailer->reset();
		}
	}
}