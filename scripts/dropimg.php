<?php

class dropimg extends prefab {

	function __construct() {

		$f3 = base::instance();


		if (admin::$signed) {

			$f3->route("POST /admin/dropimg/upload", function ($f3) {

				// Check if current image exists
				// if (!file_exists(getcwd()."/".$f3->POST["filename"]))
				// {
				// 	echo "HUH, file does not exist??";
				// 	die;
				// }

				$file = $f3->POST["file"];
				$file = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $file);
				$ext = pathinfo(getcwd()."/".$file)["extension"];
				$width = $f3->POST["width"];
				$height = $f3->POST["height"];
				
				// Resize and overwrite 
				//$this->resize($f3->FILES["file"]["tmp_name"], $file, $size[0], $size[1], $ext);

				$image = $f3->FILES["file"]["tmp_name"];
				$save_to = $file;
				$file_type = $ext;

				// Pull image off the disk into memory
				$temp_image = new Image($image, false, ''); // Image(filename, filehistory, path)

				// Make sure that width and height are set before resizing image
				if (($width*$height) > 0)
				{
					// Resize image using F3's image plugin
					$temp_image->resize($width, $height, true, true); // resize(width, height, crop, enlarge)
				}

				// Save image depending on user selected file type
				switch ($file_type)
				{	
					case "jpg":
					case "jpeg":
						imagejpeg($temp_image->data($file_type, 100), $save_to);
					break;
					case "png":
						imagepng($temp_image->data($file_type, 100), $save_to);
					break;
					case "gif":
						imagegif($temp_image->data($file_type, 100), $save_to);
					break;
				}
			});

			$f3->route("POST /admin/dropimg/upload_from_url", function ($f3) {
			
				$file = $f3->POST["file"];
				$file = str_replace($f3->SCHEME."://".$f3->HOST.$f3->BASE."/", "", $file);
				$ext = pathinfo(getcwd()."/".$file)["extension"];
				$width = $f3->POST["width"];
				$height = $f3->POST["height"];

				// If copying from google, trim this silly url
				$f3->POST["url"] = str_replace("https://www.google.com/imgres?imgurl=", "", $f3->POST["url"]);
				$f3->POST["url"] = str_replace("https://www.google.com.au/imgres?imgurl=", "", $f3->POST["url"]);

				if (copy($f3->POST["url"], $file)) {
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

			check (1, (!$args["@attrib"]["resize"] && !$args["@attrib"]["size"]), "No size attribute found for dropimg tag");

			$size = $args["@attrib"]["size"] ? $args["@attrib"]["size"] : $args["@attrib"]["resize"];
			unset($args["@attrib"]["size"], $args["@attrib"]["resize"]);
			$asize = explode("x", $size);

			$placeholder_path = "https://placeholdit.imgix.net/~text?txtsize=33&txt=".$asize[0]."x".$asize[1]."&w=".$asize[0]."&h=".$asize[1];

			// Does the file exsist?
			if (!file_exists($path = getcwd()."/".$args["@attrib"]["src"])) {

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
			$string .= "<img ";
			$string .= " title='".$size."'";	
			$string .= " data-file='".$args["@attrib"]["src"]."'";
			$string .= " data-width='".$asize[0]."'";
			$string .= " data-height='".$asize[1]."'";
			$string .= " data-mime='".mime_content_type2($path)."' ";

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
			$string .= "<img ";

			foreach ($args["@attrib"] as $key=>$value) {
				$string .= $key.'="'.$value.'" ';
			}

			$string .= ">";
			$string .= "<?php } ?>";

			return $string;
		});

	}
}