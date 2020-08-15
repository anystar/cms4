<?php
use PHPHtmlParser\Dom;
use PHPHtmlParser\Options;

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
			// $path 	  = urldecode($f3->POST["path"]);
			// $id 	  = $f3->POST["id"];
			// $sentHash = $f3->POST["hash"];
			$contents = $f3->POST["contents"];
			$order =    (int)$f3->POST["order"]; // Starting at 0
			$method = $f3->POST["method"];

			if ($method == "attribute") {
				SaveOnAttribute($filename, $order, $contents);
			} else {
				SaveOnTag($filename, $order, $contents);
			}
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
			
			$file = str_replace(getcwd()."/", "", $view);

			$contents = str_replace(" ckeditor ",  " ckeditor cms-file='".$file."' ", $contents);

			return "{~ ".'$currentFileName="'.str_replace(getcwd()."/", "", $file).'";' . "~}" . PHP_EOL . $contents;
		});

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

			$out .= '<div cms-file="<?php echo $currentFileName ?>" id="'.$args['@attrib']['id'].'" data-ck-hash="'.$hash.'" class="ckeditor" data-ck-order="<?php if (!isset($ckeditor_order)) { $ckeditor_order=0; } echo $ckeditor_order++; ?>" contenteditable="true">';
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



function SaveOnTag($filename, $order, $contents) {
	// Load in to replace contents with
	$file = file_get_contents(getcwd()."/".$filename);

	//preg_match_all("#<ckeditor.*>.*<\/ckeditor>#siU", $file, $out);

	$count = 0;
	$file = preg_replace_callback("#(<ckeditor.*>)(.*)(<\/ckeditor>)#siU", function ($match) use (&$count, $order, $contents) {

		if ($count == $order) {

			$return .= $match[1];
			$return .= $contents;
			$return .= $match[3];

			$count++;
			return $return;
		} else {
			$count++;
			return $match[0];
		}
		
	}, $file);

	file_put_contents(getcwd()."/".$filename, $file, LOCK_EX);

	echo sha1($contents);
}



function SaveOnAttribute($filename, $index, $replacingContent)
{ 
	$dom = new Dom;
	// $dom->loadFromFile($filename, [
	// 	"cleanupInput" => false,
	// 	"whitespaceTextNode" => true,
	// 	"removeDoubleSpace" => false,
	// 	"preserveLineBreaks" => false,
	// 	"selfClosing" => false,
	// 	"noSlash" => false,
	// 	"removeDoubleSpace" => false
	// ]);

	$dom->setOptions(
		// this is set as the global option level.
			(new Options())
			->setStrict(false)
			->setWhitespaceTextNode(true)
			->setCleanupInput(false)
			->setRemoveScripts(false)
			->setRemoveStyles(false)
			->setPreserveLineBreaks(false)
			->setRemoveDoubleSpace(false)
			->addNoSlashTag('br')
			->addNoSlashTag('ckeditor')
			->addNoSlashTag('link')
			->addNoSlashTag('dropimg')
			->addNoSlashTag('meta')
			->addSelfClosingTags(['dropimg', 'DOCTYPE', 'meta'])
	);

	$dom->loadFromFile($filename);
	$contents = $dom->find('*[ckeditor]');

	if ($contents->count() == 0) return;

	$hash = sha1($contents[$index]);
	
	$i = 0;
	foreach ($contents as $key=>$content) {

		if ($hash == sha1($content))
		{   
			$i++;
			
			if ($key == $index)
			{
				$realIndex = $i;
				$fields[$key] = $content->outerHtml();
				$contents[$key]->removeChild(0);
			}
		}
	}

	$innerHtml = $contents[$index]->innerHtml();
	
	if ($innerHtml != "") {
		$replacingContent = str_replace($innerHtml, $replacingContent, $contents[$index]);
	} else {
		$xx = $dom->loadStr($replacingContent, []);
		$temp = clone($contents[$index]);
		$temp->addChild($xx->root);
		$replacingContent = $temp;
	}

	$file = file_get_contents($filename);
	$search = $contents[$index]->outerHtml();
	$count = 0;
	$replace_count; // debug variable for str_ureplace

	$file2 = str_ureplace($contents[$index]->outerHtml(), function ($match, $count) use($realIndex, $replacingContent, $search) {

		if ($count != $realIndex)
			return "REPLACE__".sha1($search)."__REPLACE";
		else
			return $replacingContent;

	}, $file, $replace_count);
	
	$file2 = str_replace("REPLACE__".sha1($search)."__REPLACE", $search, $file2);

	file_put_contents($filename, $file2);
}




/**
 * str_ureplace
 * 
 * str_replace like function with callbacks for replacement(s).
 * 
 * @param string|array $search
 * @param callback|array $replace
 * @param string|array $subject
 * @param int $replace_count
 * @return string|array subject with replaces, FALSE on error.
 */
function str_ureplace($search, $replace, $subject, &$replace_count = null) {
    $replace_count = 0;
    
    // validate input
    $search = array_values((array) $search);
    $searchCount = count($search);
    if (!$searchCount) {
        return $subject;
    }
    foreach($search as &$v) {
        $v = (string) $v;
    }
    unset($v);
    $replaceSingle = is_callable($replace);    
    $replace = $replaceSingle ? array($replace) : array_values((array) $replace);
    foreach($replace as $index=>$callback) {
        if (!is_callable($callback)) {
            throw new Exception(sprintf('Unable to use %s (#%d) as a callback', gettype($callback), $index));
        }
    }
    
    // search and replace
    $subjectIsString = is_string($subject);
    $subject = (array) $subject;
    foreach($subject as &$haystack) {
        if (!is_string($haystack)) continue;
        foreach($search as $key => $needle) {
            if (!$len = strlen($needle))
                continue;            
            $replaceSingle && $key = 0;            
            $pos = 0;
            while(false !== $pos = strpos($haystack, $needle, $pos)) {
                $replaceWith = isset($replace[$key]) ? call_user_func($replace[$key], $needle, ++$replace_count) : '';
                $haystack = substr_replace($haystack, $replaceWith, $pos, $len);
            }
        }
    }
    unset($haystack);
    
    return $subjectIsString ? reset($subject) : $subject;
}