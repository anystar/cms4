<?php

class unit_test {

	function __construct($settings) {

		if (base::instance()->ADMIN)
		{
			$this->image_test_routes(base::instance());
			$this->SMTP_test_routes(base::instance());
		
			base::instance()->route("GET /php-info", function () {
				phpinfo();
				die;
			});
		}
	}

	function image_test_routes($f3) {

		$f3->route("GET /admin/unit-test/delete-image", function ($f3) {

			unlink("image-test.jpg");
			$f3->reroute("/image-test");
		});

		$f3->route("GET /image-test", function ($f3) {

			if (array_key_exists("create-image", $f3->GET))
			{
				$f3->show_image = true;
			}

			if (file_exists("image-test.jpg"))
			{
				$f3->show_image = true;
			
				// Get current file size of image
				$f3->file_size =  human_filesize(filesize("image-test.jpg"));
				$f3->file_mime = mime_content_type2("image-test.jpg");

				// Get maximum upload size on server
					// Post upload size
					// File upload size
				$f3->max_upload_size = file_upload_max_size();
				// 

			}

			echo Template::instance()->render("/unit-test/image-test.html", "text/html");
			die;
		});
	}

	function SMTP_test_routes($f3) {

		$f3->route("GET /smtp-test", function ($f3) {
			
			$mailer_config = $f3->CONFIG["mailer"];
			$f3->set("mailer", $mailer_config);

			// Check IP addresss resolution
			$result = gethostbyname($f3->get("mailer.smtp.host"));
			$f3->set("server_ip", $result == $f3->get("mailer.smtp.host") ? "No server found" : $result);

			$f3->test_subject = "Test Subject";
			$f3->test_email = "darklocker@gmail.com";
			
			echo Template::instance()->render("/unit-test/smtp-test.html", "text/html");
		});

		$f3->route("POST /smtp-test", function ($f3) {
			
			$mailer = new \Mailer();

			$mailer->addTo($f3->POST["send-to"], "Summer");
			$mailer->setReply("summer@webworksau.com", "Summer");
			$mailer->setText("Test Message");

			$mailer->queue("Test Mail Subject");

			
			redirect("smtp-test");
		});

		$f3->route("GET /admin/smtp-test", function ($f3) {



			k("login test");
		});

	}
}