<?php

class content_blocks extends prefab {
	
	static $hasInit = false;

	function __construct() {

		$f3 = base::instance();

		if ($this->hasInit())
		{
			content_blocks::$hasInit = true;

			// "return" is used in forms which
			// return back to the same page.
			// The main example of this is the contact
			// module which can be used on the same page.
			if ($f3->POST["return"])
				$page = $f3->POST["return"];
			else
				$page = $f3->PATH;

			$page = ($page!="/") ? trim($page, "/") : "index";

			$this->retreiveContent($f3, $page);
		}

		if (admin::$signed)
			$this->admin_routes($f3);
	}


	function admin_routes($f3) {

		$f3->route('GET /admin/pages', 'content_blocks::admin_page_render');
		$f3->route('GET /admin/page/edit/@page', "content_blocks::admin_edit_render");
		$f3->route('POST /admin/page/generate', function ($f3) {

			if (!content_blocks::$hasInit)
				content_blocks::generate();

			$f3->reroute("/admin/pages");
		});

		$f3->route('POST /admin/page/save', function ($f3, $params) {
			if (!admin::$signed) { return; }

			content_blocks::save_inline($f3);
		});

		$f3->route('POST /admin/page/htmlsave', function ($f3, $params) {
			content_blocks::save_inline($f3, $f3->POST["id"], $f3->POST["contents"]);
			die;
		});

		$f3->route('GET /admin/ckeditor_config.js', "content_blocks::ckeditor_toolbar");
		$f3->route('GET /admin/ckeditor_imgs_config.js', "content_blocks::ckeditor_imgs_toolbar");
		$f3->route('GET /admin/ckeditor_header_config.js', "content_blocks::ckeditor_header_toolbar");

		$f3->route('GET /admin/page/delete_content/@content', "content_blocks::deleteContent");
		$f3->route('GET /admin/page/html_edit/@content', "content_blocks::admin_render_htmledit");
		$f3->route('GET /admin/page/ace.js', "content_blocks::ace_editor");

		$f3->route('POST /admin/page/add_content', function ($f3) {

			if (strlen($f3->POST["content_name"]) == 0)
			{
				$f3->mock("GET /admin/pages");
			} else {

				// Ensure no duplicate content names are used
				$result = base::instance()->DB->exec("SELECT id FROM contentBlocks WHERE contentName=? AND page LIKE ?", [$f3->POST["content_name"], "%".$f3->POST["page"]."%"]);

				if ($result)
				{
					$f3->mock("GET /admin/pages");
				} else {
					content_blocks::createContent($f3->POST["content_name"], $f3->POST["page"], $f3->POST["type"], $f3->POST["dummy_content"]);
					$f3->mock("GET /admin/pages");
				}
			}
		});
	}


	static function createContent($name, $page=null, $type=null, $dummy_content=null)
	{
		$db = base::instance()->DB;

		$db->exec("INSERT INTO contentBlocks (page, content, type, contentName) VALUES (?,?,?,?)", [$page, $dummy_content, $type, $name]);
	}

	static function deleteContent($f3, $params) {
		base::instance()->DB->exec("DELETE FROM contentBlocks WHERE id=?", $params["content"]);

		$f3->mock("GET /admin/pages");
	}

	function retreiveContent($f3, $page) {
		$db = $f3->get("DB");
		$blocksraw = $db->exec('SELECT * FROM contentBlocks WHERE page LIKE ? OR page="all" OR page=""', "%".$page."%");

		$bc = array(); // Blocks compiled
		$ck_instances = array();
		foreach ($blocksraw as $key=>$block) {
			if ($block["contentName"] != "")
			{
				// Wrap in content editable
				if (admin::$signed) {

					switch ($block['type'])
					{
						case "header":
							$block["content"] = "<div contenteditable='true' id='".$block["page"]."_".$block["id"]."'>" . $block["content"] . "</div>";
						break;

						case "none": break;

						default:
							$block["content"] = "<div contenteditable='true' id='".$block["page"]."_".$block["id"]."'>" . $block["content"] . "</div>";
						break;
					}

					$ck_instances[$key]["id"] = $block["page"]."_".$block["id"];
					$ck_instances[$key]["type"] = $block["type"];
				}

				$f3->set($block["contentName"], $block["content"]);
			}
		}

		if (admin::$signed)
		{
			$f3->set("ck_instances", $ck_instances);

			$tmp = $f3->get("UI");
			$f3->set('UI', $f3->CMS."adminUI/");
			$inlinecode = Template::instance()->render("ckeditor_inline.js");
			$f3->set('UI', $tmp);

			$f3->concat("ckeditor", $inlinecode);
		}
	}

	function loadAll ($f3) {

		$pages = glob("*.html");

		$blocks["all"] = array();

		foreach ($pages as $page) {
			if ($page[0] == "_") continue;

			$page = str_replace(".html", "", $page);
			$blocks[$page] = array();
		}	
		
		$f3->set("pages", $blocks);

		$db = $f3->get("DB");
		$result = $db->exec('SELECT * FROM contentBlocks ORDER BY page');


		if (!$result) return;

		foreach ($result as $contentBlock)
		{
			if ($contentBlock["page"] == "all" || $contentBlock["page"] == "")
				$blocks["all"][] = $contentBlock;
			else
				$blocks[] = $contentBlock;
		}

		$f3->set("pages", $blocks);
	}

	static function save_inline($f3, $id=null, $content=null) 
	{
		if ($id==null)
		{
			$pageID = filter_var($f3->get("POST.editorID"), FILTER_SANITIZE_NUMBER_INT);
			$pageContent = $f3->get("POST.editabledata");
		} else {
			$pageID = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
			$pageContent = $content;
		}

		$db = $f3->get("DB");

		$db->exec("UPDATE contentBlocks SET content=:content WHERE id=:id", array(
			":id"=>$pageID,
			":content"=>$pageContent
		));
	}

	function hasInit() {
		$db = base::instance()->DB;

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='contentBlocks'");

		if ($result)
		{
			$this->patch_columns();
			return true;
		}
		else
			return false;
	}

	function generate() {
		$f3 = base::instance();

		$db = base::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS 'contentBlocks' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'page' TEXT, 'content' TEXT, 'lastUpdated' DATETIME DEFAULT CURRENT_TIMESTAMP,'contentName' TEXT);");

		// $pages = $f3->POST["pages"];
		// $pages = explode(",", $pages);
		// $pages = array_unique($pages);
		
		// foreach ($pages as $page) {
			
		// }

	}

	static public function admin_page_render($f3)
	{
		if (content_blocks::instance()->hasInit()) {
			content_blocks::instance()->loadAll($f3);

			echo Template::instance()->render("content_blocks/pages.html");
		} 
		else 
		{
			echo Template::instance()->render("content_blocks/nopages.html");	
		}
	}

	static public function admin_edit_render($f3, $params)
	{
		content_blocks::instance()->loadAll($f3);

		foreach ($f3->get("pages") as $pagename=>$page) {
			if ($pagename == $params["page"])
				$editable = $page;
		}

		$f3->set("editable", $editable);

		echo Template::instance()->render("content_blocks/page_edit.html");
	}

	static public function admin_render_htmledit ($f3, $params) {
		$result = base::instance()->DB->exec("SELECT * FROM contentBlocks WHERE id=?", $params["content"]);
		base::instance()->set("block", $result[0]);

		echo Template::instance()->render("content_blocks/ace_editor.html");
	}

	static public function ckeditor_toolbar($f3) 
	{
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("ckeditor_config.js", "text/javascript");
		$f3->UI = $tmp;
	}

	static public function ckeditor_imgs_toolbar($f3) 
	{
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("ckeditor_imgs_config.js", "text/javascript");
		$f3->UI = $tmp;
	}

	static public function ckeditor_header_toolbar($f3) 
	{
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("ckeditor_header_config.js", "text/javascript");
		$f3->UI = $tmp;
	}

	static public function ace_editor($f3) 
	{
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("content_blocks/additional.js", "text/javascript");
		$f3->UI = $tmp;
	}

	function patch_columns ()
	{
		$result = base::instance()->DB->exec("PRAGMA table_info(contentBlocks)");
		
		//Patch to ensure type column is added.
		foreach ($result as $r) {
			if ($r["name"] == "type") {
				return;
			}
		}

		base::instance()->DB->exec("ALTER TABLE contentBlocks ADD COLUMN type char(100)");
	}
}