<?php

class ckeditor extends prefab {

	function __construct() {

		$f3 = base::instance();

		if (!$f3->devoid("ckeditor_has_duplicates"))
		{
			echo Template::instance()->render("/ckeditor/ckeditor_warning.html");
			$f3->clear("ckeditor_has_duplicates");
			$f3->clear("id_duplicate");
			exit();
		}

		$this->template_filters($f3);

		if (admin::$signed)
		{

			$this->patch_table();

			$f3->set("ckeditor.enable_image_uploading", false);

			if (setting("ckeditor_folder_structure") && setting("ckeditor_image_upload_path"))
				$f3->set("ckeditor.enable_image_uploading", true);

			$this->assets($f3);

			if (!$f3->SETTINGS["ckeditor_skin"])
				$f3->SETTINGS["ckeditor_skin"] = "minimalist";


			$this->admin_routes($f3);

			$inlinecode = Template::instance()->render("/ckeditor/inline_init.html", null);

			$f3->set("ckeditor", $inlinecode);
		}
		else
			$f3->set("ckeditor", "");
	}

	function admin_routes($f3) {

		$f3->route("GET /admin/ckeditor", function ($f3) {

			if (!$this->install_check())
				$f3->reroute("/admin/ckeditor/setup");

			$skins = parse_ini_file($f3->SETTINGS["paths"]["cms"]."/modulesUI/ckeditor/settings.ini", true)["skins"];
			$f3->set("ckeditor.skins", $skins);

			echo Template::instance()->render("/ckeditor/ckeditor.html");
		});

		$f3->route("GET /admin/ckeditor/setup", function ($f3) {

			$f3->set("ckeditor.image_upload_path", setting("ckeditor_image_upload_path"));
			$f3->set("ckeditor.folder_structure", setting("ckeditor_folder_structure"));

			echo Template::instance()->render("/ckeditor/setup.html");
		});

		$f3->route("POST /admin/ckeditor/setup", function ($f3) {
			$post = $f3->POST;

			if ($post["upload_path"])
				setting("ckeditor_image_upload_path", $post["upload_path"]);

			if ($post["folderstructure"])
				setting("ckeditor_folder_structure", $post["folderstructure"]);

			$f3->reroute("/admin/ckeditor/setup");
		});

		$f3->route("POST /admin/ckeditor/save", function ($f3) {

			$filename = urldecode($f3->POST["file"]);
			$path 	  = urldecode($f3->POST["path"]);
			$id 	  = $f3->POST["id"];
			$sentHash = $f3->POST["hash"];
			$contents = $f3->POST["contents"];

			if ($path != "null" && $filename == "null")
			{
				content::set($id, $path, $contents);
				echo "saved";
				return;
			}
			// Load in to replace contents with
			$file = file_get_contents(getcwd()."/".$filename);

			// Determine hash
			preg_match_all("#(<ckeditor.*id=[\"']".$id."[\"'].*>)(.*)(<\/ckeditor>)#siU", $file, $output_array);
			$checkHash = sha1($output_array[2][0]);

			// If sent hash and check hash are the same,
			// then we know for absolutly sure we are updating
			// the right content.
			if ($sentHash == $checkHash) {

				$file = preg_replace_callback("#(<ckeditor.*id=[\"']".$id."[\"'].*>)(.*)(<\/ckeditor>)#siU", function ($matches) use ($contents, $filename, $id) {

					$return .= $matches[1];
					$return .= $contents;
					$return .= $matches[3];

					// Store for revision control
					$this->addRevision($id, $filename, $matches[2]);

					return $return;
				}, $file);

				file_put_contents(getcwd()."/".$filename, $file);
			} else {

				echo "wrong hash!";
			}
			
			echo sha1($contents);

			return;
		});

		$f3->route("GET /admin/ckeditor/select-skin/@skin", function ($f3, $p) {
			$skins = parse_ini_file($GLOBALS["settings"]["paths"]["cms"]."/modulesUI/ckeditor/settings.ini", true)["skins"];

			if (in_array($p["skin"], $skins))
				setting("ckeditor_skin", $p["skin"]);

			$f3->reroute("/admin/ckeditor/");
		});

		$f3->route("GET /admin/ckeditor/documentation", function ($f3) {
			echo Template::instance()->render("/ckeditor/documentation.html");
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

		$f3->route("POST /admin/ckeditor/getRevision", function ($f3) {

			$result = $f3->DB->exec("SELECT content FROM ckeditor_revisions WHERE id=?", $f3->POST["id"]);

			echo $result[0]["content"];
		});

		$f3->route("GET /admin/ckeditor/getRevisions/@ckeditor", function ($f3, $params) {

			$result = $f3->DB->exec("SELECT id, `date` FROM ckeditor_revisions WHERE ckeditor=? ORDER BY `date` DESC", $params["ckeditor"]);

			if (!$result) return null;

			foreach ($result as $key=>$value) {
				$result[$key]["date"] = time_elapsed_string($value["date"]);
			}

			j($result);

		});
	}


	function addRevision ($id, $file, $contents) {
		$db = base::instance()->DB;

		$db->exec("INSERT INTO ckeditor_revisions (`file`, `content`, `date`, `ckeditor`) VALUES (?, ?, ?, ?)", [$file, $contents, time(), $id]);

		return $version;
	}

	function install_check() {

		if (!setting("ckeditor_image_upload_path"))
			return false;

		if (!setting("ckeditor_folder_structure"))
			return false;

		return true;
	}

	function patch_table () {

		$result = base::instance()->DB->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='ckeditor_revisions'");

		if (empty($result))
			base::instance()->DB->exec("CREATE TABLE 'ckeditor_revisions' ('id' INTEGER PRIMARY KEY NOT NULL, 'file' TEXT, 'content' TEXT, 'date' DATETIME, 'ckeditor' TEXT);");
	}

	function assets($f3) {

		$f3->route("GET /admin/ckeditor/js/init.js", function () {
			echo Template::instance()->render("/ckeditor/js/init.js", "text/javascript");
		});

		$f3->route('GET /admin/ckeditor/skins/flat.png', function () { echo View::instance()->render("/ckeditor/skins/flat.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/kama.png', function () { echo View::instance()->render("/ckeditor/skins/kama.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/moonocolor.png', function () { echo View::instance()->render("/ckeditor/skins/moonocolor.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/moono-dark.png', function () { echo View::instance()->render("/ckeditor/skins/moono-dark.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/skins/office2013.png', function () { echo View::instance()->render("/ckeditor/skins/office2013.png", "image/png"); });

		$f3->route('GET /admin/ckeditor/images/inlinesave-color.svg', function () { echo View::instance()->render("/ckeditor/images/save-color.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/images/inlinesave-label.svg', function () { echo View::instance()->render("/ckeditor/images/save-label.png", "image/png"); });
		$f3->route('GET /admin/ckeditor/cms_save.js', function () { echo Template::instance()->render("/ckeditor/js/cms_save.js", "application/javascript"); });
		$f3->route('GET /admin/ckeditor/restore.js', function () { echo Template::instance()->render("/ckeditor/js/restore.js", "application/javascript"); });
		$f3->route('GET /admin/ckeditor/imagebrowser.js', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/plugin.js", "application/javascript"); });
		$f3->route('GET /admin/ckeditor/browser/browser.html', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/browser/browser.html", "text/html"); });
		$f3->route('GET /admin/ckeditor/browser/browser.css', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/browser/browser.css", "text/css"); });
		$f3->route('GET /admin/ckeditor/browser/browser.js', function () { echo View::instance()->render("/ckeditor/js/imagebrowser/browser/browser.js", "application/javascript"); });

		$f3->route('GET /admin/ckeditor/css/toolbar.css', function () { 
			echo Template::instance()->render("/ckeditor/css/toolbar.css", "text/css"); 
		});
		$f3->route('GET /admin/ckeditor/js/toolbar.js', function () { echo Template::instance()->render("/ckeditor/js/toolbar.js", "text/javascript"); });

	}


	public $id_list = array();
	function template_filters ($f3) {

		Template::instance()->extend("ckeditor", function ($args) {


			if (!array_key_exists($args["@attrib"]["id"], ckeditor::instance()->id_list))
			{
				ckeditor::instance()->id_list[$args["@attrib"]["id"]] = true;
			}
			else
			{
				// We have duplicates. I wonder if we can redirect from here?
				base::instance()->set("ckeditor_has_duplicates", true, 3600);
				base::instance()->set("id_duplicate", $args["@attrib"]["id"], 3600);
			
			    $string = '<script type="text/javascript">';
			    $string .= 'window.location = "' . $url . '"';
			    $string .= '</script>';

			    echo $string;
				die;
			}


			$hash = sha1($args[0]);
			$file = Template::instance()->actualfile;

			$type = ($args["@attrib"]["type"]) ? $args["@attrib"]["type"] : "full";

			$out .= '<?php if (admin::$signed) {?>';
			$out .= "<div file='".urlencode($file)."' id='".$args["@attrib"]["id"]."' hash='$hash' class='ckeditor' type='$type' contenteditable='true'>";
			$out .= "<?php } ?>";
			$out .= $args[0];
			$out .= '<?php if (admin::$signed) {?>';
			$out .= "</div>";
			$out .= "<?php } ?>";

			return  $out;
		});

		Template::instance()->filter("urlencode", function ($encode) { return urlencode($encode); });
	}
}