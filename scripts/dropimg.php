<?php

class dropimg extends prefab {

	function __construct() {

		$f3 = base::instance();


		if (admin::$signed) {

			$f3->route("POST /admin/dropfile/upload", function ($f3) {

				$file = $f3->POST["file"];
				$file = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $file);
				$ext = pathinfo(getcwd()."/".$file)["extension"];

				$dir = getcwd()."/".pathinfo($file)["dirname"];

				if (!is_dir($dir))
					mkdir($dir);

				if (!is_file($file))
					touch($file);

				if (is_writeable($file)) {	
					copy($f3->FILES["file"]["tmp_name"], $file);
				}
			});

			$f3->route("POST /admin/dropimg/upload", function ($f3) {

				$saveto = $f3->POST["file"];
				$saveto = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $saveto);
				$ext = pathinfo(getcwd()."/".$saveto)["extension"];
				
				$return = saveimg ($f3->FILES["file"], $saveto, [
					"size"=>[$f3->POST["width"], $f3->POST["height"]],
					"crop"=>true,
					"enlarge"=>true,
					"overwrite"=>true,
					"quality"=>100,
					"type"=>$ext
				]);
			});

			$f3->route("POST /admin/dropimg/upload_from_url", function ($f3) {

				// saveimg($f3->POST["url"], $f3->POST["file"], array(
				// 	"size" => [$f3->POST["width"], $f3->POST["height"]],
				// 	"crop" => true,
				// 	"enlarge" => true,
				// 	"quality" => 100,
				// 	"type" => "auto",
				// 	"overwrite" => true
				// ));

				// die();

				$file = $f3->POST["file"];
				$file = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $file);
				$ext = pathinfo(getcwd()."/".$file)["extension"];
				$width = $f3->POST["width"];
				$height = $f3->POST["height"];

				// If copying from google, trim this silly url
				$f3->POST["url"] = str_replace("https://www.google.com/imgres?imgurl=", "", $f3->POST["url"]);
				$f3->POST["url"] = str_replace("https://www.google.com.au/imgres?imgurl=", "", $f3->POST["url"]);

				if (copy(str_replace(" ", "%20", $f3->POST["url"]), $file)) {
					$temp_image = new Image($file, false, '');

					$temp_image->resize($width, $height, true, true); // resize(width, height, crop, enlarge)

					// Save image depending on user selected file type
					switch ($ext)
					{	
						case "jpg":
						case "jpeg":
							imagejpeg($temp_image->data($ext, 100), $file);
						break;
						case "png":
							imagepng($temp_image->data($ext, 100), $file);
						break;
						case "gif":
							imagegif($temp_image->data($ext, 100), $file);
						break;
					}
					return;
				}

				return;
			});

			if (isroute("/admin/*"))
				return;

			toolbar::instance()->append(Template::instance()->render("dropimg/init.html", null));
		}

		Template::instance()->extend("dropimg", function ($args) {
			$f3 = base::instance();

			if (!isset($args["@attrib"]))
				$f3->error(1, "DropIMG: No attributes found?");

			if (!array_key_exists("src", $args["@attrib"]))
				$f3->error(1, "DropIMG: no src value found");

			$src = $args["@attrib"]["src"];
			unset($args["@attrib"]["src"]);

			check (1, (!$args["@attrib"]["resize"] && !$args["@attrib"]["size"]), "No size attribute found for dropimg tag");

			$size = $args["@attrib"]["size"] ? $args["@attrib"]["size"] : $args["@attrib"]["resize"];
			unset($args["@attrib"]["size"], $args["@attrib"]["resize"]);
			$asize = explode("x", $size);

			$placeholder_path = "https://placeholdit.imgix.net/~text?txtsize=33&txt=".$asize[0]."x".$asize[1]."&w=".$asize[0]."&h=".$asize[1];

			// Does the file exsist?
			if (!file_exists($path = getcwd()."/".$src)) {

				copy($placeholder_path, $path);
			}

			// Have we changed the image size
			if (Cache::instance()->exists("dropimg_".sha1($path), $value))
			{
				if ($size != $value)
				{
					copy($placeholder_path, $path);
					Cache::instance()->set("dropimg_".sha1($path), $size);
				}
			}

			$string .= '<?php if (admin::$signed) {?>';
			$string .= "<img";
			$string .= " title='".$size."'";	
			$string .= " data-file='".$src."'";
			$string .= " data-width='".$asize[0]."'";
			$string .= " data-height='".$asize[1]."'";
			$string .= " data-mime='".mime_content_type2($path)."' ";
			$string .= " src='".$src."?<?=substr(sha1_file('".$src."'), -8);?>'";

			$classFilled = false;
			foreach ($args["@attrib"] as $key=>$value) {

				if ($key=="class") {
					$string .= 'class="'.$value.' imgdropzone" ';
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
			$string .= ' src="'.$src.'" ';

			foreach ($args["@attrib"] as $key=>$value) {
				$string .= $key.'="'.$value.'" ';
			}

			$string .= ">";
			$string .= "<?php } ?>";

			return $string;
		});


		Template::instance()->extend("dropfile", function ($args) {
			$f3 = base::instance();

			$string .= '<?php if (admin::$signed) {?>';

			$string .= '<a ';

			$classFilled = false;
			foreach ($args["@attrib"] as $key=>$value) {

				if ($key=="class") {
					$string .= 'class="'.$value.' filedropzone" ';
					$classFilled = true;
				} else {
					$string .= $key.'="'.$value.'" ';
				}
			}

			if (!$classFilled)
				$string .= 'class="filedropzone" ';

			$string .= 'id="'.uniqid("dropfile_").'" ';
			$string .= 'onclick="return false;" ';

			foreach ($args["@attrib"] as $key=>$value) {
				$string .= $key.'="'.$value.'" ';
			}

			$string .= '>';
			$string .= $args[0];
			$string .= "</a>";
			$string .= "<?php } ?>";

			$string .= '<?php if (!admin::$signed) {?>';

			$string .= '<a ';

			foreach ($args["@attrib"] as $key=>$value) {
				$string .= $key.'="'.$value.'" ';
			}

			$string .= '>';
			$string .= $args[0];
			$string .= "</a>";
			$string .= "<?php } ?>";

			return $string;
		});

	}
}