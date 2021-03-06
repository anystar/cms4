<?php

class dropimg extends prefab {

	public static $default_label = "Drop Image";

	function __construct() {

		$f3 = \Base::instance();

		if (admin::$signed) {

			$f3->route("POST /admin/dropfile/upload", function ($f3) {

				$file = $f3->POST["file"];
				$file = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $file);

				// Handle regular files

					$ext = pathinfo(getcwd()."/".$file)["extension"];

					$dir = getcwd()."/".pathinfo($file)["dirname"];

					if (!is_dir($dir))
						mkdir($dir);

					if (!is_file($file))
						touch($file);

					if (is_writeable($file)) {
						copy($f3->FILES["file"]["tmp_name"], getcwd()."/".$file);
					}
			});

			$f3->route("POST /admin/dropimg/upload", function ($f3) {

				$saveto = $f3->POST["file"];
				$saveto = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $saveto);

				$return = saveimg ($f3->FILES["file"], $saveto, [
					"size"=>[$f3->POST["width"], $f3->POST["height"]],
					"crop"=>true,
					"enlarge"=>true,
					"overwrite"=>true,
					"quality"=>90
				]);
			});

			$f3->route("POST /admin/dropimg/upload_from_url", function ($f3) {

				saveimg($f3->POST["url"], $f3->POST["file"], array(
					"size" => [$f3->POST["width"], $f3->POST["height"]],
					"crop" => true,
					"enlarge" => true,
					"quality" => 90,
					"type" => "auto",
					"overwrite" => true
				));

				return;
			});

			if (isroute("/admin/*"))
				return;

			toolbar::instance()->append(\Template::instance()->render("dropimg/init.html", null));
		}

		\Template::instance()->extend("dropimg", function ($args) {
			$f3 = \Base::instance();

			if (!isset($args["@attrib"]))
				$f3->error(1, "DropIMG: No attributes found?");

			if (!array_key_exists("src", $args["@attrib"]))
				$f3->error(1, "DropIMG: no src value found");

			$src = html_entity_decode($args["@attrib"]["src"]);
			unset($args["@attrib"]["src"]);

			// Strip potential url parameters from src field
			$file = parse_url($src)["path"];
			$file = str_replace("\\", "/", $file);

			check (1, (!$args["@attrib"]["resize"] && !$args["@attrib"]["size"]), "No `resize` attribute found for dropimg tag.");

			$size = $args["@attrib"]["size"] ? $args["@attrib"]["size"] : $args["@attrib"]["resize"];
			unset($args["@attrib"]["size"], $args["@attrib"]["resize"]);
			$asize = explode("x", $size);
			$psize = $asize;


			if ($psize[0] == 'auto') $psize[0] = $psize[1];
			if ($psize[1] == 'auto') $psize[1] = $psize[0];

			$placeholder_path = "https://via.placeholder.com/".$psize[0]."x".$psize[1]."?text=".$psize[0]."x".$psize[1];

			// Does the file exist?
			if (!file_exists($path = getcwd()."/".$file)) {

				$pi = pathinfo($path);

				saveimg($placeholder_path, $pi["dirname"]."/", [
					"filename" => $pi["basename"],
					"type" => $pi["extension"]
				]);
			}

			// Have we changed the image size
			if (\Cache::instance()->exists("dropimg_".sha1($path), $value))
			{
				if ($size != $value)
				{
					$pi = pathinfo($path);

					saveimg($placeholder_path, $pi["dirname"]."/", [
						"filename" => $pi["basename"],
						"type" => $pi["extension"]
					]);

					\Cache::instance()->set("dropimg_".sha1($path), $size);
				}
			}

			$string .= '<?php if (admin::$signed) {?>';
			$string .= "<img";
			$string .= " title='".$size."'";	
			$string .= " data-file='".$file."'";
			$string .= " data-width='".$asize[0]."'";
			$string .= " data-height='".$asize[1]."'";
			$string .= " data-mime='".mime_content_type2($path)."' ";
			$string .= " src='".$src."?<?php if (is_file('".$file."')) {substr(sha1_file('".$file."'), -8);} else {";
			$string .= '$pi = pathinfo("'.$path.'"); saveimg("'.$placeholder_path.'", $pi["dirname"]."/", [ "filename" => $pi["basename"], "type" => $pi["extension"]] );';
			$string .= "} ?>' ";

			$classFilled = false;
			foreach ($args["@attrib"] as $key=>$value) {

				if ($key=="class") {
					$string .= ' class="'.$value.' imgdropzone" ';
					$classFilled = true;
				} else {
					$string .= $key.'="'.$value.'" ';
				}
			}

			if (!$classFilled)
				$string .= 'class="imgdropzone" ';

			$string .= 'id="'.uniqid("dropimg_").'"';
			$string .= ">";
			$string .= "<?php } ?>";
			
			$string .= '<?php if (!admin::$signed) {?>';
			$string .= "<img";
			$string .= ' src="'.$src.'?';
			$string .= '<?php if (is_file("'.$file.'")) { echo filemtime("'.$file.'"); } ?>';
			$string .= '" ';

			foreach ($args["@attrib"] as $key=>$value) {
				$string .= $key.'="'.$value.'" ';
			}

			$string .= ">";
			$string .= "<?php } ?>";

			return $string;
		});


		\Template::instance()->extend("dropfile", function ($args) {
			$f3 = \Base::instance();

			$hive = array();

			if (!array_key_exists("class", $args["@attrib"]))
				$args["@attrib"]["class"] = "webworks-admin-button";

			foreach ($args["@attrib"] as $key=>$value) {
				if ($key=="class") {
					$hive["extra_attribs"] .= 'class="'.$value.' filedropzone webworkscms_button" ';
				} else {
					$hive["extra_attribs"] .= $key.'="'.$value.'" ';
				}
			}

			if (array_key_exists(0, $args))
				$hive["button_text"] = $args[0];

			// Get file via Href or Src
			$hive["file"] = parse_url($args["@attrib"]["href"])["path"];
			$hive["file"] = str_replace("\\", "/", $hive["file"]);
			$hive["filetype"] = mime_content_type2($args["@attrib"]["href"]);
			$hive["uniqid"] = uniqid("dropfile_");

			$uploadHTML = '<button {{@if_image}} data-filetype="{{@filetype}}" data-file="{{@file}}" id="{{@uniqid}}" {{@class}} {{@extra_attribs}}>{{@button_text}}</button>';
			$linkHTML = '<a {{@extra_attribs}}>{{@button_text}}</a>';

			$string = '<?php if (admin::$signed) {?>';
			$string .= Preview::instance()->resolve($uploadHTML, $hive);
			$string .= "<?php } else { ?>";
			$string .= Preview::instance()->resolve($linkHTML, $hive);
			$string .= "<?php } ?>";

			return $string;
		});

	}
}