<?php

class review extends prefab {

	private $settings;

	function __construct($settings) {
		$f3 = \Base::instance();
		$this->settings = $settings;

		if (admin::$signed)
		{
			if (array_key_exists("review", $f3->GET))
			{
				echo \Template::instance()->render("/review/review.html");
				die;
			}

			$f3->route("GET /admin/review/jquery.keepformdata.js", function ($f3) {
				echo \Template::instance()->render("/review/jquery.keepformdata.js", "application/javascript", null);
				$f3->abort();
			});

			$f3->route("POST /admin/review/submit", function ($f3) {
				$emails = $f3->CONFIG["review"]["email"];
		
				if (count($emails) == 0)
					return;

				$f3->delivered_to = $emails;
				echo \Template::instance()->render("/review/success.html", null);

				$f3->abort();

				$emailBody = \Template::instance()->render("/review/email_tpl_review.html", null);

				$f3->MAILER->addTo(array_shift($emails));

				foreach ($emails as $email)
					$f3->MAILER->addBCC($email);

				//$f3->MAILER->addCC($this->settings["user"]);
				$f3->MAILER->setHTML($emailBody);
				$f3->MAILER->send("Website Review");
				$f3->MAILER->reset();

				$reviewLog["last-reviewed"] = time();
				$reviewLog["next-review"] = time() + $f3->POST["nextreview"];
				$reviewLog["results"] = $f3->POST;
				file_put_contents(".cms/reviewlog.json", json_encode($reviewLog, JSON_PRETTY_PRINT));
			});
		}
	}

}