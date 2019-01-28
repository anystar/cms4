<?php

class TemplateAdditional extends Template {

	
}

class ckeditor extends prefab {

	public static $default_label = "CKEditor";

	function __construct($settings) {
		$f3 = \Base::instance();

		if (admin::$signed)
		{
			// Are we permitting uploading of images?
			if (array_key_exists("image-upload-path", $settings))
			{
				check(0, !checkdir($settings["image-upload-path"]), "CKEditor: invalid `image-upload-path` set.", $settings);
		
				check(0, $settings["folder-structure"], 
					"CKEditor: `folder-structure` not set.", 
					$settings, "Example:", 
					$settings+["folder_structure"=>"single"], 
					"Accepted Values: grouped, single");
			}

			if (!array_key_exists("skin", $settings) || $settings["skin"]!="")
				$settings["skin"] = "minimalist";

			$this->assets($f3);
			$this->admin_routes($f3);

			// Load on everything but admin routes
			if (!isroute("/admin/*"))
				ToolBar::instance()->append(\Template::instance()->render("/ckeditor/inline_init.html", null));
		}

		$this->template_filters($f3);
	}

	function admin_routes($f3) {

		$f3->route("POST /admin/ckeditor/save", function ($f3) {

			$filename = urldecode($f3->POST["file"]);
			$path 	  = urldecode($f3->POST["path"]);
			$id 	  = $f3->POST["id"];
			$sentHash = $f3->POST["hash"];
			$contents = $f3->POST["contents"];
			$order =    $f3->POST["order"];

			// Load in to replace contents with
			$file = file_get_contents(getcwd()."/".$filename);

			preg_match("#<ckeditor.*>.*<\/ckeditor>#siU", $file, $out);

			// Determine hash
			preg_match_all("#(<ckeditor.*id=[\"']".$id."[\"'].*>)(.*)(<\/ckeditor>)#siU", $file, $output_array);
			$checkHash = sha1($output_array[2][0]);

			// If sent hash and check hash are the same,
			// then we know for absolutly sure we are updating
			// the right content.
			if ($sentHash == $checkHash) {

				ini_set('pcre.backtrack_limit', 200000);
				ini_set('pcre.recursion_limit', 200000);
				$file = preg_replace_callback("#(<ckeditor.*id=[\"']".$id."[\"'].*>)(.*)(<\/ckeditor>)#siU", function ($matches) use ($contents, $filename, $id) {

					$return .= $matches[1];
					$return .= $contents;
					$return .= $matches[3];

					return $return;
				}, $file);

				// Prevent writing blank files
				if ($file == "")
				{	
					\Base::instance()->error(500, "Critial Error: Stopping CKEditor from writing blank data on Save Route! <br><br>"."Filename: ".$filename."<br><br>Path".$path."<br><br>id".$id."<br><br>contents".$contents);
					return;
				}

				file_put_contents(getcwd()."/".$filename, $file, LOCK_EX);
			} else {

				echo "wrong hash!";
				return;
			}
			
			echo sha1($contents);

			return;
		});


		$f3->route("POST /admin/ckeditor/upload_image", function ($f3) {

			$upload_directory = trim(setting("ckeditor_image_upload_path"), "/");
			$folder_structure = setting("ckeditor_folder_structure");

			if ($folder_structure == "grouped")
			{
				$path = trim(urldecode($f3->GET["upload_path"]), "/");
				$upload_directory .= "/" . $path;
			}

			if (!is_dir($upload_directory))
				mkdir($upload_directory, 0755, true);

			$new_name = str_replace(' ', '_', $f3->FILES["upload"]["name"]);
			$new_name = filter_var($new_name, FILTER_SANITIZE_EMAIL);

			$save_to = getcwd()."/".$upload_directory."/".$new_name;

			move_uploaded_file($f3->FILES["upload"]["tmp_name"], $save_to);
			
			$path = $f3->BASE . "/" . $upload_directory . "/" . $new_name;
			$ck_func_number = $f3->GET["CKEditorFuncNum"];
			echo "<script type='text/javascript'>window.parent.CKEDITOR.tools.callFunction('$ck_func_number', '$path', 'File uploaded successfully');</script>";
			exit;
		});


		$f3->route("GET /admin/ckeditor/imagebrowser", function ($f3) {

			$upload_directory = trim(setting("ckeditor_image_upload_path"), "/");
			$upload_structure = setting("ckeditor_folder_structure");

			switch ($upload_structure)
			{
				case "grouped":
					$path = trim(urldecode($f3->GET["path"]), "/");

					$urlpath = $upload_directory."/".$path;
					$dirpath = getcwd()."/".$upload_directory."/".$path;
				break;
				case "single":
					$urlpath = $upload_directory."/";
					$dirpath = getcwd()."/".$upload_directory;
				break;
			}

			if (!is_dir($dirpath))
			{
				echo json_encode(array());

				return;
			}

			$dir = scandir($dirpath);
			$dir = array_diff($dir, array('..', '.'));
			
			$accepted_mimes = [
				'image/gif',
				'image/jpeg',
				'image/png',
				'image/tiff'
			];

			foreach ($dir as $file)
			{
				if (!is_file($dirpath."/".$file))
					continue;

				$mime_type = mime_content_type2($dirpath."/".$file);

				if (in_array($mime_type, $accepted_mimes))
				{
					$compiled[] = [
						"image" => $f3->BASE."/".$urlpath."/".$file
					];
				}
			}

			echo json_encode($compiled);
		});
	}

	function assets($f3) {

		$f3->route("GET /admin/ckeditor/js/init.js", function () {
			echo \Template::instance()->render("/ckeditor/js/init.js", "text/javascript");
		});

		$f3->route('GET /admin/ckeditor/skins/flat.png', function () { echo View::instance()->render("/ckeditor/skins/flat.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/kama.png', function () { echo View::instance()->render("/ckeditor/skins/kama.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/moonocolor.png', function () { echo View::instance()->render("/ckeditor/skins/moonocolor.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/moono-dark.png', function () { echo View::instance()->render("/ckeditor/skins/moono-dark.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/office2013.png', function () { echo View::instance()->render("/ckeditor/skins/office2013.png", "image/png"); });

		$f3->route('GET /admin/ckeditor/images/inlinesave-color.svg', function () { echo View::instance()->render("/ckeditor/images/save-color.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/images/inlinesave-label.svg', function () { echo View::instance()->render("/ckeditor/images/save-label.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/cms_save.js', function () { echo \Template::instance()->render("/ckeditor/js/cms_save.js", "application/javascript"); });
		$f3->route('GET /admin/ckeditor/imagebrowser.js', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/plugin.js", "application/javascript"); });
		$f3->route('GET /admin/ckeditor/browser/browser.html', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/browser/browser.html", "text/html"); });
		$f3->route('GET /admin/ckeditor/browser/browser.css', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/browser/browser.css", "text/css"); });
		$f3->route('GET /admin/ckeditor/browser/browser.js', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/browser/browser.js", "application/javascript"); });

	}


	public $id_list = array();
	function template_filters ($f3) {
		
		\Template::instance()->beforerender(function ($contents, $view) {	

			// Only process files in the client directory
			if (getcwd() != substr($view, 0, strlen(getcwd())))
				return $contents;
			
			// if (mime_content_type2($view) == "text/html")
			// {
			// 	if (!is_writable($view))
			// 		return $contents;

			// 	$orginal = $contents;

			// 	// Inject the filename at the start of the file.

			// 	ini_set('pcre.backtrack_limit', 200000);
			// 	ini_set('pcre.recursion_limit', 200000);

			// 	// Automagically create IDs for ckeditor tag 
			// 	$contents = preg_replace_callback("/<ckeditor>/", function ($match) {

			// 		$id = substr("cid-".md5(uniqid(rand(), true)), 0, 12);

			// 		return '<ckeditor id="'.$id.'">';
			// 	}, $contents);

			// 	if (array_key_exists("ckeditor-fix-ids", \Base::instance()->GET))
			// 	{
			// 		// Check for duplicates
			// 		preg_match_all("#<ckeditor.*id=[\"'](.*)[\"']>.*<\/ckeditor>#siU", $contents, $output_array);

			// 		if ($output_array[1]) {
			// 			$dups = array_not_unique($output_array[1]);
			// 			$dups = array_unique($dups);

			// 			foreach ($dups as $id)
			// 			{
			// 				$contents = preg_replace_callback("#(<ckeditor.*id=[\"'])(".$id.")([\"'].*>.*<\/ckeditor>)#siU", function ($matches) {

			// 					$return .= $matches[1];
			// 					$return .= substr("cid-".md5(uniqid(rand(), true)), 0, 12);
			// 					$return .= $matches[3];

			// 					return $return;
			// 				}, $contents);

			// 			}
			// 		}
			// 	}

			// 	// Prevent writing blank files
			// 	if ($contents == "")
			// 	{
			// 		return "";
			// 	}
			// 	else 
			// 	{
			// 		// Make sure its actually changed
			// 		if ($orginal != $contents)
			// 			file_put_contents($view, $contents, LOCK_EX);
			// 	}
			// }
			
			return "{~ ".'$currentFileName="'.str_replace(getcwd()."/", "", $view).'";' . "~}" . PHP_EOL . $contents;
		});

		// \Template::instance()->extend("p", function ($args) {

		// 	k($args);

		// });

		
		\Template::instance()->extend("ckeditor", function ($args) {
			
			$documentation = '

				<h5>Syntax:</h5>
			'.
			\Base::instance()->highlight('<ckeditor id="{unique id}">').
			'<h5 style="padding-top:20px;">Example:</h5>'.
			\Base::instance()->highlight('<ckeditor id="main_header">').
			"<br>or<br>".
			\Base::instance()->highlight('<ckeditor id="mSXuS234fd2">').
			"<br><br><a href='?ckeditor-fix-ids' class='btn btn-danger'>Auto Fix Duplicate IDs</a>";

			// if (!isset($args["@attrib"]))
			// 	\Base::instance()->error(1, 'A CKEditor has no attributes'.$documentation);

			// if (!array_key_exists("id", $args["@attrib"]))
			// 	\Base::instance()->error(1, 'A CKEditor is missing id attribute'.$documentation);

			// if (!array_key_exists($args["@attrib"]["id"], ckeditor::instance()->id_list))
			// 	ckeditor::instance()->id_list[$args["@attrib"]["id"]] = true;
			// else
			// 	\Base::instance()->error(1, 'CKEditor Duplicate ID: "'.$args["@attrib"]["id"].'"'.$documentation);

			$hash = sha1($args[0]);
			
			$type = ($args["@attrib"]["type"]) ? $args["@attrib"]["type"] : "full";

			$out .= '<?php if (admin::$signed) {?>';

			$out .= '<div data-ck-file="<?php echo $currentFileName ?>" id="'.$args['@attrib']['id'].'" data-ck-hash="'.$hash.'" class="ckeditor" data-ck-order="<?php if (!isset($ckeditor_order)) { $ckeditor_order=0; } echo $ckeditor_order++; ?>" contenteditable="true">';
			$out .= "<?php } ?>";
			$out .= $args[0];
			$out .= '<?php if (admin::$signed) {?>';
			$out .= "</div>";
			$out .= "<?php } ?>";
			$out .= PHP_EOL;

			return  $out;
		});

		\Template::instance()->filter("urlencode", function ($encode) { return urlencode($encode); });
	}
}