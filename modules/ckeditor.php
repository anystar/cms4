<?php

class ckeditor extends prefab {

	function __construct() {

		if (!$this->installed())
			return;

		$this->routes(base::instance());
		$this->assets(base::instance());

		Template::instance()->extend("ckeditor", function ($args) {

			$hash = sha1($args[0]);
			$file = Template::instance()->file;

			$type = ($args["@attrib"]["type"]) ? $args["@attrib"]["type"] : "full";

			$out .= '<?php if (admin::$signed) {?>';
			$out .= "<div file='$file' id='".$args["@attrib"]["id"]."' hash='$hash' class='ckeditor' type='$type' contenteditable='true'>";
			$out .= "<?php } ?>";
			$out .= $args[0];
			$out .= '<?php if (admin::$signed) {?>';
			$out .= "</div>";
			$out .= "<?php } ?>";

			return  $out;
		});

		base::instance()->set("index.content", "Some test content");

		Template::instance()->filter("ckeditor", function ($content, $contentID, $type="full") {

			if ($content == "") $content = "Dummy text";

			if (admin::$signed) 
				$out .= "<div file='database' type='".$type."' id='".$contentID."' class='ckeditor' contenteditable='true'>";
			
			$out .= $content;
			
			if (admin::$signed) 
				$out .= "</div>";

			return $out;
		});

		if (admin::$signed)
		{
			if (!base::instance()->SETTINGS["ckeditor_skin"])
				base::instance()->SETTINGS["ckeditor_skin"] = "minimalist";

			$this->admin_routes(base::instance());

			$inlinecode = Template::instance()->render("/ckeditor/inline_init.html");
			base::instance()->concat("admin", $inlinecode);
		}
	}

	function routes($f3) {

		$f3->route('GET /ckeditor/images/inlinesave-color.svg', function () {
			echo View::instance()->render("/ckeditor/images/save-color.png", "image/png");
		});

		$f3->route('GET /ckeditor/images/inlinesave-label.svg', function () {
			echo View::instance()->render("/ckeditor/images/save-label.png", "image/png");
		});

		$f3->route('GET /ckeditor/cms_save.js', function () {
			echo Template::instance()->render("/ckeditor/js/cms_save.js", "application/javascript");
		});

	}

	function admin_routes($f3) {

		$f3->route("GET /admin/ckeditor", function ($f3) {
			$f3->ckeditor["skins"] = parse_ini_file($GLOBALS["settings"]["paths"]["cms"]."/modulesUI/ckeditor/settings.ini", true)["skins"];

			echo Template::instance()->render("/ckeditor/ckeditor.html");
		});

		$f3->route("POST /admin/ckeditor/save", function ($f3) {

			$filename = $f3->POST["file"];
			$id = $f3->POST["id"];
			$sentHash = $f3->POST["hash"];
			$contents = $f3->POST["contents"];

			// No filename supplied, update the database instead.
			// Hand this role over to content as its his data.
			if ($filename == 'database' || $filename == 'null' || !is_file(getcwd()."/".$filename))
			{
				$tmp = explode("-", $id);
				$name = end($tmp);	
				array_pop($tmp);
				$path = implode("/", $tmp);

				content::set($name, $path, $contents);

				return;
			}

			// Load in to replace contents with
			$file = file_get_contents(getcwd()."/".$filename);

			// Determine hash
			preg_match_all("#(<ckeditor id=[\"']".$id."[\"'].*>)(.*)(<\/ckeditor>)#siU", $file, $output_array);
			$checkHash = sha1($output_array[2][0]);

			// If sent hash and check hash are the same,
			// then we know for absolutly sure we are updating
			// the right content.
			if ($sentHash == $checkHash) {

				$file = preg_replace_callback("#(<ckeditor id=[\"']".$id."[\"'].*>)(.*)(<\/ckeditor>)#siU", function ($matches) use ($contents) {

					$return .= $matches[1];
					$return .= $contents;
					$return .= $matches[3];

					return $return;
				}, $file);

				file_put_contents(getcwd()."/".$filename, $file);
			}

			// echo "Sent: " .$sentHash. "\n";
			// echo "Check: ".$checkHash . "\n";
			// echo "New Hash: ".sha1($content) . "\n";
			// echo "Contents: " .$contents;

			echo sha1($contents);

			return;
		});

		$f3->route("GET /admin/ckeditor/select-skin/@skin", function ($f3, $p) {
			$skins = parse_ini_file($GLOBALS["settings"]["paths"]["cms"]."/modulesUI/ckeditor/settings.ini", true)["skins"];

			if (in_array($p["skin"], $skins))
				setting("ckeditor_skin", $p["skin"]);

			$f3->reroute("/admin/ckeditor/");
		});
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

	}

	function installed () {
		if (!setting("ckeditor_installed")) {
				base::instance()->route("GET /admin/ckeditor/install", function () {
					$this->install();
				});
				return false;
		}

		base::instance()->set("ckeditor.installed", true);

		return true;
	}

	function install () {
		base::instance()->reroute("/admin/ckeditor");
	}
}