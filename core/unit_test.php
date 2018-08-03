<?php

class unit_test {

	function __construct($settings) {

		if (base::instance()->ADMIN)
			$this->routes(base::instance());
	}

	function routes($f3) {

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

		$f3->route("GET /php-info", function () {
			phpinfo();
			die;
		});

	}
}