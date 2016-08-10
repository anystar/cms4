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

			// Are there sub pages?
			$page = explode("/", $page);

			if (count($page) == 1)
				$page = $page[0];

			$this->retreiveContent($f3, $page);
		}

		if (admin::$signed)
			$this->admin_routes($f3);
	}


	function admin_routes($f3) {

		$f3->route('GET /admin/pages', 'content_blocks::render_quick_view');
		$f3->route('GET /admin/pages/@page', 'content_blocks::render_admin_page');
		$f3->route('GET /admin/pages/@page/*', 'content_blocks::render_admin_page');

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
			if (!admin::$signed) { return; }

			content_blocks::save_inline($f3);
			die;
		});

		$f3->route('GET /admin/css/admin_toolbar.css', "content_blocks::admin_toolbar_css");
		$f3->route('GET /admin/js/admin_toolbar.js', "content_blocks::admin_toolbar_js");

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

		// Are we dealing with sub pages?
		if (is_array($page))
		{
			$rootPage = $page[0];
			$page = implode("/", $page);

			// Lets get the contents of the root page
			// and then we'll just override contents..
			$this->setBlocks($f3, $rootPage, $page);
		}

		$this->setBlocks($f3, $page);

		if (admin::$signed)
		{
			// Load up the Editor
			$tmp = $f3->get("UI"); $f3->set('UI', $f3->CMS."adminUI/");
			$inlinecode = Template::instance()->render("ckeditor_inline.js");
			$f3->set('UI', $tmp);

			$f3->concat("ckeditor", $inlinecode);
			$f3->concat("admin", $inlinecode);
		}
	}

	function setBlocks ($f3, $page, $subpage=null) {
		$blocksraw = $f3->DB->exec('SELECT * FROM contentBlocks WHERE page=? OR page="all" OR page=""', $page);

		// There are no blocks to process
		if (!$blocksraw) return;

		$compiled = array();
		foreach ($blocksraw as $key=>$block) {
			
			// skip if we have no content name.
			if ($block["contentName"] == "") continue;

			// if there is no page, label as for all pages
			if ($block["page"] == "") $block["page"] = "all";

			// If we are admin call the editor function
			if (admin::$signed) {
				if ($subpage!=null)
				{
					$block = $this->wrapWithCKEDITOR($f3, $block, $subpage);
				}
				else
					$block = $this->wrapWithCKEDITOR($f3, $block, $page);

			}

			// Insert into hive
			$f3->set($block["contentName"], $block["content"]);
		}
	}

	function wrapWithCKEDITOR($f3, $block, $page) {
		
		// Make safe for the editor
		$block["page"] = str_replace('/', '_forwardslash_', $page);

		// Yet to be reimplemented!
		switch ($block['type'])
		{
			default:
				$block["content"] = "<div contenteditable='true' id='".$block["page"]."_id-".$block["id"]."'>" . $block["content"] . "</div>";
			break;
		}

		$f3->ck_instances[] = array(
			"id"=>$block["page"]."_id-".$block["id"],
			"type"=>$block["type"]
		);

		return $block;
	}

	function loadAll ($f3) {

		$pages = glob("*.html");

		$blocks["all"] = array();

		foreach ($pages as $page) {
			if ($page[0] == "_") continue;

			$pageName = str_replace(".html", "", $page);
			$blocks[$pageName] = array();
		}	
		
		$f3->set("pages", $blocks);

		$db = $f3->get("DB");
		$result = $db->exec('SELECT * FROM contentBlocks ORDER BY page');

		// Don't bother if there are no content blocks from DB
		if (!$result) return;

		$subpage_counter = array();
		foreach ($result as $contentBlock)
		{
			$page = $contentBlock["page"];

			if ($page == "all" || $page == "")
				$blocks["all"][] = $contentBlock;
			else
			{
				$tmp = explode("/", $page);
				if (count($tmp) > 1) {
					$subpage_counter[$tmp[0]][$page] = 1;
					continue; // Ignore sub pages
				}

				$blocks[$page][] = $contentBlock;
			}
		}

		foreach ($subpage_counter as $key=>$value) {
			$blocks[$key]["subpages"] = count($value);
		}

		$f3->set("pages", $blocks);
	}

	static function save_inline($f3, $id=null, $content=null) 
	{

		// Get Page name and Page ID
		$page = str_replace('_forwardslash_', '/', $f3->get("POST.editorID"));
		$tmp = explode("_id-", $page);
		$page = $tmp[0];
		$blockID = $tmp[1];

		if (!$content)
			$content = $f3->get("POST.editabledata");

		$db = $f3->get("DB");

		// Get content name
		$contentName = $db->exec("SELECT contentName FROM contentBlocks WHERE id=?", $blockID)[0]["contentName"];

		// Does the content block exist?
		$id = $db->exec("SELECT id FROM contentBlocks WHERE page=? AND contentName=?", [$page, $contentName]);

		if (!$id)
		{
			// Copy Row
			$result = $db->exec("
					INSERT INTO contentBlocks (page, contentName, type) 
					select page, contentName, type
					from contentBlocks
					where id=?
				", $blockID);

			$blockID = $db->lastInsertId();
			$db->exec("UPDATE contentBlocks SET page=? WHERE id=?", [$page, $blockID]);
		}

		$result = $db->exec("UPDATE contentBlocks SET content=:content WHERE id=:id", array(
				":id"=>$blockID,
				":content"=>$content
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

	static public function render_quick_view($f3)
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



	static public function get_page ($f3, $page) {

		$f3->page_name = $page;
		$f3->contentBlocks = $f3->DB->exec("SELECT * FROM contentBlocks WHERE page=?", $page);

	}


	static public function render_admin_page($f3, $params) {

		content_blocks::instance()->loadAll($f3);
		content_blocks::get_page($f3, $params["page"]);

		echo Template::instance()->render("content_blocks/page.html");

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
		$result[0]["content"] = htmlspecialchars($result[0]["content"]);

		base::instance()->set("block", $result[0]);

		echo Template::instance()->render("content_blocks/ace_editor.html");
	}

	static public function admin_toolbar_css($f3) 
	{
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("css/admin_toolbar.css", "text/css");
		$f3->UI = $tmp;
	}


	static public function admin_toolbar_js($f3) 
	{
		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("js/admin_toolbar.js", "text/javascript");
		$f3->UI = $tmp;
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